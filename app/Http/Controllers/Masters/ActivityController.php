<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Question;
use App\Models\QuestionDropdown;
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
            // Remove the first element from the array
            array_shift($dataArray);
            // dd($dataArray);
            foreach ($dataArray as $data) {
                if (empty($data[0])) {
                    continue;
                }

                $check_already_exits = Question::where('activity_id', $acitivity->id)->where('question', $data[1])->first();
                if (!$check_already_exits) {
                    $answer_type = 1;
                    if ($data[3] == "Optional") {
                        $answer_type = 0;
                    }
                    $new_question = Question::create([
                        'activity_id' => $acitivity->id,
                        'question' => $data[1],
                        'question_type' => $data[2],
                        'question_sequence' => $sequence_counter,
                        'answer_type' => $answer_type
                    ]);
                    $sequence_counter++;
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


}
