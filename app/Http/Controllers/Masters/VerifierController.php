<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExcelImportJob;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\Question;
use App\Models\QuestionSubQuestion;
use App\Models\RemarkMaster;
use App\Models\TemplateNameHead;
use App\Models\TempUserActivityAnswersData;
use App\Models\ActivityInstanceClose;
use App\Models\User;
use App\Models\Verifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use ZipArchive;

class VerifierController extends Controller
{
    public function user_verification()
    {
        $userId = Auth::id();
        $my_verifications = Verifier::where('user_id', $userId)
            ->with('projectTemplateInfo')->orderBy('id', 'DESC')->get();
        // {{ $my_verifications->links() }}
        //     $my_verifications = Verifier::where('user_id', $userId)
        // ->with([
        //     'projectTemplateInfo.activity',                // eager load activity
        //     'projectTemplateInfo.activityGroup.get_group_activities.activityName', // eager load group activities + activityName
        // ])
        // ->orderBy('id', 'DESC')
        // ->paginate(10);

        return view('masters.verifiers.user_verifications', compact('my_verifications'));
    }

    public function group_verification_data($pt, $g, $s, $a)
    {
        Session::put('group_verification_rerender', ['pt' => $pt, 'g' => $g, 's' => $s, 'a' => $a]);
        $projectTemplateInfo = ProjectTemplate::findOrFail($pt);
        $activity_info = Activity::findOrFail($a);
        $activity_group_info = ActivityGroup::findOrFail($g);
        $uniqueRowIds = $projectTemplateInfo->projectTemplateValues()
            ->select('id')
            ->distinct()
            ->get()
            ->pluck('id');
        $data_to_verify_Ids = TempUserActivityAnswersData::whereIn('row_id', $uniqueRowIds)
            ->where('activity_id', $a)
            // ->where('activity_group_name_id', $activity_group_info->id)
            ->where('status', 0) // this is to only get pending answers
            ->select('row_id')
            ->distinct()
            ->get()
            ->pluck('row_id');
        // dd($data_to_verify_Ids, $activity_group_info->id);
        $verification_data = [];
        foreach ($data_to_verify_Ids as $verify_id) {
            //            $verify_data = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $verify_id)->first();
            $verify_data = ProjectTemplateNameValuesNew::find($verify_id);
            $template_name_values = [];
            $json_data = json_decode($verify_data->template_data_json);
            foreach ($json_data as $key => $value) {
                $templateHeadName = TemplateNameHead::find($key);
                $template_name_values[] = [
                    'name' => $templateHeadName->template_head_name,
                    'value' => $value,
                ];
            }
            $verify_data->templateNameValues = $template_name_values;
            $verification_data[] = $verify_data;
            //            $verification_data['templateNameValues'] = $template_name_values;
        }

        return view('masters.verifiers.verifications', compact('verification_data', 'activity_info', 'activity_group_info', 'projectTemplateInfo'));
    }

    public function activity_verification_data($pt, $a)
    {
        Session::put('activity_verification_rerender', ['pt' => $pt, 'a' => $a]);
        $projectTemplateInfo = ProjectTemplate::findOrFail($pt);
        $activity_info = Activity::findOrFail($a);
        $uniqueRowIds = $projectTemplateInfo->projectTemplateValues()
            ->select('id')
            ->distinct()
            ->get()
            ->pluck('id');

        // For activity_add_on activities, only show rows where the auditor has
        // finally submitted (closed) all instances via the Submit button.
        $isActivityAddOn = $projectTemplateInfo->activity_add_on == 1;
        if ($isActivityAddOn) {
            $addOnActivityIds = json_decode($projectTemplateInfo->activity_add_on_activity_ids ?? '[]', true);
            $activityInAddOn  = empty($addOnActivityIds) || in_array((int)$a, array_map('intval', $addOnActivityIds));

            if ($activityInAddOn) {
                // Only include rows that have been fully closed by the auditor
                $closedRowIds = ActivityInstanceClose::where('activity_id', $a)
                    ->whereIn('row_id', $uniqueRowIds)
                    ->pluck('row_id')
                    ->unique()
                    ->toArray();
                $uniqueRowIds = collect($closedRowIds);
            }
        }

        // Get unique (row_id, activity_sequence) pairs that are pending verification
        $rawPairs = TempUserActivityAnswersData::whereIn('row_id', $uniqueRowIds)
            ->where('activity_id', $a)
            ->where('status', 0)
            ->select('row_id', 'activity_sequence')
            ->distinct()
            ->orderBy('row_id')
            ->orderByRaw('COALESCE(activity_sequence, 0) ASC')
            ->get();

        $verification_data = [];
        $rowCache = [];
        foreach ($rawPairs as $pair) {
            if (!isset($rowCache[$pair->row_id])) {
                $rd = ProjectTemplateNameValuesNew::find($pair->row_id);
                if (!$rd) continue;
                $vals = [];
                $json_data = json_decode($rd->template_data_json);
                foreach ($json_data as $key => $value) {
                    $head = TemplateNameHead::find($key);
                    $vals[] = ['name' => $head->template_head_name, 'value' => $value];
                }
                $rd->templateNameValues = $vals;
                $rowCache[$pair->row_id] = ['model' => $rd, 'vals' => $vals];
            }

            $seq = (int)($pair->activity_sequence ?? 0);
            $instance_label = null;
            if ($seq > 0) {
                $inst = \App\Models\ActivityRepeatInstance::where('row_id', $pair->row_id)
                    ->where('activity_id', $a)
                    ->where('activity_sequence', $seq)
                    ->first();
                $instance_label = $inst ? $inst->instance_label : "Instance {$seq}";
            }

            $entry = clone $rowCache[$pair->row_id]['model'];
            $entry->templateNameValues = $rowCache[$pair->row_id]['vals'];
            $entry->activity_sequence  = $seq;
            $entry->instance_label     = $instance_label;
            $verification_data[] = $entry;
        }
        return view('masters.verifiers.verifications', compact('verification_data', 'activity_info'));
    }

    public function checkAndCreateDirectory($directory)
    {
        $publicPath = public_path($directory);
        // Check if the directory doesn't exist
        if (!File::exists($publicPath)) {
            // Create the directory
            File::makeDirectory($publicPath, 0777, true, true);
        }
    }


