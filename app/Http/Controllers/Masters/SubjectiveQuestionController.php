<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Question;
use App\Models\SubjectDropdown;
use App\Models\SubjectQuestion;
use Illuminate\Http\Request;

class SubjectiveQuestionController extends Controller
{
    public function index()
    {
        $subjective_activities = Activity::whereHas('questions', function ($query) {
            $query->where('question_type', 'Subjective');
        })->paginate(10);
        return view('masters.subjectives.index', compact('subjective_activities'));
    }
    public function subjects($id)
    {
        $question_info = Question::findOrFail($id);
        return view('masters.activities.activity_question_subjects', compact('question_info'));
    }

    public function subject_editOrUpdate(Request $request)
    {
//        dd($request->form_data);
        if (!empty($request->form_data['subjective_id'])) {
            $questionSubject = SubjectQuestion::findOrFail($request->form_data['subjective_id']);

            $questionSubject->update([
                'subject' => $request->form_data['subject_val'],
                'answer_type' => $request->form_data['subjective_answer_type']
            ]);
            return response()->json(['message' => 'success', 'message_info', 'Updated']);
        } else {
            SubjectQuestion::create([
                'question_id' => $request->form_data['question_id'],
                'subject' => $request->form_data['subject_val'],
                'answer_type' => $request->form_data['subjective_answer_type']
            ]);
            return  response()->json(['message' => "success", 'message_info' => "Created"]);
        }
    }
    public function subject_question_destroy(Request $request)
    {
        try {
            $subjectQuestion = SubjectQuestion::findOrFail($request->id);
            $subjectOptions = SubjectDropdown::where('subject_id', $subjectQuestion->id)->delete();
            $subjectQuestion->delete();
            return "Success";
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response("Question option not found", 404);
        } catch (\Exception $e) {
            return response("An error occurred", 500);
        }
    }
}
