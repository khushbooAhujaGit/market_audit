<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Project;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\Unit;
use App\Models\Zone;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DropdownController extends Controller
{
    
    function __construct(){
        return $this->middleware('auth');
    }
    
    public function index()
    {
        $acitivityWithDropdownQuestions = Activity::whereHas('questions', function ($query) {
            $query->where('question_type', 'Dropdown');
        })->paginate(10);
        return view('masters.dropdowns.index', compact('acitivityWithDropdownQuestions'));
    }

    public function create()
    {
        $activities = Activity::whereHas('questions', function ($query) {
            $query->where('question_type', 'Dropdown');
        })->get();
        return view('masters.dropdowns.add', compact('activities'));
    }

    public function store(Request $request)
    {
        foreach ($request->dropdown_options as $option_val) {
            $check_if_already = QuestionDropdown::where("question_id", $request->question_id)->where("option", $option_val)->first();
            if (!$check_if_already) {
                QuestionDropdown::create([
                    'question_id' => $request->question_id,
                    'option' => $option_val
                ]);
            }
        }
        return response()->json(['message' => "Success"]);
    }

    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'activity_id' => ['required'],
//            'project_id' => ['required', 'integer']
        ]);
        $activity = Activity::find($id);
        $activity->update($formFields);
        return redirect(route('activity.list'))->with('message', 'Activity Updated');
    }

    public function edit($id)
    {
//        $template = Template::with('getProject.getUnit.getZone.getCompany')->find($id);
        $activity = Activity::find($id);
        $companies = Company::all();
        $zones = Zone::all();
        $units = Unit::all();
        $projects = Project::all();
        return view('masters.activities.edit', compact('activity', 'companies', 'zones', 'units', 'projects'));
    }

    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);
        $activity_questions = Question::where('template_id', $template->id)->get();
        foreach ($activity_questions as $question) {
//            UserQu::where('question_id', $question->id)->delete();
        }
        Question::where('activity_id', $activity->id)->delete();
        $activity->delete();
        return back()->with('message', 'Activity Deleted Successfully');
    }

    public function get_activity_dropdown_questions(Request $request)
    {
        $questions = Question::where('activity_id', $request->id)->where('question_type', 'Dropdown')->get();
        return response()->json(['message' => "Success", 'related_questions' => $questions]);
    }

    public function dropdown_options_editOrUpdate(Request $request)
    {
        if (!empty($request->form_data['question_dropdown_id'])) {
            $questionDropdown = QuestionDropdown::findOrFail($request->form_data['question_dropdown_id']);
            $questionDropdown->update([
                'option' => $request->form_data['option_val']
            ]);
            return response()->json(['message' => 'success', 'message_info', 'Updated']);
        } else {
            QuestionDropdown::create([
                'question_id' => $request->form_data['question_id'],
                'option' => $request->form_data['option_val']
            ]);
            return response()->json(['message' => "success", 'message_info' => "Created"]);
        }
    }

    public function excel_option()
    {
        return view('masters.dropdowns.excel_options_import');
    }

      public function excel_option_store(Request $request)
    {
        $validated = $request->validate([
            'dropdown_options' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ]);
        if ($request->hasFile('dropdown_options')) {
            $file = $request->file('dropdown_options');
            $data = Excel::toCollection(Excel::class, $file)->first();
            $dataArray = $data->toArray();
            // Remove the first element from the array
            array_shift($dataArray);
            foreach ($dataArray as $data) {
                if (empty($data[0])) {
                    continue;
                }
                $check_if_question_type_dropdown = Question::where('id', $data[0])
                    ->where('question_type', 'Dropdown')
                    ->first();
                if ($check_if_question_type_dropdown) {
                    for ($index = 1; $index < count($data); $index++) {
                        if (empty($data[$index])) {
                            break;
                        }
                        $check_if_option_exists = QuestionDropdown::where('question_id', $check_if_question_type_dropdown->id)
                            ->where('option', $data[$index])
                            ->first();
                        if ($check_if_question_type_dropdown) {
                            QuestionDropdown::create([
                                'question_id' => $data[0],
                                'option' => $data[$index],
                            ]);
                        }
                    }
                }
            }
        }
        return back()->with(['message' => 'Successfully uploaded']);
    }


}