    public function data_to_verify($r, $a, $g = null, $seq = 0)
    {
        //         dd($r);
        $related_values = ProjectTemplateNameValuesNew::find($r);

        $template_name_values = [];
        $json_data = json_decode($related_values->template_data_json);
        foreach ($json_data as $key => $value) {
            $templateHeadName = TemplateNameHead::find($key);
            $template_name_values[] = [
                'name' => $templateHeadName->template_head_name,
                'value' => $value,
            ];
        }

        $related_values->templateNameValues = $template_name_values;

        //khushboo 15-05-25
        // dd($related_values[0]->project_template_id);
        $projectTemplateData = ProjectTemplate::find($related_values->project_template_id);
        $remarks = RemarkMaster::where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();
        // dd($projectTemplateData);
        //khushboo 15-05-25

        $activity_check = Activity::findOrFail($a);
        // $remarks = RemarkMaster::where('status', 1)->get();
        $activity_group_info = '';
        if ($g) {
            $activity_group_info = ActivityGroup::findOrFail($g);
        }
        $related_questions = Question::with([
            'getOptions',
            'getSubjects.getOptions',
            'subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.getSubjects.getOptions',
            'subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
        ])->where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();

        // BFS: collect ALL sub-question child IDs across all levels
        $sub_question_child_ids = [];
        $toProcess = $related_questions->pluck('id')->toArray();
        while (!empty($toProcess)) {
            $childIds = QuestionSubQuestion::whereIn('parent_question_id', $toProcess)
                ->pluck('child_question_id')->toArray();
            $newIds = array_diff($childIds, $sub_question_child_ids);
            if (empty($newIds)) break;
            $sub_question_child_ids = array_merge($sub_question_child_ids, $newIds);
            $toProcess = array_values($newIds);
        }

        $activity_sequence = (int)$seq;
        $instance_label = null;
        if ($activity_sequence > 0) {
            $inst = \App\Models\ActivityRepeatInstance::where('row_id', $r)
                ->where('activity_id', $a)
                ->where('activity_sequence', $activity_sequence)
                ->first();
            $instance_label = $inst ? $inst->instance_label : "Instance {$activity_sequence}";
        }

        $user_responses = TempUserActivityAnswersData::where('row_id', $r)
            ->where('activity_id', $activity_check->id)
            ->where(function ($q) use ($activity_sequence) {
                if ($activity_sequence > 0) {
                    $q->where('activity_sequence', $activity_sequence);
                } else {
                    $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                }
            })
            ->get();

        //check if otp verification is allowed
        $project = Project::find($projectTemplateData->project_id);
        $is_otp_required = $project->is_otp_required;
        // if ($is_otp_required && $user_responses[0]->otp_verified_status == 1) {
        $checkLatLongData = TempUserActivityAnswersData::where('row_id', $r)
            ->where('activity_id', $activity_check->id)->whereNotNull('latitude')->whereNotNull('longitude')->exists();
        $userLatLongData = [];
        if ($checkLatLongData) {
            $userLatLongData = TempUserActivityAnswersData::where('row_id', $r)
                ->where('activity_id', $activity_check->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest() // orders by created_at descending by default
                ->first(['latitude', 'longitude']);
        }
        // dd($user_responses, $related_values);

        $auditorData = User::find($user_responses[0]->user_id);
        $agencyData = !empty($auditorData) ? User::find($auditorData->agency_user_id)->first() : null;
        $auditDateTime = $user_responses[0]->created_at->format('Y-m-d H:i:s');

        return view('masters.verifiers.verify', compact('auditDateTime', 'agencyData', 'auditorData', 'related_values', 'activity_check', 'related_questions', 'sub_question_child_ids', 'user_responses', 'activity_group_info', 'remarks', 'userLatLongData', 'activity_sequence', 'instance_label'));
        // }
        // else{
        //     return redirect()->back()->with('message', 'OTP Verification is pending for this audit');
        // }
    }


    public function activity_answer_verify(Request $request)
    {
        $group_id = null;
        $group_or_single = 0;
        if ($request->has('activity_group_id')) {
            $group_or_single = 1;
            $group_id = $request->activity_group_id;
        }
        $userId = Auth::id();
        $new_status = "";
        $row_id = $request->row_id;
        $a_id = $request->activity_id;
        $activity_sequence = (int)($request->activity_sequence ?? 0);
        $seqFilter = function ($q) use ($activity_sequence) {
            if ($activity_sequence > 0) {
                $q->where('activity_sequence', $activity_sequence);
            } else {
                $q->where(function ($inner) {
                    $inner->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                });
            }
        };

        //khushboo 18-06-2025
        //answer pdf create
        $projectTemplateValue = ProjectTemplateNameValuesNew::where('id', $row_id)->get();
        $projectTemplate = ProjectTemplate::find($projectTemplateValue[0]->project_template_id);
        $projectTemplateHeaderId = $projectTemplate->main_header;
        $projectTemplateValueData = ProjectTemplateNameValuesNew::where('id', $row_id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateHeaderId}\"')) as value")
            )
            //            ->where('template_name_head_id', $projectTemplateHeaderId)
            ->first();
        //answer pdf create
        //khushboo 18-06-2025

        $activity_info = Activity::findOrFail($a_id);
        $related_questions = Question::where('activity_id', $activity_info->id)->pluck('id')->toArray();
        $g_id = $group_id ? $group_id : 0;

        $verifier_remark = $request->remark == 'other' && isset($request->other_remark) ? $request->other_remark : $request->remark;
        //khushboo 10-06-25
        if ($request->remark == 'other' && isset($request->other_remark) && empty($request->other_remark)) {
            redirect()->back()->with('error', 'Please Add Other Remark Text');
        }
        //khushboo 10-06-25

        $baseDirectory = 'activityAnswerImages/'; // this is the base directory where all the project directories will be kept

        $get_project_template_value_row_info = ProjectTemplateNameValuesNew::find($row_id);
        //        dd($get_project_template_value_row_info->getProjectTemplateData->getProject);

