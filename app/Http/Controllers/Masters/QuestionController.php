<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionSubQuestion;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function update(Request $request)
    {
        $form_data = $request->form_data;
        $question_info = Question::find($form_data['id']);
        if ($question_info) {

            // If the user is trying to map this question to a parent (old-style dropdown/yes-no flow),
            // block it if the question is already linked as a sub-question via the Outlet flow.
            if (!empty($form_data['parent_question_id'])) {
                $alreadySubChild = QuestionSubQuestion::where('child_question_id', $form_data['id'])->exists();
                if ($alreadySubChild) {
                    $outletParent = QuestionSubQuestion::where('child_question_id', $form_data['id'])
                        ->with('parentQuestion')
                        ->first();
                    $parentName = $outletParent && $outletParent->parentQuestion
                        ? $outletParent->parentQuestion->question
                        : 'an Outlet question';
                    return response()->json([
                        'message' => 'error',
                        'error'   => "This question is already linked as a sub-question under \"{$parentName}\". Remove it from the Outlet sub-questions first before mapping it to a parent question.",
                    ]);
                }
            }

            $parentQuestion = Question::find($form_data['parent_question_id']);
            $question_info->update([
                'question' => $form_data['question'],
                'question_type' => $form_data['question_type'],
                'question_sequence' => $form_data['question_sequence'],
                'answer_type' => $form_data['answer_type'],
                'parent_question_id' => $form_data['parent_question_id'],
                'parent_dropdown_id' => $parentQuestion->question_type == 'Yes / No' ? null : $form_data['parent_dropdown_id'],
                'parent_value' => $form_data['parent_value'],
            ]);
            return response()->json(['message' => "success", 'question_info' => $question_info]);

        } else {
            return "This question doesn't exists";
        }
    }
    

    public function destroy(Request $request)
    {
        $question = Question::find($request->id);
        $question->delete();
        return "Success";
    }
}
