<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\QuestionSubQuestion;
use Illuminate\Http\Request; 
 
class QuestionController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    /** Create a brand-new question from the front-end Add Question panel. */
    public function store(Request $request)
    {
        $data = $request->form_data;

        $questionText = trim($data['question'] ?? '');
        $questionType = trim($data['question_type'] ?? 'Free Text');
        $parentGroupId = !empty($data['parent_group_id']) ? $data['parent_group_id'] : null;

        // Duplicate check for standalone questions (not sub-questions of an MR parent).
        // Same text + same type → duplicate, reject.
        // Same text + different type → allowed (e.g. "Remark" as Free Text and as Image).
        if (!$parentGroupId) {
            $existingQ = Question::where('activity_id', $data['activity_id'])
                ->where('question', $questionText)
                ->where('question_type', $questionType)
                ->where('is_parent', 1)
                ->exists();

            if ($existingQ) {
                return response()->json([
                    'message' => 'A question with this text and type already exists. Use a different question text or choose a different type.',
                ], 422);
            }
        }

        $nextSeq = Question::where('activity_id', $data['activity_id'])->max('question_sequence') + 1;

        $parentId = !empty($data['parent_question_id']) ? (int)$data['parent_question_id'] : null;
        $isParent = ($parentId || $parentGroupId) ? 0 : 1;

        $allowMultiple = ($data['question_type'] === 'Image')
            ? (int)($data['allow_multiple_images'] ?? 0) : 0;

        $q = Question::create([
            'activity_id'           => $data['activity_id'],
            'question'              => $data['question'],
            'question_type'         => $data['question_type'],
            'question_sequence'     => $nextSeq,
            'answer_type'           => (int)($data['answer_type'] ?? 1),
            'is_parent'             => $isParent,
            'help_text'             => $data['help_text']       ?? null,
            'validation_rule'       => $data['validation_rule'] ?? 'none',
            'validation_min'        => $data['validation_min']  ?? null,
            'validation_max'        => $data['validation_max']  ?? null,
            'validation_regex'      => $data['validation_regex'] ?? null,
            'file_types'            => $data['file_types']      ?? null,
            'max_file_size_mb'      => is_numeric($data['max_file_size_mb'] ?? null) ? (float)$data['max_file_size_mb'] : null,
            'date_min'              => $data['date_min']        ?? null,
            'date_max'              => $data['date_max']        ?? null,
            'parent_question_id'    => $parentId,
            'parent_value'          => $data['parent_value']    ?? null,
            'allow_multiple_images' => $allowMultiple,
        ]);

        // Create Dropdown / Multi select options — also reused for Barcode/QR Code/RFID
        // questions, where each "option" is an expected header name to validate the
        // scanned code's data against at answer time, not a selectable choice.
        if (!empty($data['options']) && in_array($data['question_type'], ['Dropdown', 'Multi select', 'Barcode', 'QR Code', 'RFID'])) {
            foreach (explode('|', $data['options']) as $opt) {
                $opt = trim($opt);
                if ($opt !== '') {
                    \App\Models\QuestionDropdown::create(['question_id' => $q->id, 'option' => $opt]);
                }
            }
        }

        // Link as sub-question of a Multi Response parent
        if (!empty($data['parent_group_id'])) {
            $nextSubSeq = QuestionSubQuestion::where('parent_question_id', $data['parent_group_id'])->max('sequence') + 1;
            QuestionSubQuestion::create([
                'parent_question_id' => (int)$data['parent_group_id'],
                'child_question_id'  => $q->id,
                'sequence'           => (int)$nextSubSeq,
            ]);
        }

        return response()->json(['message' => 'success', 'question' => $q->load('getOptions')]);
    }

    public function update(Request $request)
    {
        $form_data     = $request->form_data;
        $question_info = Question::find($form_data['id']);
        if (!$question_info) {
            return "This question doesn't exists";
        }

        $allowMultiple = ($form_data['question_type'] === 'Image') ? (int)($form_data['allow_multiple_images'] ?? 0) : 0;

        // Duplicate check on edit: same text + same type as another question in the activity → reject.
        $newText = trim($form_data['question'] ?? '');
        $newType = trim($form_data['question_type'] ?? '');
        if ($newText && $newType) {
            $conflict = Question::where('activity_id', $question_info->activity_id)
                ->where('question', $newText)
                ->where('question_type', $newType)
                ->where('is_parent', 1)
                ->where('id', '!=', $question_info->id)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'Another question with this text and type already exists. Use a different question text or choose a different type.',
                ], 422);
            }
        }
 
        // ── Sync multi-parent trigger links ──────────────────────────────────
        // parent_triggers comes from the multi-select builder.
        // We also derive the old-style parent_question_id / parent_value from it
        // (the first Yes/No or Dropdown parent) for backward-compatible JS show/hide.
        $parentTriggers = $form_data['parent_triggers'] ?? [];

        $oldStyleParentId    = null;
        $oldStyleParentValue = null;
        $oldStyleDropdownId  = null;

        if (!empty($parentTriggers)) {
            // Only delete Yes/No and Dropdown conditional links.
            // Multi Response sub-question links are managed via the sub-questions page
            // and must NOT be deleted here.
            QuestionSubQuestion::where('child_question_id', $question_info->id)
                ->whereHas('parentQuestion', function ($q) {
                    $q->whereIn('question_type', ['Yes / No', 'Dropdown']);
                })
                ->delete();

            foreach ($parentTriggers as $idx => $pt) {
                $parentId     = (int)($pt['parent_id'] ?? 0);
                $triggerValue = $pt['trigger_value'] ?? null;
                if (!$parentId) continue;

                $pq = Question::find($parentId);
                if (!$pq) continue;

                if (in_array($pq->question_type, ['Yes / No', 'Dropdown'])) {
                    // Conditional parent: stored ONLY as parent_question_id on the question.
                    // Do NOT create a question_sub_questions entry — that would cause the
                    // question to render twice on the auditor form (once conditional + once
                    // as a sub-question with _pctx_ naming), producing duplicate DB records.
                    if ($oldStyleParentId === null) {
                        $oldStyleParentId    = $parentId;
                        $oldStyleParentValue = $triggerValue;
                        $oldStyleDropdownId  = ($pq->question_type !== 'Yes / No')
                            ? QuestionDropdown::where('question_id', $parentId)
                                ->where('option', $triggerValue)->value('id')
                            : null;
                    }
                } else {
                    // Multi Response or other parent: create/update question_sub_questions entry.
                    // IMPORTANT: never derive sequence from $idx here — $parentTriggers mixes
                    // old-style conditional links and new-style MR links together, so its
                    // array position has nothing to do with this sub-question's real order
                    // among its MR siblings (this previously caused an unrelated field edit,
                    // e.g. toggling required/optional, to silently reshuffle sub-question
                    // order — see "Manage Sub Questions" for the actual reorder UI, which
                    // updates sequence correctly via updateSubQuestionSequence()).
                    // Preserve the existing sequence on update; only assign one (append to
                    // end of this parent's children) when the link is genuinely new.
                    $existingLink = QuestionSubQuestion::where('parent_question_id', $parentId)
                        ->where('child_question_id', $question_info->id)
                        ->first();

                    if ($existingLink) {
                        $existingLink->update(['trigger_value' => $triggerValue]);
                    } else {
                        $nextSequence = (int) QuestionSubQuestion::where('parent_question_id', $parentId)->max('sequence') + 1;
                        QuestionSubQuestion::create([
                            'parent_question_id' => $parentId,
                            'child_question_id'  => $question_info->id,
                            'sequence'           => $nextSequence,
                            'trigger_value'      => $triggerValue,
                        ]);
                    }
                }
            }
        }

        // If a conditional parent is being assigned, demote this question from parent to child.
        // If the conditional parent is cleared, leave is_parent unchanged — the question may
        // still be a sub-question of an MR group (managed via question_sub_questions).
        $isParentUpdate = $oldStyleParentId ? 0 : $question_info->is_parent;

        $question_info->update([
            'question'              => $form_data['question'],
            'question_type'         => $form_data['question_type'],
            // Keep existing sequence if not explicitly provided (edit panel sends null to preserve it)
            'question_sequence'     => !is_null($form_data['question_sequence'] ?? null)
                ? $form_data['question_sequence']
                : $question_info->question_sequence,
            'answer_type'           => $form_data['answer_type'],
            'help_text'             => $form_data['help_text']        ?? null,
            'validation_rule'       => $form_data['validation_rule']  ?? 'none',
            'validation_min'        => $form_data['validation_min']   ?? null,
            'validation_max'        => $form_data['validation_max']   ?? null,
            'validation_regex'      => $form_data['validation_regex'] ?? null,
            'file_types'            => $form_data['file_types']       ?? null,
            'max_file_size_mb'      => is_numeric($form_data['max_file_size_mb'] ?? null) ? (float)$form_data['max_file_size_mb'] : null,
            'date_min'              => $form_data['date_min']         ?? null,
            'date_max'              => $form_data['date_max']         ?? null,
            'parent_question_id'    => $oldStyleParentId,
            'parent_dropdown_id'    => $oldStyleDropdownId,
            'parent_value'          => $oldStyleParentValue,
            'allow_multiple_images' => $allowMultiple,
            'is_parent'             => $isParentUpdate,
        ]);

        // Sync dropdown / multi-select options.
        // Also propagate to all questions in the same activity that share the same
        // question text + type — these are reused sub-questions across Multi Response parents.
        if (in_array($form_data['question_type'] ?? '', ['Dropdown', 'Multi select', 'Barcode', 'QR Code', 'RFID'])) {
            $rawOptions = $form_data['options'] ?? '';
            $parsedOpts = [];
            if ($rawOptions !== '' && $rawOptions !== null) {
                foreach (explode('|', $rawOptions) as $opt) {
                    $opt = trim($opt);
                    if ($opt !== '') $parsedOpts[] = $opt;
                }
            }

            // Find all sibling questions in the same activity with the same text + type
            $siblingIds = Question::where('activity_id', $question_info->activity_id)
                ->where('question', $question_info->question)
                ->where('question_type', $question_info->question_type)
                ->pluck('id');

            foreach ($siblingIds as $qid) {
                QuestionDropdown::where('question_id', $qid)->delete();
                foreach ($parsedOpts as $opt) {
                    QuestionDropdown::create(['question_id' => $qid, 'option' => $opt]);
                }
            }
        }

        return response()->json(['message' => "success", 'question_info' => $question_info->load('getOptions')]);
    }

    /** Return all existing parent-trigger links for a question (pre-populates the edit modal).
     *  Includes both new-style (question_sub_questions) and old-style (parent_question_id). */
    public function getParentLinks($id)
    {
        $question = Question::with('getOptions')->find($id);
        if (!$question) return response()->json(['links' => []]);

        // New-style: question_sub_questions entries
        $links = QuestionSubQuestion::with(['parentQuestion.getOptions'])
            ->where('child_question_id', $id)
            ->orderBy('sequence')
            ->get()
            ->map(function ($link) {
                $parent = $link->parentQuestion;
                return [
                    'parent_id'     => $link->parent_question_id,
                    'parent_name'   => $parent?->question ?? '',
                    'parent_type'   => $parent?->question_type ?? '',
                    'trigger_value' => $link->trigger_value,
                    'options'       => $parent?->getOptions->pluck('option')->values() ?? [],
                ];
            })->toArray();

        // Old-style: parent_question_id on the question itself
        // Add it if not already in the list (avoid duplicate entries)
        if ($question->parent_question_id) {
            $alreadyListed = collect($links)->contains('parent_id', $question->parent_question_id);
            if (!$alreadyListed) {
                $parent = Question::with('getOptions')->find($question->parent_question_id);
                if ($parent) {
                    $links[] = [
                        'parent_id'     => $question->parent_question_id,
                        'parent_name'   => $parent->question,
                        'parent_type'   => $parent->question_type,
                        'trigger_value' => $question->parent_value,
                        'options'       => $parent->getOptions->pluck('option')->values(),
                    ];
                }
            }
        }

        return response()->json(['links' => $links]);
    }

    /** Return sub-questions linked to a Multi Response parent as JSON (for the edit panel). */
    public function getSubQuestionsJson($id)
    {
        $question = Question::find($id);
        if (!$question) return response()->json(['sub_questions' => []]);

        $links = QuestionSubQuestion::with('childQuestion')
            ->where('parent_question_id', $question->getKey())
            ->orderBy('sequence')
            ->get()
            ->map(function ($link) {
                $child = $link->childQuestion;
                return [
                    'id'          => $link->child_question_id, // raw child question ID
                    'link_id'     => $link->id,                // question_sub_questions.id for unlinking
                    'question'    => $child?->question ?? '',
                    'type'        => $child?->question_type ?? '',
                    'answer_type' => $child?->answer_type ?? 0,
                    'required'    => ($child?->answer_type ?? 0) == 1,
                    'sequence'    => $link->sequence,
                ];
            });

        return response()->json(['sub_questions' => $links]);
    }

    public function destroy(Request $request)
    {
        $question = Question::find($request->id);
        $question->delete();
        return "Success";
    }
}