        $projectName = $get_project_template_value_row_info->getProjectTemplateData->getProject->project_name;
        $projectId = $get_project_template_value_row_info->getProjectTemplateData->getProject->id;

        $projectDirectory = $baseDirectory . $projectName . '/';
        $this->checkAndCreateDirectory($projectDirectory);
        // Get the current month and year
        $currentMonthYear = Carbon::now()->format('FY');
        // Create directory for the current month and year if it doesn't exist
        $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
        $message = "";
        if ($request->has('approve')) {
            // Handle the approval logic
            $new_status = 5; // this means the the answer user filled is verified by the verifier
            $message = "Verified Successfully";
        }


        // Check if the "reject" button was clicked
        if ($request->has('reject')) {
            $message = "Rejected Successfully";
            // Handle the rejection logic
            $new_status = 4; // this means the the answer user filled is rejected by the verifier
        }

        //khushboo 10-06-25 (create new remark)

        if ($request->has('other_remark') && !empty($request->other_remark)) {

            $checkifRemarkExist = RemarkMaster::where('project_id', $projectId)->where('remark', 'LIKE', $request->other_remark)->exists();
            if (!$checkifRemarkExist) {

                $newRemark = RemarkMaster::create([
                    'remark' => $request->other_remark,
                    'status' => 1,
                    'project_id' => $projectId,
                ]);

                $verifier_remark = $newRemark->id;
            }
        }
        $auditDate = null;

        //khushboo 10-06-25

        //khushboo 07-05-26

        // Check if the "send_back" button was clicked
        if ($request->has('send_back')) {
            // Handle the approval logic
            $new_status = 3; // this means the the answer user filled is verified by the verifier
            $message = "Sendback Successfully";

            //check for otp
            $otp_verified_status_exist = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $request->activity_id)
                ->where($seqFilter)
                ->where('otp_verified_status', 1)->exists();
            if ($otp_verified_status_exist) {
                TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where($seqFilter)
                    ->update([
                        'status' => 3,
                        'otp_verified_status' => 0,
                        'mobile_otp' => null,
                        'mobile_no' => null
                    ]);
            } else {
                TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where($seqFilter)
                    ->update(['status' => 3]);
            }
            // Mark repeat instance as pending so user can re-submit
            if ($activity_sequence > 0) {
                \App\Models\ActivityRepeatInstance::where('row_id', $row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where('activity_sequence', $activity_sequence)
                    ->update(['status' => 0]);
            }
            // Remove close record so auditor can re-enter instances page after sendback
            ActivityInstanceClose::where('row_id', $row_id)
                ->where('activity_id', $request->activity_id)
                ->delete();

            $params = Session::get('activity_verification_rerender');
            return redirect(route('activity.verification.view', $params))->with('message', $message);
        }

        //khushboo 07-05-26

