<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\SubjectDropdown;
use App\Models\SubjectQuestion;
use Illuminate\Http\Request;

class SubjectDropdownController extends Controller
{
    public function view_subject_options($id){
        $subjectInfo = SubjectQuestion::findOrFail($id);

        return view('masters.activities.subject_options', compact('subjectInfo'));

    }

    public function subject_option_editOrUpdate(Request $request){
        if(!empty($request->form_data['sub_option_id'])){
            $questionSubject = SubjectDropdown::findOrFail($request->form_data['sub_option_id']);
            $questionSubject->update([
                'option' => $request->form_data['subject_option'],
//                'answer_type' => $request->form_data['subjective_answer_type'],  //khushboo 11-06-2025
            ]);
            return response()->json(['message'=> 'success', 'message_info', 'Updated']);
        }else{
            SubjectDropdown::create([
                'subject_id' => $request->form_data['subject_id'],
                'option' => $request->form_data['subject_option'],
//                'answer_type' => $request->form_data['subjective_answer_type'],  //khushboo 11-06-2025
            ]);
            return  response()->json(['message' => "success", 'message_info' => "Created"]);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $subjectDropdown = SubjectDropdown::findOrFail($request->id);
            $subjectDropdown->delete();
            return "Success";
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response("Question option not found", 404);
        } catch (\Exception $e) {
            return response("An error occurred", 500);
        }
    }

    public function get_subject_options(Request $request){
        $questionInfo = Question::where('id', $request->question_id)->first();
        $questionSubjectInfo = SubjectQuestion::with('getOptions')->where('question_id', $questionInfo->id)
            ->where('subject', $request->subject)->first();

        return response()->json(['message' => "success", "subjectOption" => $questionSubjectInfo]);
    }

    public function  get_subject_dropdowns($id)
    {
//        dd($id);
//        $question_info = Question::findOrFail($id);
        $subjectiveOptionInfo = SubjectDropdown::find($id);
        $questionId = $subjectiveOptionInfo->SubjectiveQuestion->questionInfo->id;
//        dd($questionId);
        $dropdownData = QuestionDropdown::where('subject_id', $subjectiveOptionInfo->subject_id)
            ->where('subject_dropdown_id', $id)
            ->where('question_id', $questionId)
            ->get();
//        dd($questionId, $subjectiveOptionInfo->subject_id, $id);
        return view('masters.activities.subjective_option_dropdown', compact('subjectiveOptionInfo', 'dropdownData', 'questionId'));

    }

    public function store_subject_dropdowns(Request $request){
//        dd($request->all());
        if (!empty($request->form_data['question_dropdown_id'])) {
            $questionDropdown = QuestionDropdown::findOrFail($request->form_data['question_dropdown_id']);
            $questionDropdown->update([
                'option' => $request->form_data['option_val'],
                'subject_id' => $request->form_data['subject_id'],
                'subject_dropdown_id' => $request->form_data['subject_dropdown_id'],
            ]);
            return response()->json(['message' => 'success', 'message_info', 'Updated']);
        } else {
            QuestionDropdown::create([
                'question_id' => $request->form_data['question_id'],
                'option' => $request->form_data['option_val'],
                'subject_id' => $request->form_data['subject_id'],
                'subject_dropdown_id' => $request->form_data['subject_dropdown_id'],
            ]);
            return response()->json(['message' => "success", 'message_info' => "Created"]);
        }
    }

}
