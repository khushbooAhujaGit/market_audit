<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

       public function update(Request $request){
        $form_data =  $request->form_data;
        $question_info = Question::find($form_data['id']);
        if($question_info){
            $question_info->update([
               'question' => $form_data['question'],
               'question_type' => $form_data['question_type'],
               'question_sequence' => $form_data['question_sequence'],
               'answer_type' => $form_data['answer_type'],
            ]);
            return response()->json(['message' => "success", 'question_info' => $question_info]);

        }else{
            return "This question doesn't exists";
        }
    }

    public function destroy(Request $request){
        $question = Question::find($request->id);
        $question->delete();
        return "Success";
    }
}