        $parameters = $request->except(['activity_id', 'activity_group_id', 'row_id', '_token', 'reject', 'approve', 'activity_group_name_id', 'sequence', 'remark', 'activity_sequence', 'other_remark']);
        foreach ($parameters as $key => $value) {
            if (!is_object($value)) {
                if (!empty($value)) {
                    $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                        ->where("activity_id", $a_id)
                        ->where($seqFilter)
                        ->where(function ($query) use ($g_id) {
                            if ($g_id == 0) {
                                $query->whereNull('activity_group_name_id')
                                    ->orWhere('activity_group_name_id', 0);
                            } else {
                                $query->where('activity_group_name_id', $g_id);
                            }
                        })
                        ->where('question_id', $key)->first();

                    $check_questionType = Question::find($key);
                    //                    dd($key);
                    //                    dd($check_questionType->question_type);
                    if (!empty($check_questionType)) {
                        if ($check_questionType->question_type == "Date") {
                            if (isset($value)) {
                                $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                            }
                        } elseif ($check_questionType->question_type == "Multi select") {
                            $value = implode(',', $value);
                        } elseif ($check_questionType->question_type == "Audio") {
                            if (strpos($value, 'data:audio/') === 0) {
                                $get_privous_audio = TempUserActivityAnswersData::where('row_id', $row_id)
                                    ->where('activity_id', $activity_info->id)
                                    ->where('question_id', $key)
                                    ->first();
                                if ($get_privous_audio) {
                                    $previousAudioPath = public_path($get_privous_audio->user_answer);
                                    if (File::exists($previousAudioPath)) {
                                        File::delete($previousAudioPath);
                                    }
                                }
                                $audioData = $value;
                                $audioBlob = base64_decode(explode(',', $audioData)[1]);
                                $audioFileName = uniqid() . '.wav';
                                $audioFilePath = $projectMonthYearDirectory . '/' . $audioFileName;
                                // Save the audio file
                                file_put_contents(public_path($audioFilePath), $audioBlob);
                                // Set user_answer to the file path
                                $value = $audioFilePath;
                            } else {
                                $parsed_url = parse_url($value);
                                // The path will start with /activityAnswerImages, so we extract that part
                                $relative_path = substr($parsed_url['path'], strpos($parsed_url['path'], 'activityAnswerImages'));
                                $value = $relative_path;
                            }
                        }
                    }

                    if (!empty($check_questionType) && $check_questionType->question_type == "Subjective") {
                        $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                            ->where("activity_id", $a_id)
                            ->where($seqFilter)
                            ->where(function ($query) use ($g_id) {
                                if ($g_id == 0) {
                                    $query->whereNull('activity_group_name_id')
                                        ->orWhere('activity_group_name_id', 0);
                                } else {
                                    $query->where('activity_group_name_id', $g_id);
                                }
                            })
                            ->where('question_id', $key)->get();

                        foreach ($user_answer_get as $key1 => $value1) {
                            //                            echo '<pre>';
                            //                            echo 'subjective - '.$value1->id;
                            //                            echo $value;

                            if (!empty($value) && $value1->user_answer != $value) {
                                $value1->user_answer = $value;
                                $value1->edited_by_verifier = 1;
                            } else {
                                $value1->edited_by_verifier = 0;
                            }
                            $value1->status = $new_status;
                            $value1->remark = $verifier_remark;
                            $value1->verified_by = $userId;
                            $value1->edited_by_verifier = 1;
                            $value1->save();
                            // Remove the question ID from the related questions array
                            if (($index = array_search($key, $related_questions)) !== false) {
                                unset($related_questions[$index]);
                            }
                        }
                    } else {
                        //                        echo '<pre>';
                        //                        echo 'non subjective'.$user_answer_get->id;
                        if ($user_answer_get) {

                            if (!empty($value) && $user_answer_get->user_answer != $value) {
                                $user_answer_get->user_answer = $value;
                                $user_answer_get->edited_by_verifier = 1;
                            } else {
                                $user_answer_get->edited_by_verifier = 1;
                            }
                            $user_answer_get->status = $new_status;
                            $user_answer_get->remark = $verifier_remark;
                            $user_answer_get->verified_by = $userId;
                            $user_answer_get->save();
                            // Remove the question ID from the related questions array
                            if (($index = array_search($key, $related_questions)) !== false) {
                                unset($related_questions[$index]);
                            }
                        }
                    }
                }
            }
        }
        // dd($new_status);
        foreach ($related_questions as $re_quest) {

            //            dd($row_id, $a_id, $g_id, $re_quest);
            $user_answer_count = TempUserActivityAnswersData::where("row_id", $row_id)
                ->where("activity_id", $a_id)
                ->where($seqFilter)
                ->where(function ($query) use ($g_id) {
                    if ($g_id == 0) {
                        $query->whereNull('activity_group_name_id')
                            ->orWhere('activity_group_name_id', 0);
                    } else {
                        $query->where('activity_group_name_id', $g_id);
                    }
                })
                ->where('question_id', $re_quest)->count();
            if ($user_answer_count == 1) {

                $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                    ->where("activity_id", $a_id)
                    ->where($seqFilter)
                    ->where(function ($query) use ($g_id) {
                        if ($g_id == 0) {
                            $query->whereNull('activity_group_name_id')
                                ->orWhere('activity_group_name_id', 0);
                        } else {
                            $query->where('activity_group_name_id', $g_id);
                        }
                    })
                    ->where('question_id', $re_quest)->first();

                $auditDate = $user_answer_get->created_at;

                //                echo '<pre>';
                //                echo 'image'.$user_answer_get->id;
                if ($request->hasFile($re_quest)) {
                    //                    echo '<pre>';
                    //                    echo 'image edit'.$user_answer_get->id;
                    // this is to remove the previous file from the folder when verifier is updating the file
                    $folder = "answer";
                    $previousImagePath = public_path('answer/' . basename($user_answer_get->user_answer));
                    if (File::exists($previousImagePath)) {
                        File::delete($previousImagePath);
                    }
                    $answer_image = $request->file($re_quest);
                    $file_name = 'file_' . time() . rand(0, 999) . '.' . $answer_image->getClientOriginalExtension();
                    $answer_image->move(public_path($folder), $file_name);
                    $answer_image_url = url('/') . '/' . $folder . '/' . $file_name;
                    $user_answer_get->user_answer = $answer_image_url;
                    $user_answer_get->status = $new_status;
                    $user_answer_get->remark = $verifier_remark;
                    $user_answer_get->verified_by = $userId;
                    $user_answer_get->edited_by_verifier = 1;
                    $user_answer_get->save();
                } else {
                    //                dd($user_answer_get->status);
                    //                print_r($user_answer_get);
                    //                    echo '<pre>';
                    //                    echo 'image save'.$user_answer_get->id;
                    $user_answer_get->status = $new_status;
                    $user_answer_get->remark = $verifier_remark;
                    $user_answer_get->verified_by = $userId;
                    $user_answer_get->edited_by_verifier = 1;
                    $user_answer_get->save();
                }
            } else if ($user_answer_count > 1) {

                $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                    ->where("activity_id", $a_id)
                    ->where($seqFilter)
                    ->where(function ($query) use ($g_id) {
                        if ($g_id == 0) {
                            $query->whereNull('activity_group_name_id')
                                ->orWhere('activity_group_name_id', 0);
                        } else {
                            $query->where('activity_group_name_id', $g_id);
                        }
                    })
                    ->where('question_id', $re_quest)->get();

                $auditDate = $user_answer_get[0]->created_at;

                foreach ($user_answer_get as $key1 => $value1) {
                    //                    echo '<pre>';
                    //                    echo 'subj other img- '.$value1->id;

                    if ($request->hasFile($re_quest)) {
                        //                        echo '<pre>';
                        //                        echo 'subj other with edit img- '.$value1->id;
                        // this is to remove the previous file from the folder when verifier is updating the file
                        $folder = "answer";
                        $previousImagePath = public_path('answer/' . basename($user_answer_get->user_answer));
                        if (File::exists($previousImagePath)) {
                            File::delete($previousImagePath);
                        }
                        $answer_image = $request->file($re_quest);
                        $file_name = 'file_' . time() . rand(0, 999) . '.' . $answer_image->getClientOriginalExtension();
                        $answer_image->move(public_path($folder), $file_name);
                        $answer_image_url = url('/') . '/' . $folder . '/' . $file_name;
                        $value1->user_answer = $answer_image_url;
                        $value1->status = $new_status;
                        $value1->remark = $verifier_remark;
                        $value1->verified_by = $userId;
                        $value1->edited_by_verifier = 1;
                        $value1->save();
                    } else {
                        //                dd($user_answer_get->status);
                        //                print_r($user_answer_get);
                        //                        echo '<pre>';
                        //                        echo 'subj other without edit img- '.$value1->id;
                        $value1->status = $new_status;
                        $value1->remark = $verifier_remark;
                        $value1->verified_by = $userId;
                        $value1->edited_by_verifier = 1;
                        $value1->save();
                    }
                }
            }
        }

        try {
            //            dd('fghgf');
            $this->storeAnswerPdf($row_id, $projectTemplateValueData->value, $a_id, $auditDate);
        } catch (\Exception $e) {
            //            dd($e->getMessage());
        }

        //        dd('gjnhg');

        if ($group_or_single == 1) {
            $params = Session::get('group_verification_rerender');
            return redirect(route('activityGroup.verification.view', $params))->with('message', $message);
        }
        $params = Session::get('activity_verification_rerender');
        return redirect(route('activity.verification.view', $params))->with('message', $message);

