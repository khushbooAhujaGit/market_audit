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

        $nextSeq = Question::where('activity_id', $data['activity_id'])->max('question_sequence') + 1;

        $parentId = !empty($data['parent_question_id']) ? (int)$data['parent_question_id'] : null;
        $isParent = ($parentId || !empty($data['parent_group_id'])) ? 0 : 1;

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

        // Create Dropdown / Multi select options
        if (!empty($data['options']) && in_array($data['question_type'], ['Dropdown', 'Multi select'])) {
            foreach (explode('|', $data['options']) as $opt) {
                $opt = trim($opt);
                if ($opt) {
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
                    // Multi Response or other parent: create question_sub_questions entry
                    QuestionSubQuestion::updateOrCreate(
                        ['parent_question_id' => $parentId, 'child_question_id' => $question_info->id],
                        ['sequence' => $idx + 1, 'trigger_value' => $triggerValue]
                    );
                }
            }
        }

        $question_info->update([
            'question'              => $form_data['question'],
            'question_type'         => $form_data['question_type'],
            // Keep existing sequence if not explicitly provided (edit panel sends null to preserve it)
            'question_sequence'     => !is_null($form_data['question_sequence'] ?? null)
                ? $form_data['question_sequence']
                : $question_info->question_sequence,
            'answer_type'           => $form_data['answer_type'],
            'parent_question_id'    => $oldStyleParentId,
            'parent_dropdown_id'    => $oldStyleDropdownId,
            'parent_value'          => $oldStyleParentValue,
            'allow_multiple_images' => $allowMultiple,
        ]);

        return response()->json(['message' => "success", 'question_info' => $question_info]);
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
