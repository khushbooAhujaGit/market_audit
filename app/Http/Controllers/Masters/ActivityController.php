<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\QuestionSubQuestion;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DynamicTableExport; 

class ActivityController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function index()
    {
//        $activities = Activity::latest()->paginate(10);
        $activities = Activity::orderBy('id', 'DESC')->get();
        //{{ $activities->links() }}
        return view("masters.activities.index", compact('activities'));
    }
    public function create()
    {
        return view('masters.activities.add');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'activity_name'   => ['required', 'unique:activities,activity_name'],
            'questions_excel' => [
                'nullable',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ]);

        $acitivity = Activity::create([
            'activity_name' => $request->activity_name
        ]);
        $sequence_counter = 1;

        // Check if file is present in the request
        if ($request->hasFile('questions_excel')) {
            $file = $request->file('questions_excel');
            $data = Excel::toCollection(Excel::class, $file)->first();
            $dataArray = $data->toArray();

            // ── Auto-detect column positions from the header row ──────────────────
            // This makes the import work regardless of column order or template version
            // (old 18-col vs new 19-col with "Allow Multiple Images")
            $headerRow = array_shift($dataArray);
            $colMap = [];
            foreach ($headerRow as $idx => $hdr) {
                if ($hdr === null) continue;
                $key = strtolower(trim(preg_replace('/\s+/', '_', (string)$hdr)));
                $colMap[$key] = $idx;
            }

            // Skip description row (row 2) if present — it won't have a numeric Sr. Number
            if (!empty($dataArray) && !is_numeric($dataArray[0][0] ?? null)) {
                array_shift($dataArray);
            }

            // Helper: safely read a column by its header name
            $col = function (array $row, string $name, $default = null) use ($colMap) {
                if (!isset($colMap[$name])) return $default;
                return $row[$colMap[$name]] ?? $default;
            };

            $sequenceToQuestionId = [];
            $questionTextToId     = [];

            // ── PASS 1: Create all questions ──────────────────────────────────
            foreach ($dataArray as $row) {
                if (empty($row[0]) && empty($row[$colMap['question_text'] ?? 1] ?? null)) continue;

                $questionText = trim((string)($col($row, 'question_text') ?? ''));
                if (!$questionText) continue;

                if (Question::where('activity_id', $acitivity->id)->where('question', $questionText)->exists()) {
                    $existing = Question::where('activity_id', $acitivity->id)->where('question', $questionText)->first();
                    if ($existing) {
                        $seqVal = $col($row, 'sr._number') ?? $col($row, 'sr.number') ?? $row[0] ?? null;
                        if ($seqVal) $sequenceToQuestionId[(int)$seqVal] = $existing->id;
                        $questionTextToId[$questionText] = $existing->id;
                    }
                    continue;
                }

                $questionType = trim((string)($col($row, 'type') ?? 'Free Text'));
                $answerType   = strtolower(trim((string)($col($row, 'required') ?? 'Required'))) === 'optional' ? 0 : 1;

                $parentGroupText = trim((string)($col($row, 'parent_group_question') ?? ''));
                $dependsOnText   = trim((string)($col($row, 'depends_on_question')   ?? ''));

                // is_parent = 0 for sub-questions (parent_group_question set)
                // and conditional children (depends_on_question set)
                $isParent = (!empty($dependsOnText) || !empty($parentGroupText)) ? 0 : 1;

                // Multiple Answers Allowed — Multi select ONLY
                $multiSelectAllowed = ($questionType === 'Multi select'
                    && strtolower(trim((string)($col($row, 'multiple_answers_allowed') ?? ''))) === 'yes') ? 1 : 0;

                // Allow Multiple Images — Image ONLY
                $allowMultipleImages = ($questionType === 'Image'
                    && strtolower(trim((string)($col($row, 'allow_multiple_images') ?? ''))) === 'yes') ? 1 : 0;

                $optionsRaw      = trim((string)($col($row, 'options')           ?? ''));
                $helpText        = trim((string)($col($row, 'help_text')         ?? '')) ?: null;
                $validationRule  = trim((string)($col($row, 'validation_rule')   ?? '')) ?: 'none';
                $validationMin   = trim((string)($col($row, 'validation_min')    ?? '')) ?: null;
                $validationMax   = trim((string)($col($row, 'validation_max')    ?? '')) ?: null;
                $validationRegex = trim((string)($col($row, 'validation_regex')  ?? '')) ?: null;
                $fileTypes       = trim((string)($col($row, 'file_types')        ?? '')) ?: null;
                $rawMb           = $col($row, 'max_file_size_mb');
                $maxFileSizeMb   = is_numeric($rawMb) ? (float)$rawMb : null;
                $dateMin         = trim((string)($col($row, 'date_min')          ?? '')) ?: null;
                $dateMax         = trim((string)($col($row, 'date_max')          ?? '')) ?: null;

                $new_question = Question::create([
                    'activity_id'           => $acitivity->id,
                    'question'              => $questionText,
                    'question_type'         => $questionType,
                    // Only parent/standalone questions get a sequence number.
                    // Child questions (sub-questions or conditional children) have no sequence.
                    'question_sequence'     => $isParent ? $sequence_counter : null,
                    'answer_type'           => $answerType,
                    'is_parent'             => $isParent,
                    'allow_multiple_images' => $allowMultipleImages,
                    'help_text'             => $helpText,
                    'validation_rule'       => $validationRule,
                    'validation_min'        => $validationMin,
                    'validation_max'        => $validationMax,
                    'validation_regex'      => $validationRegex,
                    'file_types'            => $fileTypes,
                    'max_file_size_mb'      => $maxFileSizeMb,
                    'date_min'              => $dateMin,
                    'date_max'              => $dateMax,
                ]);

                $seqVal = $col($row, 'sr._number') ?? $col($row, 'sr.number') ?? $row[0] ?? null;
                if ($seqVal) $sequenceToQuestionId[(int)$seqVal] = $new_question->id;
                $questionTextToId[$questionText] = $new_question->id;

                // Create dropdown/multiselect options
                if ($optionsRaw && in_array($questionType, ['Dropdown', 'Multi select'])) {
                    foreach (explode('|', $optionsRaw) as $opt) {
                        $opt = trim($opt);
                        if ($opt) {
                            \App\Models\QuestionDropdown::create([
                                'question_id' => $new_question->id,
                                'option'      => $opt,
                            ]);
                        }
                    }
                }

                // Old-style conditional child linking (when parent already created in this pass)
                $dependsOnAnswer = trim((string)($col($row, 'depends_on_answer') ?? ''));
                if ($dependsOnText && isset($questionTextToId[$dependsOnText])) {
                    $new_question->update([
                        'parent_question_id' => $questionTextToId[$dependsOnText],
                        'parent_value'       => $dependsOnAnswer ?: null,
                    ]);
                }

                // Only parent questions consume a sequence number
                if ($isParent) $sequence_counter++;
            }

            // ── PASS 2 & 3: Use full DB map (safe for any row order) ────────────────
            $allActivityQuestions = Question::where('activity_id', $acitivity->id)
                ->pluck('id', 'question')
                ->toArray();

            // PASS 2 — sub-question links via parent_group_question
            foreach ($dataArray as $row) {
                $questionText    = trim((string)($col($row, 'question_text') ?? ''));
                $parentGroupText = trim((string)($col($row, 'parent_group_question') ?? ''));
                if (!$questionText || !$parentGroupText) continue;

                $childId  = $allActivityQuestions[$questionText]    ?? null;
                $parentId = $allActivityQuestions[$parentGroupText] ?? null;
                if (!$childId || !$parentId || $childId === $parentId) continue;

                $exists = QuestionSubQuestion::where('parent_question_id', $parentId)
                    ->where('child_question_id', $childId)->exists();
                if (!$exists) {
                    $nextSeq = (int) QuestionSubQuestion::where('parent_question_id', $parentId)->max('sequence') + 1;
                    QuestionSubQuestion::create([
                        'parent_question_id' => $parentId,
                        'child_question_id'  => $childId,
                        'sequence'           => $nextSeq,
                    ]);
                }
            }

            // PASS 3 — depends_on_question links (conditional child of Dropdown / Yes-No parent)
            // Stores ONLY in parent_question_id + parent_value on the question.
            // Does NOT create question_sub_questions entries — that is for parent_group_question only.
            foreach ($dataArray as $row) {
                $questionText    = trim((string)($col($row, 'question_text')       ?? ''));
                $dependsOnText   = trim((string)($col($row, 'depends_on_question') ?? ''));
                $dependsOnAnswer = trim((string)($col($row, 'depends_on_answer')   ?? ''));
                if (!$questionText || !$dependsOnText) continue;

                $childId  = $allActivityQuestions[$questionText]  ?? null;
                $parentId = $allActivityQuestions[$dependsOnText] ?? null;
                if (!$childId || !$parentId) continue;

                // Normalize depends_on_answer:
                // blank / "non_empty" / "is_answered" / "any" → '__non_empty__' sentinel
                // anything else → use as-is (specific value match for Yes/No, Dropdown)
                $normalizedAnswer = $dependsOnAnswer;
                if (in_array(strtolower($dependsOnAnswer), ['', 'non_empty', 'is_answered', 'any', 'not_empty', '__non_empty__'])) {
                    $normalizedAnswer = '__non_empty__';
                }

                Question::where('id', $childId)->update([
                    'parent_question_id' => $parentId,
                    'parent_value'       => $normalizedAnswer ?: null,
                    'is_parent'          => 0,
                ]);
            }
        }

        // Manual mode (new flow): just created the activity, redirect to questions page
        // The user will add questions using the fully-featured activity questions panel
        if (!$request->hasFile('questions_excel') && $request->input('manual_mode') == '1') {
            return redirect()->route('activities.question', $acitivity->id)
                ->with('message', 'Activity created! Now add your questions below.');
        }

        // Legacy manual mode: process inline bulk questions if provided
        if (!$request->hasFile('questions_excel') && $request->has('questions')) {
            $manualRows = $request->input('questions', []);
            $manualSeq  = Question::where('activity_id', $acitivity->id)->max('question_sequence') + 1;
            $indexToId  = [];

            foreach ($manualRows as $formIdx => $row) {
                $text = trim($row['question'] ?? '');
                if (empty($text)) continue;

                $qtype         = $row['question_type'] ?? 'Free Text';
                $required      = (int)($row['answer_type'] ?? 0);
                $parentFormIdx  = isset($row['parent_group_id']) && $row['parent_group_id'] !== '' ? $row['parent_group_id'] : null;
                $condParentIdx  = isset($row['cond_parent_idx']) && $row['cond_parent_idx'] !== '' ? $row['cond_parent_idx'] : null;
                $condTrigger    = $row['cond_trigger'] ?? null;
                $isParentQ      = ($parentFormIdx === null && $condParentIdx === null) ? 1 : 0;
                $allowMultiImg  = ($qtype === 'Image' && !empty($row['allow_multiple_images'])) ? 1 : 0;

                $q = Question::create([
                    'activity_id'           => $acitivity->id,
                    'question'              => $text,
                    'question_type'         => $qtype,
                    'question_sequence'     => $isParentQ ? $manualSeq++ : null,
                    'answer_type'           => $required,
                    'is_parent'             => $isParentQ,
                    'help_text'             => $row['help_text'] ?? null,
                    'allow_multiple_images' => $allowMultiImg,
                    'parent_question_id'    => null, // resolved in second pass
                    'parent_value'          => $condTrigger ?: null,
                    'validation_rule'       => ($row['validation_rule'] ?? 'none') ?: 'none',
                    'validation_min'        => $row['validation_min'] ?? null,
                    'validation_max'        => $row['validation_max'] ?? null,
                    'validation_regex'      => $row['validation_regex'] ?? null,
                ]);

                $indexToId[$formIdx] = [
                    'id'             => $q->id,
                    'parent_form_idx'=> $parentFormIdx,
                    'cond_parent_idx'=> $condParentIdx,
                    'cond_trigger'   => $condTrigger,
                    'qtype'          => $qtype,
                    'options'        => $row['options'] ?? '',
                ];
            }

            // Second pass: options + sub-question links
            foreach ($indexToId as $info) {
                if (!empty($info['options']) && in_array($info['qtype'], ['Dropdown', 'Multi select'])) {
                    foreach (array_filter(array_map('trim', explode('|', $info['options']))) as $opt) {
                        \App\Models\QuestionDropdown::create(['question_id' => $info['id'], 'option' => $opt]);
                    }
                }
                // MR sub-question link
                if ($info['parent_form_idx'] !== null && isset($indexToId[$info['parent_form_idx']])) {
                    $parentQId = $indexToId[$info['parent_form_idx']]['id'];
                    $nextSeq   = \App\Models\QuestionSubQuestion::where('parent_question_id', $parentQId)->max('sequence') + 1;
                    \App\Models\QuestionSubQuestion::create([
                        'parent_question_id' => $parentQId,
                        'child_question_id'  => $info['id'],
                        'sequence'           => $nextSeq,
                    ]);
                }
                // Conditional link (Yes/No or Dropdown parent)
                if (!empty($info['cond_parent_idx']) && isset($indexToId[$info['cond_parent_idx']])) {
                    $condParentQId = $indexToId[$info['cond_parent_idx']]['id'];
                    Question::where('id', $info['id'])->update([
                        'parent_question_id' => $condParentQId,
                        'parent_value'       => $info['cond_trigger'] ?: null,
                        'is_parent'          => 0,
                    ]);
                }
            }
        }

        return redirect(route('activities.question', $acitivity->id))
            ->with('message', "Activity created successfully.");
    }


    public function view_question($activity_id, \Illuminate\Http\Request $request)
    {
        $activity = Activity::with('questions.getOptions')->findOrFail($activity_id);

        $search  = trim($request->get('q', ''));
        $sortBy  = in_array($request->get('sort'), ['question', 'question_type', 'question_sequence']) ? $request->get('sort') : 'question_sequence';
        $sortDir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 10;

        // Paginate only parent/standalone questions; sub-questions are shown inline.
        // IMPORTANT: search uses a nested closure so activity_id constraint is never broken
        // by orWhere (without closure, orWhere removes preceding WHERE conditions).
        $parentQuery = Question::where('activity_id', $activity_id)
            ->where('is_parent', 1)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('question', 'like', "%{$search}%")
                        ->orWhere('question_type', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $parentQuestions = $parentQuery->paginate($perPage)->withQueryString();

        // Load ALL sub-question data for the parents on this page only
        $parentIds   = $parentQuestions->pluck('id')->toArray();
        $subLinks    = \App\Models\QuestionSubQuestion::whereIn('parent_question_id', $parentIds)
                            ->orderBy('sequence')->get()->groupBy('parent_question_id');
        $allChildren = Question::where('activity_id', $activity_id)->where('is_parent', 0)->get()->keyBy('id');

        return view('masters.activities.activity_questions', compact(
            'activity', 'parentQuestions', 'subLinks', 'allChildren',
            'search', 'sortBy', 'sortDir', 'perPage'
        ));
    }

    public function bulkAddQuestions($activity_id)
    {
        $activity = Activity::findOrFail($activity_id);
        return view('masters.activities.bulk_add_questions', compact('activity'));
    }

    public function bulkStoreQuestions(Request $request, $activity_id)
    {
        $activity  = Activity::findOrFail($activity_id);
        $rows      = $request->input('questions', []);
        $sequence  = Question::where('activity_id', $activity_id)->max('question_sequence') + 1;
        $created   = 0;

        // First pass: create all questions and build a map of form-index → Question ID
        // so that parent_group_id (a form row index) can be resolved to real DB IDs.
        $indexToId = []; // formRowIndex => question.id

        foreach ($rows as $formIdx => $row) {
            $text = trim($row['question'] ?? '');
            if (empty($text)) continue;

            $qtype         = $row['question_type']       ?? 'Free Text';
            $required      = (int)($row['answer_type']   ?? 0);
            $parentFormIdx = $row['parent_group_id'] !== '' ? $row['parent_group_id'] : null;
            $isParent      = ($parentFormIdx === null) ? 1 : 0;
            $allowMultiImg = ($qtype === 'Image' && !empty($row['allow_multiple_images'])) ? 1 : 0;

            $q = Question::create([
                'activity_id'           => $activity_id,
                'question'              => $text,
                'question_type'         => $qtype,
                'question_sequence'     => $isParent ? $sequence++ : null,
                'answer_type'           => $required,
                'is_parent'             => $isParent,
                'help_text'             => $row['help_text'] ?? null,
                'allow_multiple_images' => $allowMultiImg,
                'parent_question_id'    => null,
            ]);

            $indexToId[$formIdx] = ['id' => $q->id, 'parent_form_idx' => $parentFormIdx, 'qtype' => $qtype, 'options' => $row['options'] ?? ''];
            $created++;
        }

        // Second pass: create options and sub-question links now that all IDs are known
        foreach ($indexToId as $formIdx => $info) {
            $qId   = $info['id'];
            $qtype = $info['qtype'];

            // Options
            if (!empty($info['options']) && in_array($qtype, ['Dropdown', 'Multi select'])) {
                foreach (array_filter(array_map('trim', explode('|', $info['options']))) as $opt) {
                    \App\Models\QuestionDropdown::create(['question_id' => $qId, 'option' => $opt]);
                }
            }

            // Parent link
            $parentFormIdx = $info['parent_form_idx'];
            if ($parentFormIdx !== null && isset($indexToId[$parentFormIdx])) {
                $parentQId = $indexToId[$parentFormIdx]['id'];
                $nextSeq   = \App\Models\QuestionSubQuestion::where('parent_question_id', $parentQId)->max('sequence') + 1;
                \App\Models\QuestionSubQuestion::create([
                    'parent_question_id' => $parentQId,
                    'child_question_id'  => $qId,
                    'sequence'           => $nextSeq,
                ]);
            }
        }

        return redirect()->route('activities.question', $activity_id)
            ->with('message', "$created question(s) created successfully.");
    }

    public function edit($id)
    {
        $activity = Activity::findOrFail($id);
        return view('masters.activities.edit', compact('activity'));
    }
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'activity_name' => ['required'],
        ]);
        $activity = Activity::findOrFail($id);
        $activity->update($validated);
        return redirect(route('activities.list'))->with('message', 'Activity Updated');
    }
    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:activities,id',
        ]);
        $activity = Activity::find($request->id);
        if ($activity) {
            // Delete the activity, which will cascade delete related questions and their dropdowns
            $activity->delete();

            return "Success";
            return response()->json(['message' => 'Activity and related questions deleted successfully.'], 200);
        } else {
            return response()->json(['message' => 'Activity not found.'], 404);
        }
    }

    public function get_activity_info(Request $request)
    {
        $activity_info = Activity::findOrFail($request->id);
        return response()->json(['info' => $activity_info, 'message' => "success"]);
    }

    public function dropdown_options($id)
    {
        // dd($id);
        $question_info = Question::findOrFail($id);
        return view('masters.activities.activity_question_options', compact('question_info'));
    }

    public function destroy_question_option(Request $request)
    {
        try {
            $question_option = QuestionDropdown::findOrFail($request->id);
            $question_option->delete();
            return "Success";
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response("Question option not found", 404);
        } catch (\Exception $e) {
            return response("An error occurred", 500);
        }
    }

    //khushboo 13-05-2025
    public function exportActivityData()
    {
        $activities = Activity::all();

        $data = $activities->map(function ($activity) {
            return [
                $activity->activity_name,
            ];
        });

        $headings = ['Activity Name'];

        return Excel::download(new DynamicTableExport($data, $headings), 'activities.xlsx');
    }
    //khushboo 13-05-2025


    public function subQuestions($question_id)
    {
        $question = Question::with(['subQuestions.childQuestion'])->findOrFail($question_id);
        $activity = Activity::findOrFail($question->activity_id);
        // Exclude questions already linked to THIS specific parent (raw FK values from pivot table).
        // Same child can be reused under different parents — no global exclusion.
        $linked_ids = \App\Models\QuestionSubQuestion::where('parent_question_id', $question->getKey())
            ->pluck('child_question_id')
            ->map(fn($id) => (int)$id)
            ->toArray();

        // Raw numeric PK of the parent so we can exclude it from its own selection
        $parentRawId = $question->getKey();

        // Show ALL non-Multi-Response questions except the parent itself and already-linked ones.
        // Include both standalone (is_parent=1) and child (is_parent=0) questions —
        // any question can be linked as a sub-question of a Multi Response parent.
        $available_questions = Question::where('activity_id', $question->activity_id)
            ->whereRaw('id != ?', [$parentRawId])
            ->where('question_type', '!=', 'Multi Response')
            ->whereNotIn('id', $linked_ids)
            ->orderBy('question_sequence')
            ->get();
        return view('masters.activities.activity_sub_questions', compact('question', 'activity', 'available_questions'));
    }

    public function storeSubQuestion(Request $request)
    {
        $request->validate([
            'parent_question_id' => 'required|exists:questions,id',
            'child_question_id'  => 'required|exists:questions,id|different:parent_question_id',
            'sequence'           => 'required|integer|min:1',
        ]);

        $exists = QuestionSubQuestion::where('parent_question_id', $request->parent_question_id)
            ->where('child_question_id', $request->child_question_id)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'This sub-question is already linked.');
        }

        QuestionSubQuestion::create([
            'parent_question_id' => $request->parent_question_id,
            'child_question_id'  => $request->child_question_id,
            'sequence'           => $request->sequence,
        ]);

        return redirect()->back()->with('message', 'Sub-question linked successfully.');
    }

    public function destroySubQuestion(Request $request)
    {
        try {
            $link = QuestionSubQuestion::findOrFail($request->id);
            $parentId = $link->parent_question_id;
            $link->delete();

            // Re-sequence remaining siblings starting from 1
            $remaining = QuestionSubQuestion::where('parent_question_id', $parentId)
                ->orderBy('sequence')
                ->orderBy('id')
                ->get();

            $newSequences = [];
            foreach ($remaining as $index => $sibling) {
                $newSeq = $index + 1;
                $sibling->update(['sequence' => $newSeq]);
                $newSequences[$sibling->id] = $newSeq;
            }

            return response()->json(['status' => 'Success', 'sequences' => $newSequences]);
        } catch (\Exception $e) {
            return response("Not found", 404);
        }
    }

    public function updateSubQuestionSequence(Request $request)
    {
        try {
            $link = QuestionSubQuestion::findOrFail($request->id);
            $newSeq = (int) $request->sequence;
            $parentId = $link->parent_question_id;

            // If another sibling already has this sequence, swap with it
            $conflict = QuestionSubQuestion::where('parent_question_id', $parentId)
                ->where('sequence', $newSeq)
                ->where('id', '!=', $link->id)
                ->first();

            if ($conflict) {
                // Swap: give the conflict the old sequence of the current link
                $conflict->update(['sequence' => $link->sequence]);
            }

            $link->update(['sequence' => $newSeq]);
            return response()->json(['status' => 'Success', 'swapped_id' => $conflict?->id, 'swapped_seq' => $conflict?->sequence]);
        } catch (\Exception $e) {
            return response("Not found", 404);
        }
    }

    //khushboo 06-03-2026
    public function getParentQuestionDropdown(Request $request)
    {
        try{
            $question_id = $request->id;
            $question = Question::find($question_id);
            $question_option = [];
            if($question->question_type == 'Dropdown'){
                $question_option = QuestionDropdown::where('question_id', $question_id)
                    ->select('id','option')
                    ->get()
                    ->toArray();
//                dd($question_option);
            }
            if($question->question_type == 'Yes / No'){
                $question_option[]['option'] = 'Yes';
                $question_option[]['option'] = 'No';
//                dd($question_option);
            }
            return response()->json(['status'=> true,'question_option' => $question_option]);
        }
        catch(\Exception $e){
            dd($e->getMessage());
            return response()->json(['status'=> false,'question_option' => $e->getMessage()]);
        }

    }
    //khushboo 06-03-2026

}
