<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DataAssign;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\ProjectTemplateNameValue;
use App\Models\AuditorAssignedData;
use App\Models\AnswerActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends Controller
{
    //

    public function authentication(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                'mobile' => 'required',
                'password' => 'required',
                // 'device_no' => 'required',
            ]);

            if ($validator->fails()) {
                return response([
                    'status' => 401,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $creaditional  = $request->only(['mobile', 'password']);
            $user = User::where('mobile', $request->mobile)->first();

            if (!$user) {
                return response([
                    'status' => 201,
                    'message' => 'User not found',
                ], 201);
            }

            // if ($user->device_no !== $request->device_no) {
            //     if (!$user->device_no) {
            //         $user->device_no = $request->device_no;
            //         $user->save();
            //     } else {

            //         return response([
            //             'status' => 401,
            //             'message' => 'Please Login with your old device',
            //         ], 401);
            //     }
            // }
            if (Auth::attempt($creaditional)) {
                $user = Auth::user();

                return response([
                    'status' => 200,
                    'message' => 'Login successfully',
                    'user' => $user,
                    'token' =>  $user->createToken("API TOKEN")->plainTextToken,
                ]);
            } else {
                return response([
                    'status' => 203,
                    'message' => 'Invalid creaditional',

                ], 200);
            }
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage()
            ]);
        }
    }
    public function fetchQuestion(Request $request)
    {

        try {

            $user_id = $request->user_id;

            // $proTempdata = ProjectTemplateNameValue::where('value', $request->value)->where('project_template_id', $request->project_template_id)->first();



            // $auditorid =  AuditorAssignedData::with('dataAssign')->where('user_id', $user_id)->whereHas('dataAssign', function ($query) use ($proTempdata) {
            //                 $query->where('project_template_id',$proTempdata->project_template_id)
            //                 ->where('template_name_head_id', $proTempdata->template_name_head_id);
            //         })->first();

            // return $auditorid;
            // return $auditorid->dataAssign->activity_id;
            $user =  Question::with(['getOptions:id,question_id,option'])->where('activity_id', $request->activity_id)->active()
                ->select('id', 'question', 'question_type', 'question_sequence')->orderBy('question_sequence', 'asc')->get();

            foreach ($user as $item) {

                $answer = AnswerActivity::where('user_id', $user_id)->where('question_id', $item->id)->where('row_id', $request->row_id)->first();

                $item->answer = $answer ? $answer->user_answer : null;
            }

            if (!$user) {
                return response([
                    'status' => 201,
                    'message' => 'something is wrong!',

                ], 201);
            }


            return response([
                'status' => 200,
                'message' => 'success',
                'questions' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 200,
                'message' => $th->getMessage(),
            ], 200);
        }
    }
    //
    public function  updateStatus(Request $request)
    {
        try {
            $user_id = $request->user_id;

            $dataAssign =  DataAssign::find($request->id);
            $dataAssign->status = 2;
            $dataAssign->save();

            if ($dataAssign) {
                return response([
                    'status' => 200,
                    'message' => 'success',
                ], 200);
            } else {
                return response([
                    'status' => 201,
                    'message' => 'Something is wrong',

                ], 201);
            }
        } catch (\Throwable $th) {
            return response([
                'status' => 200,
                'message' => $th->getMessage(),
            ], 200);
        }
    }
}