        return back()->with(['message' => $message]);
    }


    public function activity_answer_verifyOldd(Request $request)
    {
        $group_id = null;
        $group_or_single = 0;
        if ($request->has('activity_group_id')) {
            $group_or_single = 1;
            $group_id = $request->activity_group_id;
        }
        $userId = Auth::id();
        $new_status = "";
        $row_id = $request->row_id;
        //        dd($row_id);
        $a_id = $request->activity_id;

        //khushboo 18-06-2025
        //answer pdf create
        $projectTemplateValue = ProjectTemplateNameValuesNew::where('id', $row_id)->get();
        $projectTemplate = ProjectTemplate::find($projectTemplateValue[0]->project_template_id);
        $projectTemplateHeaderId = $projectTemplate->main_header;
        $projectTemplateValueData = ProjectTemplateNameValuesNew::where('id', $row_id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateHeaderId}\"')) as value")
            )
            //            ->where('template_name_head_id', $projectTemplateHeaderId)
            ->first();
        //answer pdf create
        //khushboo 18-06-2025

        $activity_info = Activity::findOrFail($a_id);
        $related_questions = Question::where('activity_id', $activity_info->id)->pluck('id')->toArray();
        $g_id = $group_id ? $group_id : 0;

        $verifier_remark = $request->remark;
        //khushboo 10-06-25
        if ($request->remark == 'other' && isset($request->other_remark) && empty($request->other_remark)) {
            redirect()->back()->with('error', 'Please Add Other Remark Text');
        }
        //khushboo 10-06-25

        $baseDirectory = 'activityAnswerImages/'; // this is the base directory where all the project directories will be kept

        $get_project_template_value_row_info = ProjectTemplateNameValuesNew::find($row_id);
        //        dd($get_project_template_value_row_info->getProjectTemplateData->getProject);

        $projectName = $get_project_template_value_row_info->getProjectTemplateData->getProject->project_name;
        $projectId = $get_project_template_value_row_info->getProjectTemplateData->getProject->id;

        $projectDirectory = $baseDirectory . $projectName . '/';
        $this->checkAndCreateDirectory($projectDirectory);
        // Get the current month and year
        $currentMonthYear = Carbon::now()->format('FY');
        // Create directory for the current month and year if it doesn't exist
        $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
        $message = "";
        if ($request->has('approve')) {
            // Handle the approval logic
            $new_status = 5; // this means the the answer user filled is verified by the verifier
            $message = "Verified Successfully";
        }

        // Check if the "reject" button was clicked
        if ($request->has('reject')) {
            $message = "Rejected Successfully";
            // Handle the rejection logic
            $new_status = 4; // this means the the answer user filled is rejected by the verifier
        }

        //khushboo 10-06-25 (create new remark)

        if ($request->has('other_remark') && !empty($request->other_remark)) {


            $checkifRemarkExist = RemarkMaster::where('project_id', $projectId)->where('remark', 'LIKE', $request->other_remark)->exists();
            if (!$checkifRemarkExist) {

                $newRemark = RemarkMaster::create([
                    'remark' => $request->other_remark,
                    'status' => 1,
                    'project_id' => $projectId,
                ]);

                $verifier_remark = $newRemark->id;
            }
        }
        $auditDate = null;

        //khushboo 10-06-25

        $parameters = $request->except(['activity_id', 'activity_group_id', 'row_id', '_token', 'reject', 'approve', 'activity_group_name_id', 'sequence', 'remark']);
        foreach ($parameters as $key => $value) {
            if (!is_object($value)) {
                if (!empty($value)) {
                    $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                        ->where("activity_id", $a_id)
                        ->where(function ($query) use ($g_id) {
                            if ($g_id == 0) {
                                $query->whereNull('activity_group_name_id')
                                    ->orWhere('activity_group_name_id', 0);
                            } else {
                                $query->where('activity_group_name_id', $g_id);
                            }
                        })
                        ->where('question_id', $key)->first();

                    $check_questionType = Question::find($key);
                    //                    dd($key);
                    //                    dd($check_questionType->question_type);
                    if (!empty($check_questionType)) {
                        if ($check_questionType->question_type == "Date") {
                            if (isset($value)) {
                                $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                            }
                        } elseif ($check_questionType->question_type == "Multi select") {
                            $value = implode(',', $value);
                        } elseif ($check_questionType->question_type == "Audio") {
                            if (strpos($value, 'data:audio/') === 0) {
                                $get_privous_audio = TempUserActivityAnswersData::where('row_id', $row_id)
                                    ->where('activity_id', $activity_info->id)
                                    ->where('question_id', $key)
                                    ->first();
                                if ($get_privous_audio) {
                                    $previousAudioPath = public_path($get_privous_audio->user_answer);
                                    if (File::exists($previousAudioPath)) {
                                        File::delete($previousAudioPath);
                                    }
                                }
                                $audioData = $value;
                                $audioBlob = base64_decode(explode(',', $audioData)[1]);
                                $audioFileName = uniqid() . '.wav';
                                $audioFilePath = $projectMonthYearDirectory . '/' . $audioFileName;
                                // Save the audio file
                                file_put_contents(public_path($audioFilePath), $audioBlob);
                                // Set user_answer to the file path
                                $value = $audioFilePath;
                            } else {
                                $parsed_url = parse_url($value);
                                // The path will start with /activityAnswerImages, so we extract that part
                                $relative_path = substr($parsed_url['path'], strpos($parsed_url['path'], 'activityAnswerImages'));
                                $value = $relative_path;
                            }
                        }
                    }

                    if (!empty($check_questionType) && $check_questionType->question_type == "Subjective") {
                        $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                            ->where("activity_id", $a_id)
                            ->where(function ($query) use ($g_id) {
                                if ($g_id == 0) {
                                    $query->whereNull('activity_group_name_id')
                                        ->orWhere('activity_group_name_id', 0);
                                } else {
                                    $query->where('activity_group_name_id', $g_id);
                                }
                            })
                            ->where('question_id', $key)->get();

                        foreach ($user_answer_get as $key1 => $value1) {

                            if ($value1->user_answer !== $value) {
                                $value1->user_answer = $value;
                                $value1->edited_by_verifier = 1;
                            }
                            $value1->status = $new_status;
                            $value1->remark = $verifier_remark;
                            $value1->verified_by = $userId;
                            $value1->save();
                            // Remove the question ID from the related questions array
                            if (($index = array_search($key, $related_questions)) !== false) {
                                unset($related_questions[$index]);
                            }
                        }
                    } else {
                        if ($user_answer_get) {

                            if ($user_answer_get->user_answer !== $value) {
                                $user_answer_get->user_answer = $value;
                                $user_answer_get->edited_by_verifier = 1;
                            }
                            $user_answer_get->status = $new_status;
                            $user_answer_get->remark = $verifier_remark;
                            $user_answer_get->verified_by = $userId;
                            $user_answer_get->save();
                            // Remove the question ID from the related questions array
                            if (($index = array_search($key, $related_questions)) !== false) {
                                unset($related_questions[$index]);
                            }
                        }
                    }
                }
            }
        }
        // dd($new_status);
        foreach ($related_questions as $re_quest) {

            //            dd($row_id, $a_id, $g_id, $re_quest);
            $user_answer_count = TempUserActivityAnswersData::where("row_id", $row_id)
                ->where("activity_id", $a_id)
                ->where(function ($query) use ($g_id) {
                    if ($g_id == 0) {
                        $query->whereNull('activity_group_name_id')
                            ->orWhere('activity_group_name_id', 0);
                    } else {
                        $query->where('activity_group_name_id', $g_id);
                    }
                })
                ->where('question_id', $re_quest)->count();
            if ($user_answer_count == 1) {

                $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                    ->where("activity_id", $a_id)
                    ->where(function ($query) use ($g_id) {
                        if ($g_id == 0) {
                            $query->whereNull('activity_group_name_id')
                                ->orWhere('activity_group_name_id', 0);
                        } else {
                            $query->where('activity_group_name_id', $g_id);
                        }
                    })
                    ->where('question_id', $re_quest)->first();

                $auditDate = $user_answer_get->created_at;

                if ($request->hasFile($re_quest)) {

                    // this is to remove the previous file from the folder when verifier is updating the file
                    $folder = "answer";
                    $previousImagePath = public_path('answer/' . basename($user_answer_get->user_answer));
                    if (File::exists($previousImagePath)) {
                        File::delete($previousImagePath);
                    }
                    $answer_image = $request->file($re_quest);
                    $file_name = 'file_' . time() . rand(0, 999) . '.' . $answer_image->getClientOriginalExtension();
                    $answer_image->move(public_path($folder), $file_name);
                    $answer_image_url = url('/') . '/' . $folder . '/' . $file_name;
                    $user_answer_get->user_answer = $answer_image_url;
                    $user_answer_get->status = $new_status;
                    $user_answer_get->remark = $verifier_remark;
                    $user_answer_get->verified_by = $userId;
                    $user_answer_get->edited_by_verifier = 1;
                    $user_answer_get->save();
                } else {
                    //                dd($user_answer_get->status);
                    //                print_r($user_answer_get);
                    $user_answer_get->status = $new_status;
                    $user_answer_get->remark = $verifier_remark;
                    $user_answer_get->verified_by = $userId;
                    $user_answer_get->save();
                }
            } else if ($user_answer_count > 1) {

                $user_answer_get = TempUserActivityAnswersData::where("row_id", $row_id)
                    ->where("activity_id", $a_id)
                    ->where($seqFilter)
                    ->where(function ($query) use ($g_id) {
                        if ($g_id == 0) {
                            $query->whereNull('activity_group_name_id')
                                ->orWhere('activity_group_name_id', 0);
                        } else {
                            $query->where('activity_group_name_id', $g_id);
                        }
                    })
                    ->where('question_id', $re_quest)->get();

                $auditDate = $user_answer_get[0]->created_at;

                foreach ($user_answer_get as $key1 => $value1) {

                    if ($request->hasFile($re_quest)) {

                        // this is to remove the previous file from the folder when verifier is updating the file
                        $folder = "answer";
                        $previousImagePath = public_path('answer/' . basename($user_answer_get->user_answer));
                        if (File::exists($previousImagePath)) {
                            File::delete($previousImagePath);
                        }
                        $answer_image = $request->file($re_quest);
                        $file_name = 'file_' . time() . rand(0, 999) . '.' . $answer_image->getClientOriginalExtension();
                        $answer_image->move(public_path($folder), $file_name);
                        $answer_image_url = url('/') . '/' . $folder . '/' . $file_name;
                        $value1->user_answer = $answer_image_url;
                        $value1->status = $new_status;
                        $value1->remark = $verifier_remark;
                        $value1->verified_by = $userId;
                        $value1->edited_by_verifier = 1;
                        $value1->save();
                    } else {
                        //                dd($user_answer_get->status);
                        //                print_r($user_answer_get);
                        $value1->status = $new_status;
                        $value1->remark = $verifier_remark;
                        $value1->verified_by = $userId;
                        $value1->save();
                    }
                }
            }
        }

        try {

            $this->storeAnswerPdf($row_id, $projectTemplateValueData->value, $a_id, $auditDate);
        } catch (\Exception $e) {
            //            dd($e->getMessage());
        }


        //        dd('gjnhg');

        if ($group_or_single == 1) {
            $params = Session::get('group_verification_rerender');
            return redirect(route('activityGroup.verification.view', $params))->with('message', $message);
        }
        $params = Session::get('activity_verification_rerender');
        return redirect(route('activity.verification.view', $params))->with('message', $message);

        return back()->with(['message' => $message]);
    }


    public function storeAnswerPdf($r, $v, $a, $auditDate)
    {

        //        $related_values = ProjectTemplateNameValue::where('row_id', $r)->where('value', $v)->get();
        $related_values = ProjectTemplateNameValuesNew::where('id', $r)
            //            ->where('value', $v)
            ->where('template_data_json', 'like', '%"' . $v . '"%')
            ->get();
        //        dd($related_values);

        $projectTemplateData = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('id', $related_values[0]->project_template_id)->first();

        //        dd($projectTemplateData);
        $remarks = RemarkMaster::where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();

        $activity_check = Activity::find($a);

        $related_questions = Question::where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();

        $user_responses = TempUserActivityAnswersData::with('getUser')
            ->where('row_id', $r)
            ->where('activity_id', $activity_check->id)
            ->get();


        if (!$user_responses->IsEmpty()) {

            $auditorInfo = $user_responses[0];
            $auditDate = $user_responses[0]->created_at;
            $auditDate = Carbon::parse($auditDate)->format('d-m-Y');

            $auditTime = $user_responses[0]->created_at;
            $auditTime = Carbon::parse($auditTime)->format('H:i A');

            $pdf = Pdf::loadView('masters.pdf_templates.verify_template', [
                'projectTemplateData' => $projectTemplateData,
                'related_questions' => $related_questions,
                'auditorInfo' => $auditorInfo,
                'auditDate' => $auditDate,
                'auditTime' => $auditTime,
                'user_responses' => $user_responses,
                'value' => $v
            ]);

            // Define base directory
            $baseDirectory = 'activityAnswerImages/';

            // Get project name
            $projectTemp = ProjectTemplateNameValuesNew::find($r);
            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;

            // Build path: activityAnswerImages/{projectName}/
            $projectDirectory = $baseDirectory . $projectName . '/';
            $this->checkAndCreateDirectory($projectDirectory);

            // 1️⃣ Make sure the directory exists & is writable
            $this->ensureWritableDirectory($projectDirectory, 0777);

            // Build path: activityAnswerImages/{projectName}/{MonthYear}/
            $currentMonthYear = Carbon::now()->format('FY');
            $projectMonthYearDirectory = $projectDirectory . $currentMonthYear . '/';
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            // ✅ Create answerPdfs folder inside activityAnswerImages/{projectName}/{MonthYear}/
            $answerPdfsDirectory = $projectMonthYearDirectory . 'answerPdfs/';
            $this->checkAndCreateDirectory($answerPdfsDirectory);

            // 1️⃣ Make sure the directory exists & is writable
            $this->ensureWritableDirectory($answerPdfsDirectory, 0777);

            $sanitizedProjectName = str_replace(' ', '-', $projectName);
            $fileName = 'Audit-' . $sanitizedProjectName . '_' . $r . '_' . $auditDate . '.pdf';
            // Define the PDF filename
            //            $fileName = 'Audit-' . $projectName . '_' . $r . '_' . $auditDate . '.pdf';

            // Full path to save
            $fullFilePath = public_path($answerPdfsDirectory . $fileName);

            // ✅ Check if file already exists
            if (!File::exists($fullFilePath)) {
                // Save the PDF if it doesn't already exist
                $pdf->save($fullFilePath);

                // 3️⃣ Give the newly‑created file full permissions too
                $this->setFilePermissions($fullFilePath, 0777);
            } else {
                // Optionally log or return message
                Log::info("PDF already exists: " . $fileName);
            }
        }
    }

    private function ensureWritableDirectory(string $absPath, int $mode = 0777): void
    {
        if (!File::isDirectory($absPath)) {
            // recursive = true, force = true
            File::makeDirectory($absPath, $mode, true, true);
        }

        // chmod even if it already existed (covers deploys where default perms changed)
        File::chmod($absPath, $mode);
    }


    private function setFilePermissions(string $absPath, int $mode = 0777): void
    {
        if (File::exists($absPath)) {
            File::chmod($absPath, $mode);
        }
    }


    public function viewTemplateFile($r, $a, $g = null)
    {
        // dd($r);
        $related_values = ProjectTemplateNameValuesNew::where('id', $r)->get();

        //khushboo 15-05-25
        // dd($related_values[0]->project_template_id);
        $projectTemplateData = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('id', $related_values[0]->project_template_id)->first();
        // dd($projectTemplateData);

        $remarks = RemarkMaster::where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();
        // dd($projectTemplateData);
        //khushboo 15-05-25

        $activity_check = Activity::findOrFail($a);
        // $remarks = RemarkMaster::where('status', 1)->get();
        $activity_group_info = '';
        if ($g) {
            $activity_group_info = ActivityGroup::findOrFail($g);
        }

        $related_questions = Question::where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();
        //        dd($related_questions);

        $user_responses = TempUserActivityAnswersData::with('getUser')
            ->where('row_id', $r)
            ->where('activity_id', $activity_check->id)
            ->get();

        $checkLatLongData = TempUserActivityAnswersData::where('row_id', $r)
            ->where('activity_id', $activity_check->id)->whereNotNull('latitude')->whereNotNull('longitude')->exists();
        $userLatLongData = [];

        if ($checkLatLongData) {
            $userLatLongData = TempUserActivityAnswersData::where('row_id', $r)
                ->where('activity_id', $activity_check->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get(['latitude', 'longitude'])
                ->toArray();
        }

        $auditorInfo = $user_responses[0];
        $auditDate = $user_responses[0]->created_at;
        $auditDate = Carbon::parse($auditDate)->format('d-m-Y');

        $auditTime = $user_responses[0]->created_at;
        $auditTime = Carbon::parse($auditTime)->format('H:i A');

        return view(
            'masters.pdf_templates.verify_template',
            compact(
                'related_values',
                'activity_check',
                'related_questions',
                'user_responses',
                'activity_group_info',
                'remarks',
                'userLatLongData',
                'projectTemplateData',
                'auditorInfo',
                'auditTime',
                'auditDate',
            )
        );
    }

    public function downloadPdfTemplate(Request $request)
    {
        $r = $request->row_id;
        $v = $request->distributor_value;
        $a = $request->activity_id;

        //        $related_values = ProjectTemplateNameValue::where('row_id', $r)->where('value', $v)->get();

        $related_values = ProjectTemplateNameValuesNew::where('id', $r)
            //            ->where('value', $v)
            // ->where('template_data_json', 'like', '%"'.$v.'"%')
            //  ->whereJsonContains('template_data_json', $v)
            ->where('template_data_json', 'like', '%' . addslashes($v) . '%')
            ->get();


        // dd($related_values);

        $projectTemplateData = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('id', $related_values[0]->project_template_id)->first();

        //        dd($projectTemplateData);
        $remarks = RemarkMaster::where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();

        $activity_check = Activity::find($a);

        $related_questions = Question::where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();

        $user_responses = TempUserActivityAnswersData::with('getUser')
            ->where('row_id', $r)
            ->where('activity_id', $activity_check->id)->get();


        if (!$user_responses->IsEmpty()) {

            $auditorInfo = $user_responses[0];
            $auditDate = $user_responses[0]->created_at;
            $auditDate = Carbon::parse($auditDate)->format('d-m-Y');

            $auditTime = $user_responses[0]->created_at;
            $auditTime = Carbon::parse($auditTime)->format('H:i A');

            $templateJson = json_decode($related_values[0]->template_data_json, true);

            $mainHeaderValue = $templateJson[$projectTemplateData->main_header] ?? null;
            $subHeaderValue = $templateJson[$projectTemplateData->sub_header] ?? null;


            $pdf = Pdf::loadView('masters.pdf_templates.verify_template', [
                'projectTemplateData' => $projectTemplateData,
                'related_questions' => $related_questions,
                'auditorInfo' => $auditorInfo,
                'auditDate' => $auditDate,
                'auditTime' => $auditTime,
                'user_responses' => $user_responses,
                'value' => $v,
                'main_header' => $mainHeaderValue,
                'sub_header' => $subHeaderValue
            ]);


            // File name and path
            $fileName = 'Audit-' . uniqid() . '.pdf';
            $filePath = public_path('temp/' . $fileName);
            //        dd($filePath);

            // Ensure the directory exists
            if (!file_exists(public_path('temp'))) {
                mkdir(public_path('temp'), 0755, true);
            }

            // Save the PDF to the public/temp folder
            $pdf->save($filePath);

            // Return the public URL to access the PDF
            $url = asset('temp/' . $fileName);

            return response()->json([
                'file_url' => $url,
                'file_name' => $fileName
            ]);
        }
    }

    public function deleteTempPdf(Request $request)
    {
        $fileName = $request->file_name;
        $filePath = public_path('temp/' . $fileName);

        if (file_exists($filePath)) {
            unlink($filePath);
            return response()->json(['message' => 'File deleted']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }


    public function reportPage()
    {

        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if ($currentUser->getRoleNames()->first() == 'Verifier') {
            $companies = [];
            $userId = Auth::id();

            // Get the project_template_ids assigned to this verifier
            $templateIds = Verifier::where('user_id', $userId)
                ->pluck('project_template_name_id');

            //            dd($templateIds);
            // Now get projects that are linked to those templates
            $projects = Project::whereHas('getProjectTemplates', function ($query) use ($templateIds) {
                $query->whereIn('id', $templateIds);
            })
                ->orderBy('id', 'DESC')->get();

            //            dd($projects);
        }

        if ($currentUser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->orderBy('id', 'DESC')
                ->get();
        }

        if ($currentUser->getRoleNames()->first() == 'Super Admin') {
            $companies = [];
            $projects = Project::orderBy('id', 'DESC')->get();
        }

        if ($currentUser->getRoleNames()->first() == 'Agency') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('is_agency_required', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('agency_id', $currentUser->id);
                })
                ->orderBy('id', 'DESC')
                ->get();
        }

        //        dd($projects);
        return view('masters.companies.pdf_report_page', compact('companies', 'currentUser', 'projects', 'currentUserRole'));
    }


    public function exportAnswerPdfs(Request $request)
    {
        $projectData = Project::find($request->project_id);
        $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
        $rowIds = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplateInfo->id)->pluck('id')->toArray();

        $endDate = $request->end_date;
        $startDate = $request->start_date;


        //        dd(empty($startDate));

        if (empty($rowIds)) {
            return response()->json(['error' => 'No row IDs provided.'], 422);
        }

        $zip = new ZipArchive();
        $zipFileName = 'All_PDFs_' . now()->format('Ymd_His') . '.zip';
        $zipPath = public_path('zips/' . $zipFileName);

        // Ensure zip directory exists
        if (!File::exists(public_path('zips'))) {
            File::makeDirectory(public_path('zips'), 0755, true);
        }

        $filesAdded = false;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

            foreach ($rowIds as $rowId) {

                $projectName = $projectData->project_name;
                $sanitizedProjectName = str_replace(' ', '-', $projectName);
                //                $sanitizedProjectName = Str::slug($projectName);
                $folderPath = public_path("activityAnswerImages/{$projectName}/" . now()->format('FY') . "/answerPdfs/");

                if (!File::exists($folderPath)) continue;

                $pdfFiles = File::files($folderPath);
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();

                // dd($pdfFiles, $startDate, $endDate);

                foreach ($pdfFiles as $file) {

                    $addToZip = true;
                    $fileName = $file->getFilename();

                    // Get file modified time
                    $fileDate = Carbon::createFromTimestamp($file->getMTime());
                    // dd($fileDate);


                    //                    dd($sanitizedProjectName);
                    $pattern = '/^Audit-' . preg_quote($sanitizedProjectName, '/') . '_(\d+)_([\d]{2}-[\d]{2}-[\d]{4})\.pdf$/';

                    if (preg_match($pattern, $fileName, $matches)) {
                        $matchedRowId = $matches[1];
                        $rawDate = $matches[2]; // e.g. 11-06-2025

                        // Check if row ID matches
                        if ($matchedRowId != $rowId) {
                            continue;
                        }


                        // Check date range
                        // // if (!empty($startDate) && !empty($endDate)) {
                        // if ($fileDate->between($startDate, $endDate)) {
                        //     // if ($fileDate <= $startDate || $fileDate >= $endDate) {
                        //         continue;
                        //     // }
                        // }
                        // Skip file if it's outside the date range
                        if (!$fileDate->between($startDate, $endDate)) continue;


                        $zip->addFile($file->getRealPath(), $fileName);
                        $filesAdded = true;
                    }
                }
            }

            $zip->close();

            if ($filesAdded && File::exists($zipPath)) {
                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return response()->json(['error' => 'No PDFs matched your filters.'], 404);
        }

        return response()->json(['error' => 'Could not create zip file.'], 500);
    }
}
