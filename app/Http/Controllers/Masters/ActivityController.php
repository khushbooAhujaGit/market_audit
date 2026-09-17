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

    /**
     * Validates parent/child references across the WHOLE uploaded sheet before any
     * row gets stored. Catches two specific mistakes that previously imported
     * silently as broken/misattached data:
     *  1. parent_group_question (Multi Response sub-question) or
     *     depends_on_question (conditional child) pointing at a question of the
     *     wrong type — e.g. bound to a "Multi select" question instead of an
     *     actual "Multi Response" one, or a conditional child depending on
     *     something other than a "Yes / No" / "Dropdown" question.
     *  2. Either of those columns referencing question text that doesn't match
     *     any question actually present in this file (typo, or the intended
     *     parent was simply never added to the sheet).
     * Returns an array of human-readable error strings; empty array = no errors.
     */
    private function validateExcelQuestionRows(array $dataArray, \Closure $col): array
    {
        $errors = [];

        // Build question_text => type for every row in the file (first occurrence
        // wins — matches how duplicate detection elsewhere in this import already
        // treats question_text as the natural key).
        $textToType = [];
        foreach ($dataArray as $row) {
            $text = trim((string)($col($row, 'question_text') ?? ''));
            $type = trim((string)($col($row, 'type') ?? ''));
            if ($text !== '' && !isset($textToType[$text])) {
                $textToType[$text] = $type;
            }
        }

        $dataRowNumber = 0;
        foreach ($dataArray as $row) {
            $text = trim((string)($col($row, 'question_text') ?? ''));
            if ($text === '') continue; // blank/spacer rows — skipped the same way the import itself skips them
            $dataRowNumber++;

            $rowLabel = "Data row {$dataRowNumber} (\"{$text}\")";

            $parentGroupText = trim((string)($col($row, 'parent_group_question') ?? ''));
            $dependsOnQ      = trim((string)($col($row, 'depends_on_question')   ?? ''));
            $dependsOnA      = trim((string)($col($row, 'depends_on_answer')     ?? ''));

            if ($parentGroupText !== '') {
                if ($parentGroupText === $text) {
                    $errors[] = "{$rowLabel}: parent_group_question refers to itself.";
                } elseif (!isset($textToType[$parentGroupText])) {
                    $errors[] = "{$rowLabel}: parent_group_question \"{$parentGroupText}\" does not match any question in this file.";
                } elseif ($textToType[$parentGroupText] !== 'Multi Response') {
                    $errors[] = "{$rowLabel}: parent_group_question \"{$parentGroupText}\" must be a \"Multi Response\" question, but it is type \"{$textToType[$parentGroupText]}\".";
                }
            }

            if ($dependsOnQ !== '') {
                if ($dependsOnQ === $text) {
                    $errors[] = "{$rowLabel}: depends_on_question refers to itself.";
                } elseif (!isset($textToType[$dependsOnQ])) {
                    $errors[] = "{$rowLabel}: depends_on_question \"{$dependsOnQ}\" does not match any question in this file.";
                } elseif (!in_array($textToType[$dependsOnQ], ['Yes / No', 'Dropdown'])) {
                    $errors[] = "{$rowLabel}: depends_on_question \"{$dependsOnQ}\" must be a \"Yes / No\" or \"Dropdown\" question, but it is type \"{$textToType[$dependsOnQ]}\".";
                }
            } elseif ($dependsOnA !== '') {
                $errors[] = "{$rowLabel}: depends_on_answer (\"{$dependsOnA}\") is set but depends_on_question is empty.";
            }
        }

        return $errors;
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

        $dataArray = null;
        $col       = null;

        // Check if file is present in the request
        if ($request->hasFile('questions_excel')) {
            $file = $request->file('questions_excel');

            // Use PhpSpreadsheet directly with formula evaluation enabled.
            // Excel::toCollection() returns formula strings (e.g. "=B4") instead of computed
            // values, causing parent_group_question references to break. toArray(null,true)
            // forces formula calculation so cell-reference-based parent_group_question values
            // resolve to the actual question text.
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet   = $spreadsheet->getActiveSheet();
            // calculateFormulas=true, formatData=false, returnCellRef=false
            $rawRows = $worksheet->toArray(null, true, false, false);
            // Convert to numeric-indexed array of arrays (same shape as toCollection)
            $dataArray = array_values($rawRows);

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

            // ── Validate the WHOLE sheet's parent/child references before storing
            // anything — catches a parent_group_question bound to the wrong question
            // type (e.g. "Multi select" instead of "Multi Response"), and any
            // parent_group_question/depends_on_question that doesn't match any
            // question actually present in this file. Rejects the whole upload (no
            // activity or questions get created) if anything is wrong, rather than
            // silently importing broken/misattached sub-questions.
            $validationErrors = $this->validateExcelQuestionRows($dataArray, $col);
            if (!empty($validationErrors)) {
                return response()->json([
                    'message' => 'The uploaded Excel file has errors. Please fix them and re-upload.',
                    'errors'  => $validationErrors,
                ], 422);
            }
        }

        $acitivity = Activity::create([
            'activity_name' => $request->activity_name
        ]);
        $sequence_counter = 1;

        if ($dataArray !== null) {
            $sequenceToQuestionId = [];
            $questionTextToId     = [];

            // ── PRE-SCAN: Build a map of row-index → parent_group_question for ALL rows.
            // We use the row INDEX (not Sr.Number) as the key because sub-question rows
            // often use Excel formulas like =A3+1 for their Sr.Number. When the file is
            // read without formula evaluation those cells return the formula string, which
            // PHP casts to 0 — causing every sub-question to collide on key 0.
            // Using the row index avoids this entirely.
            $rowToParentGroup  = []; // [rowIndex => parent_group_question_text]
            $rowToDepQuestion  = []; // [rowIndex => depends_on_question_text]
            $rowToDepAnswer    = []; // [rowIndex => depends_on_answer]
            foreach ($dataArray as $rowIdx => $row) {
                $pgText  = trim((string)($col($row, 'parent_group_question') ?? ''));
                $dqText  = trim((string)($col($row, 'depends_on_question')   ?? ''));
                $daText  = trim((string)($col($row, 'depends_on_answer')     ?? ''));
                if ($pgText)  $rowToParentGroup[$rowIdx] = $pgText;
                if ($dqText)  $rowToDepQuestion[$rowIdx] = $dqText;
                if ($daText)  $rowToDepAnswer[$rowIdx]   = $daText;
            }

            // ── PASS 1: Create all questions ──────────────────────────────────
            foreach ($dataArray as $rowIdx => $row) {
                if (empty($row[0]) && empty($row[$colMap['question_text'] ?? 1] ?? null)) continue;

                $questionText = trim((string)($col($row, 'question_text') ?? ''));
                if (!$questionText) continue;

                // Read type early — needed for duplicate resolution logic below
                $questionType = trim((string)($col($row, 'type') ?? 'Free Text'));

                $parentGroupTextCheck = trim((string)($col($row, 'parent_group_question') ?? ''));

                // Duplicate check for standalone questions (no parent_group_question):
                // Same text + same type → duplicate, skip and reuse existing.
                // Same text + different type → different question, allow creation.
                if (empty($parentGroupTextCheck)) {
                    $existingQ = Question::where('activity_id', $acitivity->id)
                        ->where('question', $questionText)
                        ->where('question_type', $questionType)
                        ->where('is_parent', 1)
                        ->first();

                    if ($existingQ) {
                        $seqVal = $col($row, 'sr._number') ?? $col($row, 'sr.number') ?? $row[0] ?? null;
                        if ($seqVal) $sequenceToQuestionId[(int)$seqVal] = $existingQ->id;
                        $questionTextToId[$questionText] = $existingQ->id;
                        continue;
                    }
                }
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
                $dateMin         = $this->excelDateToString($col($row, 'date_min'));
                $dateMax         = $this->excelDateToString($col($row, 'date_max'));

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

                // Row index is always unique — no collision from formula-based or reused Sr.Numbers.
                // Never write the Sr.Number as an additional key: plain Sr.Numbers (e.g. "2") would
                // overwrite an earlier rowIdx entry that happens to share that integer.
                $sequenceToQuestionId[$rowIdx] = $new_question->id;
                $questionTextToId[$questionText] = $new_question->id;

                // Create dropdown/multiselect options — also reused for Barcode/QR Code/RFID
                // questions, where each "option" is an EXPECTED HEADER NAME the scanned
                // code should contain (compared against the actual scan at answer time),
                // not a selectable choice.
                if ($optionsRaw && in_array($questionType, ['Dropdown', 'Multi select', 'Barcode', 'QR Code', 'RFID'])) {
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

            // ── PASS 2 & 3: Use full DB maps (safe for any row order) ───────────────
            // Group by question text to correctly resolve duplicates (prefer is_parent=1)
            $allQByTextAll = Question::where('activity_id', $acitivity->id)
                ->get()
                ->groupBy('question'); // text → collection

            // Helper: given question text, return the best-matching parent ID.
            // Prefers questions with is_parent=1; falls back to any match.
            // This handles both top-level MR parents AND nested MR sub-questions as parents.
            $resolveParentId = function(string $text) use ($allQByTextAll): ?int {
                $candidates = $allQByTextAll[$text] ?? collect();
                $q = $candidates->firstWhere('is_parent', 1) ?? $candidates->first();
                return $q?->id ?? null;
            };

            // PASS 2 — sub-question links via parent_group_question.
            // Uses $rowToParentGroup (row-index → parent text) — immune to formula Sr.Numbers.
            // Works for ANY row order: child can appear before OR after its parent.
            // Supports nested MR: a sub-question can itself be an MR parent of deeper sub-questions.
            foreach ($rowToParentGroup as $rowIdx => $parentGroupText) {
                if (!isset($sequenceToQuestionId[$rowIdx])) continue; // child not created
                $childId  = $sequenceToQuestionId[$rowIdx];
                $parentId = $resolveParentId($parentGroupText);
                if (!$parentId || $childId === $parentId) continue;

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

            // PASS 3 — depends_on_question links (conditional children).
            // Uses $rowToDepQuestion (row-index → parent text) — immune to formula Sr.Numbers.
            // The child can appear on ANY row — before or after its parent question.
            // Reuses $allQByTextAll (built above) — no extra DB query.
            foreach ($rowToDepQuestion as $rowIdx => $dependsOnText) {
                if (!isset($sequenceToQuestionId[$rowIdx])) continue;
                $childId   = $sequenceToQuestionId[$rowIdx];
                $depAnswer = $rowToDepAnswer[$rowIdx] ?? '';

                // Find the parent: prefer is_parent=1 question with that text;
                // fall back to any question with that text (covers sub-question parents too).
                $parentCandidates = $allQByTextAll[$dependsOnText] ?? collect();
                $parentQ = $parentCandidates->firstWhere('is_parent', 1)
                        ?? $parentCandidates->first();
                $parentId = $parentQ?->id ?? null;

                if (!$childId || !$parentId) continue;

                // Normalize depends_on_answer: blank/"is_answered"/etc → __non_empty__ sentinel
                $normalizedAnswer = $depAnswer;
                if (in_array(strtolower($depAnswer), ['', 'non_empty', 'is_answered', 'any', 'not_empty', '__non_empty__'])) {
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
                if (!empty($info['options']) && in_array($info['qtype'], ['Dropdown', 'Multi select', 'Barcode', 'QR Code', 'RFID'])) {
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

        // Load ALL questions for this activity (needed for nested sub-question resolution)
        $allQuestions = Question::where('activity_id', $activity_id)->get()->keyBy('id');

        // Load ALL QuestionSubQuestion links for the activity (supports nested MR hierarchies)
        $allQuestionIds = $allQuestions->keys()->toArray();
        $allSubLinks    = \App\Models\QuestionSubQuestion::whereIn('parent_question_id', $allQuestionIds)
                            ->orderBy('sequence')->get()->groupBy('parent_question_id');

        // BC aliases so the view's existing references still work
        $subLinks    = $allSubLinks;
        $allChildren = $allQuestions;

        return view('masters.activities.activity_questions', compact(
            'activity', 'parentQuestions', 'subLinks', 'allSubLinks', 'allChildren', 'allQuestions',
            'search', 'sortBy', 'sortDir', 'perPage'
        ));
    }

    public function reorderQuestions($activity_id, \Illuminate\Http\Request $request)
    {
        $ids = $request->input('ids', []);
        foreach ($ids as $index => $id) {
            Question::where('id', $id)
                ->where('activity_id', $activity_id)
                ->update(['question_sequence' => $index + 1]);
        }
        return response()->json(['status' => 'success']);
    }

    public function bulkAddQuestions($activity_id)
    {
        $activity = Activity::findOrFail($activity_id);
        return view('masters.activities.bulk_add_questions', compact('activity'));
    }

    /**
     * Export the current activity questions as an Excel file in the same
     * format as the bulk-import template so users can edit and re-upload.
     */
    public function exportActivityQuestions($activity_id)
    {
        $activity = Activity::findOrFail($activity_id);

        // Load all questions for this activity with their options and sub-question links.
        $allQuestions = Question::where('activity_id', $activity_id)
            ->with(['getOptions', 'subQuestions.childQuestion'])
            ->orderBy('question_sequence')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // Build ordered flat list of rows the same way the import expects them:
        //   parent → its MR sub-questions → conditional children of each
        $rows     = [];
        $visited  = [];

        // Recursive helper: appends a question and all its descendants to $rows.
        $flatten = null;
        $flatten = function (Question $q, ?string $mrParentText, ?string $condParentText, ?string $condTrigger) use (
            &$rows, &$visited, &$flatten, $allQuestions
        ) {
            // Only deduplicate top-level parents; sub-questions are shared across
            // multiple MR parents and must be exported once per parent.
            $isTopLevel = ($mrParentText === null && $condParentText === null);
            if ($isTopLevel) {
                if (isset($visited[$q->id])) return;
                $visited[$q->id] = true;
            }

            // Options (pipe-separated)
            $options = $q->getOptions->pluck('option')->implode('|');

            // parent_group_question — text of MR parent if this is a sub-question
            $parentGroupQ = $mrParentText;

            // depends_on_question / depends_on_answer — for conditional children
            $dependsOnQ   = $condParentText;
            $dependsOnA   = $condTrigger;

            // answer_type
            $required = $q->answer_type == 1 ? 'Required' : 'Optional';

            // Multiple Answers Allowed — only meaningful for Multi select
            $multipleAnswers = ($q->question_type === 'Multi select') ? 'Yes' : null;

            // Allow Multiple Images
            $multipleImages = ($q->question_type === 'Image' && $q->allow_multiple_images) ? 'Yes' : null;

            // Validation rule — blank if 'none'
            $valRule = ($q->validation_rule && $q->validation_rule !== 'none') ? $q->validation_rule : null;

            $rows[] = [
                'question_id'             => $q->id,
                'question_text'           => $q->question,
                'type'                    => $q->question_type,
                'required'                => $required,
                'help_text'               => $q->help_text,
                'options'                 => $options ?: null,
                'multiple_answers_allowed'=> $multipleAnswers,
                'allow_multiple_images'   => $multipleImages,
                'validation_rule'         => $valRule,
                'validation_min'          => $q->validation_min,
                'validation_max'          => $q->validation_max,
                'validation_regex'        => $q->validation_regex,
                'file_types'              => $q->file_types,
                'max_file_size_mb'        => $q->max_file_size_mb,
                'date_min'                => $q->date_min,
                'date_max'                => $q->date_max,
                'parent_group_question'   => $parentGroupQ,
                'depends_on_question'     => $dependsOnQ,
                'depends_on_answer'       => $dependsOnA,
            ];

            // MR sub-questions (via QuestionSubQuestion pivot)
            foreach ($q->subQuestions->sortBy('sequence') as $link) {
                $child = $link->childQuestion ?? $allQuestions[$link->child_question_id] ?? null;
                if (!$child) continue;
                $flatten($child, $q->question, null, null);

                // Conditional children of this sub-question
                $condChildren = Question::where('parent_question_id', $child->id)
                    ->with('getOptions')
                    ->orderBy('question_sequence')
                    ->get();
                foreach ($condChildren as $cc) {
                    $triggerVal = ($cc->parent_value === '__non_empty__' || !$cc->parent_value)
                        ? 'is_answered'
                        : $cc->parent_value;
                    $flatten($cc, null, $child->question, $triggerVal);
                }
            }

            // Conditional children of this top-level question
            if (!$mrParentText && !$condParentText) {
                $condChildren = Question::where('parent_question_id', $q->id)
                    ->with('getOptions')
                    ->orderBy('question_sequence')
                    ->get();
                foreach ($condChildren as $cc) {
                    $triggerVal = ($cc->parent_value === '__non_empty__' || !$cc->parent_value)
                        ? 'is_answered'
                        : $cc->parent_value;
                    $flatten($cc, null, $q->question, $triggerVal);
                }
            }
        };

        // Process top-level parent questions in sequence order
        $parents = $allQuestions->filter(fn($q) => $q->is_parent == 1)->sortBy('question_sequence');
        foreach ($parents as $parent) {
            $flatten($parent, null, null, null);
        }

        // Build the spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Questions');

        // ── Header row 1: column names (no question_id — stored in hidden sheet) ──
        $headers = [
            'Sr. Number', 'question_text', 'type', 'required', 'help_text', 'options',
            'Multiple Answers Allowed', 'Allow Multiple Images', 'validation_rule',
            'validation_min', 'validation_max', 'validation_regex', 'file_types',
            'max_file_size_mb', 'date_min', 'date_max',
            'parent_group_question', 'depends_on_question', 'depends_on_answer',
        ];

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF1F3864']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'wrapText'   => true],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                             'color' => ['argb' => 'FFAAAAAA']]],
        ];

        $descStyle = [
            'font' => ['italic' => true, 'color' => ['argb' => 'FF555555'], 'size' => 9],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'startColor' => ['argb' => 'FFEEF2FF']],
            'alignment' => ['wrapText' => true, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP],
        ];

        // Row 1 headers
        foreach ($headers as $col => $hdr) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($hdr);
            $sheet->getStyleByColumnAndRow($col + 1, 1)->applyFromArray($headerStyle);
        }
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Row 2: descriptions
        $descriptions = [
            'Sequential row number (1, 2, 3 ...)',
            'Full question text shown to the auditor  REQUIRED',
            'Question type — pick from dropdown  REQUIRED',
            'Mandatory?  Required  or  Optional  REQUIRED',
            'Hint / instruction text shown below the question (optional)',
            'Dropdown / Multi select: pipe-separated choices  e.g.  Option A|Option B|Option C',
            'Multi select only — Yes = auditor can choose multiple options; No or blank = single choice',
            'Image only — Yes = auditor can upload multiple images (shows + button); No or blank = single image',
            'Validation rule — pick from dropdown  (default: none)',
            'Min value / char-count / digit-count for the selected rule',
            'Max value / char-count / digit-count for the selected rule',
            'Regex pattern — only used when validation_rule = regex',
            'Image / File Upload: accepted extensions  e.g.  .jpg,.png,.pdf',
            'Image / File Upload: max file size in MB (number only)  e.g.  10',
            'Date / Date & Time: earliest date allowed  YYYY-MM-DD',
            'Date / Date & Time: latest date allowed  YYYY-MM-DD',
            'Sub-question: paste exact question_text of the parent Multi Response question',
            'Conditional: paste exact question_text of the PARENT question.',
            'Conditional trigger value:  "Yes" / "No" or dropdown option, or "is_answered" for any non-empty parent',
        ];
        foreach ($descriptions as $col => $desc) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 2);
            $cell->setValue($desc);
            $sheet->getStyleByColumnAndRow($col + 1, 2)->applyFromArray($descStyle);
        }
        $sheet->getRowDimension(2)->setRowHeight(50);

        // ── Data rows (starting at row 3) ──────────────────────────────────────
        $dataStyle = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                             'color' => ['argb' => 'FFDDDDDD']]],
        ];
        $subQStyle  = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                   'startColor' => ['argb' => 'FFF0F4FF']]];
        $condStyle  = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                   'startColor' => ['argb' => 'FFFFF8F0']]];

        foreach ($rows as $idx => $row) {
            $excelRow = $idx + 3;
            $sr       = $idx + 1;

            // Write all fields except question_id (which goes to the hidden sheet)
            $rowValues = [
                $sr,
                $row['question_text'],
                $row['type'],
                $row['required'],
                $row['help_text'],
                $row['options'],
                $row['multiple_answers_allowed'],
                $row['allow_multiple_images'],
                $row['validation_rule'],
                $row['validation_min'],
                $row['validation_max'],
                $row['validation_regex'],
                $row['file_types'],
                $row['max_file_size_mb'],
                $row['date_min'],
                $row['date_max'],
                $row['parent_group_question'],
                $row['depends_on_question'],
                $row['depends_on_answer'],
            ];

            foreach ($rowValues as $col => $val) {
                $sheet->getCellByColumnAndRow($col + 1, $excelRow)->setValue($val);
            }

            $sheet->getStyleByColumnAndRow(1, $excelRow, count($headers), $excelRow)
                ->applyFromArray($dataStyle);

            // Tint sub-question rows and conditional rows
            if (!empty($row['parent_group_question'])) {
                $sheet->getStyleByColumnAndRow(1, $excelRow, count($headers), $excelRow)
                    ->applyFromArray($subQStyle);
            } elseif (!empty($row['depends_on_question'])) {
                $sheet->getStyleByColumnAndRow(1, $excelRow, count($headers), $excelRow)
                    ->applyFromArray($condStyle);
            }
        }

        // ── Hidden sheet: activity_id lock + Sr. Number → question_id mapping ────
        $metaSheet = $spreadsheet->createSheet();
        $metaSheet->setTitle('_meta');
        $metaSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
        // Row 1: activity_id lock — import will reject if this doesn't match
        $metaSheet->setCellValue('A1', 'activity_id');
        $metaSheet->setCellValue('B1', (int) $activity_id);
        // Row 2: column headers for Sr → question_id map
        $metaSheet->setCellValue('A2', 'sr');
        $metaSheet->setCellValue('B2', 'question_id');
        foreach ($rows as $idx => $row) {
            $metaSheet->setCellValue('A' . ($idx + 3), $idx + 1);
            $metaSheet->setCellValue('B' . ($idx + 3), $row['question_id']);
        }

        // Column widths
        $colWidths = [8, 45, 18, 10, 25, 30, 12, 12, 18, 8, 8, 18, 18, 10, 12, 12, 40, 40, 20];
        foreach ($colWidths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        // Freeze top 2 rows
        $sheet->freezePane('A3');
        $spreadsheet->setActiveSheetIndex(0);

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $activity->activity_name) . '_questions.xlsx';
        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    public function importQuestionsFromExcel(Request $request, $activity_id)
    {
        $request->validate(['excel_file' => 'required|file|mimes:xlsx,xls']);

        $activity = Activity::findOrFail($activity_id);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('excel_file')->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        // Row 0 = header names, Row 1 = descriptions, data starts at Row 2
        if (count($rows) < 3) {
            return back()->with('error', 'The uploaded file has no question data.');
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $col     = array_flip($headers);

        // Read _meta sheet: validate activity_id lock and build Sr → question_id map
        $srToQuestionId = [];
        if ($spreadsheet->sheetNameExists('_meta')) {
            $metaRows = $spreadsheet->getSheetByName('_meta')->toArray(null, true, true, false);

            // Row 0: activity_id lock
            if (!empty($metaRows[0]) && strtolower(trim($metaRows[0][0] ?? '')) === 'activity_id') {
                $fileActivityId = (int)($metaRows[0][1] ?? 0);
                if ($fileActivityId && $fileActivityId !== (int) $activity_id) {
                    return back()->with('error',
                        'This template was downloaded for a different activity and cannot be uploaded here. Please use the correct template for this activity.');
                }
            }

            // Row 1: headers (sr / question_id), data from row 2 onward
            foreach (array_slice($metaRows, 2) as $mr) {
                $sr  = (int)($mr[0] ?? 0);
                $qid = (int)($mr[1] ?? 0);
                if ($sr && $qid) $srToQuestionId[$sr] = $qid;
            }
        }

        // Load existing questions keyed by ID and grouped by text
        // (grouped because multiple questions can share the same text e.g. "Remark")
        $existingById    = Question::where('activity_id', $activity_id)->get()->keyBy('id');
        $existingByText  = $existingById->groupBy(fn($q) => trim($q->question));
        $textMatchCursor = []; // tracks how many times each text has been matched (for round-robin)

        $sequence      = 1;
        $nameToId      = [];
        $textToFirstId = [];
        $subLinks      = []; // one entry per Excel row with parent_group_question (preserves shared sub-questions)
        $created = $updated = 0;

        // First pass: upsert questions — match by _meta sheet question_id first, then fall back to text.
        // Questions NOT in the file are left untouched (never deleted).
        for ($i = 2; $i < count($rows); $i++) {
            $row  = $rows[$i];
            $text = trim($row[$col['question_text'] ?? 1] ?? '');
            if (empty($text)) continue;

            $sr          = (int)($row[0] ?? ($i - 1));
            $qIdFromFile = $srToQuestionId[$sr] ?? 0;
            $qtype         = trim($row[$col['type']      ?? 2] ?? 'Free Text');
            $required      = strtolower(trim($row[$col['required'] ?? 3] ?? '')) === 'required' ? 1 : 0;
            $helpText      = trim($row[$col['help_text']  ?? 4] ?? '') ?: null;
            $options       = trim($row[$col['options']    ?? 5] ?? '') ?: null;
            $allowMultiImg  = strtolower(trim($row[$col['allow multiple images'] ?? 7] ?? '')) === 'yes' ? 1 : 0;
            $multipleAnswers = strtolower(trim($row[$col['multiple answers allowed'] ?? 6] ?? '')) === 'yes' ? 1 : 0;
            $valRule        = isset($col['validation_rule'])   ? (trim($row[$col['validation_rule']]   ?? '') ?: null) : null;
            $valMin         = isset($col['validation_min'])    ? (trim($row[$col['validation_min']]    ?? '') ?: null) : null;
            $valMax         = isset($col['validation_max'])    ? (trim($row[$col['validation_max']]    ?? '') ?: null) : null;
            $valRegex       = isset($col['validation_regex'])  ? (trim($row[$col['validation_regex']]  ?? '') ?: null) : null;
            $fileTypes      = isset($col['file_types'])        ? (trim($row[$col['file_types']]        ?? '') ?: null) : null;
            $maxFileSizeMb  = isset($col['max_file_size_mb'])  ? (trim($row[$col['max_file_size_mb']]  ?? '') ?: null) : null;
            $dateMin        = isset($col['date_min'])          ? $this->excelDateToString($row[$col['date_min']] ?? null) : null;
            $dateMax        = isset($col['date_max'])          ? $this->excelDateToString($row[$col['date_max']] ?? null) : null;

            $parentGroupQ = isset($col['parent_group_question'])
                ? (trim($row[$col['parent_group_question']] ?? '') ?: null) : null;
            $dependsOnQ   = isset($col['depends_on_question'])
                ? (trim($row[$col['depends_on_question']]  ?? '') ?: null) : null;
            $dependsOnA   = isset($col['depends_on_answer'])
                ? (trim($row[$col['depends_on_answer']]    ?? '') ?: null) : null;

            $isParent = ($parentGroupQ === null && $dependsOnQ === null) ? 1 : 0;

            $attrs = [
                'question_type'          => $qtype,
                'question_sequence'      => $isParent ? $sequence++ : null,
                'answer_type'            => $required,
                'is_parent'              => $isParent,
                'help_text'              => $helpText,
                'allow_multiple_images'  => $allowMultiImg,
                'validation_rule'        => $valRule,
                'validation_min'         => $valMin,
                'validation_max'         => $valMax,
                'validation_regex'       => $valRegex,
                'file_types'             => $fileTypes,
                'max_file_size_mb'       => $maxFileSizeMb,
                'date_min'               => $dateMin,
                'date_max'               => $dateMax,
                'parent_question_id'     => null,
            ];

            // Match by question_id from _meta, BUT verify the DB question text matches
            // the row text (or is close enough). If _meta is stale due to row deletions/
            // reordering in Excel, the Sr number shifts and we get a wrong question_id.
            $metaQId       = $qIdFromFile && isset($existingById[$qIdFromFile]) ? $qIdFromFile : 0;
            $metaTextMatch = $metaQId && (trim($existingById[$metaQId]->question) === $text);

            if ($metaTextMatch) {
                // _meta question_id confirmed — text matches exactly
                $q = $existingById[$metaQId];
                $q->update($attrs);
                $updated++;
            } elseif ($existingByText->has($text)) {
                // Text match — same text, possibly multiple questions with different types.
                // First try to find one with the same question_type as the Excel row (exact match).
                // If no type match, fall back to cursor order (round-robin) so that duplicate
                // same-text same-type questions are picked in sequence without cross-type collision.
                // When cursor exceeds group size, clamp to last() to avoid wrapping back to first().
                $group = $existingByText->get($text);
                $typeMatch = $group->first(fn($q) => $q->question_type === $qtype);
                if ($typeMatch) {
                    $q = $typeMatch;
                } else {
                    $cursor = $textMatchCursor[$text] ?? 0;
                    $q      = $cursor < $group->count()
                        ? $group->values()->get($cursor)
                        : $group->values()->last();
                    $textMatchCursor[$text] = $cursor + 1;
                }
                $q->update($attrs);
                $updated++;
            } elseif ($metaQId) {
                // _meta has a question_id but text differs — user renamed the question text.
                // Trust the question_id and update including the new text.
                $q = $existingById[$metaQId];
                $q->update(array_merge($attrs, ['question' => $text]));
                $updated++;
            } else {
                // New question not in DB
                $q = Question::create(array_merge($attrs, [
                    'activity_id' => $activity_id,
                    'question'    => $text,
                ]));
                $created++;
            }

            // Sync options: delete old, recreate from Excel
            if (in_array($qtype, ['Multi Response', 'Multi select', 'Single select', 'Dropdown', 'Barcode', 'QR Code', 'RFID'])) {
                \App\Models\QuestionDropdown::where('question_id', $q->id)->delete();
                if ($options) {
                    foreach (explode('|', $options) as $opt) {
                        $opt = trim($opt);
                        if ($opt !== '') {
                            \App\Models\QuestionDropdown::create(['question_id' => $q->id, 'option' => $opt]);
                        }
                    }
                }
            }

            // Key by question_id (not text) to avoid collisions on duplicate texts like "Remark"
            $nameToId[$q->id] = [
                'id'         => $q->id,
                'text'       => $text,
                'dependsOnQ' => $dependsOnQ,
                'dependsOnA' => $dependsOnA,
            ];
            // Collect per-row MR sub-question links (one entry per Excel row) so shared
            // sub-questions that appear under multiple MR parents all get their links preserved.
            if ($parentGroupQ) {
                $subLinks[] = ['childId' => $q->id, 'parentGroupQ' => $parentGroupQ];
            }
            // Also keep a text→id map for parent lookups in second pass
            $textToFirstId[$text] = $textToFirstId[$text] ?? $q->id;
        }

        // Second pass: re-link sub-questions and conditional children for processed questions only.
        // Delete only links where BOTH parent AND child are in processedIds — using OR would nuke
        // links for shared sub-questions (e.g. "Remark" linked to 13 MR parents) that aren't
        // being re-created via the same pass.
        $processedIds = array_keys($nameToId);
        \App\Models\QuestionSubQuestion::whereIn('parent_question_id', $processedIds)
            ->whereIn('child_question_id', $processedIds)->delete();

        // Re-create MR sub-question links from per-row $subLinks (not from $nameToId which
        // deduplicates by question_id and would lose all-but-last parent for shared sub-questions).
        $seqByParent = [];
        foreach ($subLinks as $link) {
            if (!isset($textToFirstId[$link['parentGroupQ']])) continue;
            $parentId = $textToFirstId[$link['parentGroupQ']];
            $childId  = $link['childId'];
            if ($childId === $parentId) continue;
            $seqByParent[$parentId] = ($seqByParent[$parentId] ?? 0) + 1;
            \App\Models\QuestionSubQuestion::create([
                'parent_question_id' => $parentId,
                'child_question_id'  => $childId,
                'sequence'           => $seqByParent[$parentId],
            ]);
            Question::where('id', $childId)->update(['is_parent' => 0]);
        }

        foreach ($nameToId as $qId => $info) {
            if ($info['dependsOnQ'] && isset($textToFirstId[$info['dependsOnQ']])) {
                $parentId   = $textToFirstId[$info['dependsOnQ']];
                $triggerVal = ($info['dependsOnA'] === 'is_answered') ? '__non_empty__' : $info['dependsOnA'];
                Question::where('id', $qId)->update([
                    'parent_question_id' => $parentId,
                    'parent_value'       => $triggerVal,
                    'is_parent'          => 0,
                ]);
            }
        }

        return back()->with('success', "Import complete: {$created} added, {$updated} updated.");
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
            if (!empty($info['options']) && in_array($qtype, ['Dropdown', 'Multi select', 'Barcode', 'QR Code', 'RFID'])) {
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

        // Show ALL questions except the parent itself and already-linked ones.
        // Multi Response questions are allowed as sub-questions (nested MR hierarchy).
        $available_questions = Question::where('activity_id', $question->activity_id)
            ->whereRaw('id != ?', [$parentRawId])
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
            $childId  = $link->child_question_id;
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

            // A sub-question can be reused under multiple parents (see subQuestions()),
            // so only restore is_parent once nothing else still claims this question as a
            // sub-question — otherwise it's left orphaned: is_parent=0 but not actually
            // linked anywhere, which makes getAllQuestion() wrongly surface it as a
            // standalone root question instead of hiding it.
            $stillLinkedElsewhere = QuestionSubQuestion::where('child_question_id', $childId)->exists();
            if (!$stillLinkedElsewhere) {
                Question::where('id', $childId)
                    ->whereNull('parent_question_id')
                    ->update(['is_parent' => 1]);
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

    private function excelDateToString($value): ?string
    {
        if ($value === null || $value === '') return null;
        $str = trim((string) $value);
        if ($str === '') return null;
        // Excel stores dates as integer serial numbers (e.g. 46204)
        if (is_numeric($str)) {
            try {
                // Excel epoch: Dec 30, 1899 (accounting for Lotus 1-2-3 leap-year bug)
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', '1899-12-30')
                    ->addDays((int) $str);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        // Already a date string — normalise to Y-m-d
        try {
            foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fmt) {
                try {
                    return \Carbon\Carbon::createFromFormat($fmt, $str)->format('Y-m-d');
                } catch (\Exception $e) {}
            }
            return \Carbon\Carbon::parse($str)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

}
