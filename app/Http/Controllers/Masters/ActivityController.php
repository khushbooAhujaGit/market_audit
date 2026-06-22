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
            'activity_name' => ['required', 'unique:activities,activity_name'],
            'questions_excel' => [
                'required',
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
            // Remove header row
            array_shift($dataArray);

            // Map: excel sequence number → created question id (for sub-question linking)
            $sequenceToQuestionId = [];

            // ── PASS 1: Create all questions ──────────────────────────────────
            foreach ($dataArray as $row) {
                if (empty($row[0])) {
                    continue;
                }

                $check_already_exits = Question::where('activity_id', $acitivity->id)->where('question', $row[1])->first();
                if (!$check_already_exits) {
                    $answer_type = 1;
                    if ($row[3] == "Optional") {
                        $answer_type = 0;
                    }
                    $is_parent = 1;
                    if (isset($row[4]) && !empty($row[4]) && $row[4] == "Yes") {
                        $is_parent = 0;
                    }
                    $allow_multiple_images = 0;
                    if ($row[2] === 'Image' && isset($row[7]) && strtolower(trim((string)$row[7])) === 'yes') {
                        $allow_multiple_images = 1;
                    }
                    $new_question = Question::create([
                        'activity_id'           => $acitivity->id,
                        'question'              => $row[1],
                        'question_type'         => $row[2],
                        'question_sequence'     => $sequence_counter,
                        'answer_type'           => $answer_type,
                        'is_parent'             => $is_parent,
                        'allow_multiple_images' => $allow_multiple_images,
                    ]);
                    // Track sequence → id for sub-question linking
                    $sequenceToQuestionId[(int)$row[0]] = $new_question->id;
                    $sequence_counter++;
                }
            }

            // ── PASS 2: Create sub-question links (columns F & G) ─────────────
            // Col index 5 = "Sub Question Of (Sequence No.)"
            // Col index 6 = "Sub Question Sequence"
            foreach ($dataArray as $row) {
                if (empty($row[0])) {
                    continue;
                }
                // Only process rows that have a parent sequence value in column F
                if (!isset($row[5]) || empty($row[5])) {
                    continue;
                }

                $parentSeq   = (int)$row[5];
                $subSeq      = isset($row[6]) && !empty($row[6]) ? (int)$row[6] : 1;
                $childSeq    = (int)$row[0];

                $parentQuestionId = $sequenceToQuestionId[$parentSeq] ?? null;
                $childQuestionId  = $sequenceToQuestionId[$childSeq]  ?? null;

                if (!$parentQuestionId || !$childQuestionId || $parentQuestionId === $childQuestionId) {
                    continue;
                }

                $alreadyLinked = QuestionSubQuestion::where('parent_question_id', $parentQuestionId)
                    ->where('child_question_id', $childQuestionId)
                    ->exists();

                if (!$alreadyLinked) {
                    QuestionSubQuestion::create([
                        'parent_question_id' => $parentQuestionId,
                        'child_question_id'  => $childQuestionId,
                        'sequence'           => $subSeq,
                    ]);
                }
            }
        }

        return redirect(route('activities.list'))->with('message', "Acitivity Created Successfully");
    }


    public function view_question($activity_id)
    {
        $activity = Activity::findOrFail($activity_id);
        return view('masters.activities.activity_questions', compact('activity'));
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
        $linked_ids = $question->subQuestions->pluck('child_question_id')->toArray();

        // Exclude questions already used as children in ANY question_sub_questions link
        // (a question can only belong to one Outlet parent at a time)
        $already_sub_children = QuestionSubQuestion::pluck('child_question_id')->unique()->toArray();
        $exclude_ids = array_unique(array_merge($linked_ids, $already_sub_children));

        // Available questions:
        //   - From the same activity, not the parent itself
        //   - is_parent=0 (child type) OR question_type='Multi Response' (for nested sections)
        //   - whereNull('parent_question_id') → excludes old-style conditional children
        //     (those are owned by Dropdown/Yes-No parents via parent_question_id column;
        //      they belong to the old flow and must NOT mix into the Multi Response flow)
        //   - Not already linked as a sub-question child to any Multi Response parent
        $available_questions = Question::where('activity_id', $question->activity_id)
            ->where('id', '!=', $question_id)
            ->where(function ($q) {
                $q->where('is_parent', 0);
                //   ->orWhere('question_type', 'Multi Response');
            })
            ->whereNull('parent_question_id')
            ->whereNotIn('id', $exclude_ids)
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
