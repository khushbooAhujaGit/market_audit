<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityRepeatInstance;
use App\Models\ActivityInstanceClose;
use App\Models\QuestionSubQuestion;
use App\Models\ActivityGroupPivot;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectMasterTemplates;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\Question;
use App\Models\TemplateName;
use App\Models\TemplateNameHead;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ConvertAuditorSelfiesToGeoSelfies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// use http\Client\Curl\User;

class TaskHandlerController extends Controller
{
    use \App\Traits\InterventionImage;
    // this is to render the projects assigned to the user logged in
    public function userProjects()
    {
        $user = Auth::user();
        //khushboo 05-07-25
        $dataAssignIds = UserActivityDataAssign::where('user_id', $user->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id')->toArray();
        $uniqueUserProjects = DataAssign::whereIn('id', $dataAssignIds)->distinct('project_id')->pluck('project_id')->toArray();
        //khushboo 05-07-25
        $userProjects = Project::whereIn('id', $uniqueUserProjects)->get();
        return view('masters.users.user_projects', compact('userProjects'));
    }


    public function userProjectMasterData(Project $project)
    {

        $startTime = microtime(true);
        $isParent = 1;
        $group_session_id = null;
        // if (!isset($group_info->id)) {
        //     $group_info = null;
        // } else {
        //     $group_session_id = $group_info->id;
        // }
        $row_renderred_arr = [];
        $user = Auth::user();
        $project_data_arr = [];
        $project_data_completed_arr = [];
        $project_temp_info = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first();
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;

        $project_template_details = DB::table('project_templates')->where('project_id', $project->id)
            ->get();

        //        if ($project_temp_info->is_master == 1) {

        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        // dd($projectTemplateInfoDetails, $projectTemplateHeaders);
        $add_project_data_title = "Add ";
        //khushboo 05-07-25
        $activitySequence = 0;

        $isOutletAssigned = false;
        foreach ($project_template_details as $tempKey => $tempData) {

            $getDataAssignIds = DataAssign::where('project_id', $project->id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('id')
                ->pluck('id');

            $getDataAssignTemplateHeadIds = DataAssign::where('project_id', $project->id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('template_name_head_id')
                ->pluck('template_name_head_id');

            $checkingIfProjectAssigned = UserActivityDataAssign::where('user_id', $user->id)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->exists();

            $dataAssignCommonIds = UserActivityDataAssign::where('user_id', $user->id)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id')
                ->toArray();

            $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')->pluck('row_id')->toArray();

            // dd($getRowIds);
            //khushboo 05-07-25

            if ($checkingIfProjectAssigned) {
                $projectTemplateHeadsAssigned = [];
                //khushboo 05-07-25
                if (!empty($getDataAssignTemplateHeadIds)) {
                    foreach ($getDataAssignTemplateHeadIds as $headId) {

                        $templateMainHeaderId = $tempData->master_head_id;
                        if ($tempData->is_master == 0) {

                            $getHeadValues = DB::table('project_template_name_values_new')
                                ->where('project_template_id', $project_temp_info->id)
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$templateMainHeaderId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();

                            if (!empty($getHeadValues)) {
                                $isOutletAssigned = true;
                            }
                            //                            dd($tempData->master_head_id, $getHeadValues);
                        } else {

                            $getHeadValues = DB::table('project_template_name_values_new')
                                ->whereIn('id', $getRowIds)
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();
                            // dd($getHeadValues);
                        }

                        //                        $getHeadValues = DB::table('project_template_name_values_new')
                        //                            ->whereIn('id', $getRowIds)
                        //                            ->select(
                        //                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                        //                            )
                        //                            ->get();

                        $project_templates_data = ProjectTemplate::where('project_id', $project->id)
                            ->where('is_master', 1)->first();

                        if (count($getHeadValues) > 0) {
                            foreach ($getHeadValues as $headValue) {
                                //                                dd($headValue->head_value);
                                if (!empty($headValue->head_value)) {
                                    $projectTemplateHeadsAssigned[] = [
                                        'project_template_id' => $project_templates_data->id,
                                        'head_id' => $tempData->is_master == 0 ? $templateMainHeaderId : $headId,
                                        'head_value' => $headValue->head_value, // Add more fields if needed
                                    ];
                                }
                            }
                        }
                    }
                }
                //khushboo 05-07-25
                // dd($projectTemplateHeadsAssigned);

                $activities = [];

                if ($tempData->activityType == 0) {
                    $activities[] = $tempData->activity_group_name_id_or_activity_id;
                } elseif ($tempData->activityType == 1) {
                    $activities = array_merge(
                        $activities,
                        DB::table('activity_group_pivots')->where('activity_group_id', $tempData->activity_group_name_id_or_activity_id)
                            ->pluck('activity_id')
                            ->toArray()
                    );
                }

                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {

                    $matchedRows = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $assigned_value_info['project_template_id'])
                        ->select('id', 'template_data_json')
                        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) = ?", [$assigned_value_info['head_value']])
                        ->get();

                    //                    echo $assigned_value_info['project_template_id'];
                    //                    print_r($matchedRows);
                    //                    echo '<pre>';
                    foreach ($matchedRows as $row) {
                        $jsonData = json_decode($row->template_data_json, true);

                        foreach ($jsonData as $headKey => $value) {
                            $headData = TemplateNameHead::find((int) $headKey);

                            $projectDataArr[] = [
                                'row_id' => $row->id,
                                'head_id' => $headKey,
                                'head_name' => $headData->template_head_name ?? '',
                                'head_value' => $value,
                            ];
                        }
                    }

                    //                    dd('ghgj');
                    // dd($projectDataArr);
                    foreach ($projectDataArr as $projectData) {
                        //                        dd($projectData);
                        if (!in_array($projectData['row_id'], $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData['row_id'];
                            // $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                            //     ->whereIn('activity_id', $activities)
                            //     ->exists();
                            // Get all required question IDs
                            $activityRequiredQuestions = Question::whereIn('activity_id', $activities)
                                // ->where('answer_type', 1)
                                ->pluck('id')
                                ->toArray();

                            // Count how many required questions were submitted
                            $submittedcount = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                ->whereIn('activity_id', $activities)
                                ->whereIn('question_id', $activityRequiredQuestions)
                                ->where('user_id', Auth::user()->id)
                                ->distinct('question_id')
                                ->count('question_id');

                            // Check if any answer exists at all
                            $is_answered = $submittedcount > 0;

                            // Check if subjective question exists
                            $subjectiveExists = Question::whereIn('activity_id', $activities)
                                ->where('answer_type', 1)
                                ->where('question_type', 'Subjective')
                                ->exists();

                            // Total required questions count
                            $requiredCount = DB::table('questions')->whereIn('activity_id', $activities)
                                ->where('answer_type', 1)
                                ->pluck('id')
                                ->count();

                            // Final check
                            if (!$is_answered) {
                                $check_if_answered = false;
                            } else {
                                // Special rule: If subjective exists → allow >= OR == (business logic?)
                                if ($subjectiveExists) {
                                    // If subjective exists, allow ANY submitted as long as count meets requirement
                                    $check_if_answered = ($submittedcount >= $requiredCount);
                                } else {
                                    // Normal: submitted must be equal or greater
                                    $check_if_answered = ($submittedcount >= $requiredCount);
                                }
                            }

                            // --- Sendback check ---
                            $isSentBack = false;
                            if ($check_if_answered) {
                                $latestAnswer = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                    ->whereIn('activity_id', $activities)
                                    ->where('user_id', Auth::user()->id)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if ($latestAnswer && $latestAnswer->status == 3) {
                                    $isSentBack = true;
                                }
                            }

                            // --- Repeat/Close check: if any activity allows repeat, require fully closed ---
                            $hasRepeatActivity = false;
                            $isFullyClosed = true;
                            foreach ($activities as $actId) {
                                $addOnIds = json_decode($tempData->activity_add_on_activity_ids ?? '[]', true);
                                $allowRepeat = $tempData->activity_add_on == 1 &&
                                    (empty($addOnIds) || in_array((int)$actId, array_map('intval', $addOnIds)));
                                if ($allowRepeat) {
                                    $hasRepeatActivity = true;
                                    $closeRecord = ActivityInstanceClose::where('row_id', $projectData['row_id'])
                                        ->where('activity_id', $actId)
                                        ->where('user_id', Auth::user()->id)
                                        ->exists();
                                    if (!$closeRecord) {
                                        $isFullyClosed = false;
                                    } else {
                                        $sentBackExists = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                            ->where('activity_id', $actId)
                                            ->where('user_id', Auth::user()->id)
                                            ->where('status', 3)->exists();
                                        if ($sentBackExists) {
                                            $isFullyClosed = false;
                                        }
                                    }
                                }
                            }

                            $rowStatus = $isSentBack ? 'pending'
                                : ($check_if_answered && (!$hasRepeatActivity || $isFullyClosed) ? 'completed' : 'pending');

                            // dd($submittedcount, $requiredCount);
                            $otpVerificationDone = false;
                            if ($project->is_otp_required == 1) {

                                $activityAllQuestions = Question::whereIn('activity_id', $activities)
                                    ->pluck('id')
                                    ->toArray();

                                $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                    ->whereIn('activity_id', $activities)
                                    ->whereIn('question_id', $activityAllQuestions)
                                    ->where('user_id', Auth::user()->id)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                // dd($lastQuestionAnswered, $activityRequiredQuestions, $activities);
                                if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                                    $otpVerificationDone = true;
                                }
                            }

                            // dd($check_if_answered, $submittedcount, $activityRequiredQuestions);

                            $get_row_main_header_value = DB::table('project_template_name_values_new')
                                ->where('id', $projectData['row_id'])
                                ->select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$main_header_id}\"')) as value"))
                                ->value('value'); // Get the single column value directly

                            $get_row_sub_header_value = DB::table('project_template_name_values_new')
                                ->where('id', $projectData['row_id'])
                                ->select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$sub_header_id}\"')) as value"))
                                ->value('value'); // Get the single column value directly

                            $main_header = null;
                            if ($get_row_main_header_value) {
                                //                                $main_header = $get_row_header->value;
                                $main_header = $get_row_main_header_value;
                            }

                            $sub_header = null;
                            if ($get_row_sub_header_value) {
                                //                                $sub_header = $get_row_sub_header->value;
                                $sub_header = $get_row_sub_header_value;
                            }
                            // dd($projectData);

                            $templateJson = DB::table('project_template_name_values_new')->where('id', $projectData['row_id'])->value('template_data_json');

                            $templateData = json_decode($templateJson, true); // associative array

                            $result = $projectTemplateHeaders->map(function ($header) use ($templateData) {
                                return [
                                    'head_id' => $header->id,
                                    'head_name' => $header->template_head_name,
                                    'value' => $templateData[$header->id] ?? null,
                                ];
                            });

                            // dd($result);

                            //khushboo 09-03-2026
                            //check if all outlets are complete or not
                            $existingoutletcompletestatus = 0;
                            $outletexist = false;
                            $outletexistcount = 0;
                            $data_add_on = 0;
                            $outlet_templates = DB::table('project_templates')->where('project_id', $project->id)->where('is_master', 0)->get();
                            foreach ($outlet_templates as $outletTemplate) {
                                if ($outletTemplate->data_add_on == 1) {
                                    $data_add_on++;
                                }
                                $outlet_template_datas = DB::table('project_template_name_values_new')->where('project_template_id', $outletTemplate->id)->get();
                                foreach ($outlet_template_datas as $outletTemplateData) {

                                    $mastertemplate = DB::table('project_template_name_values_new')->where('id', $projectData['row_id'])->first();
                                    $masterjsondata = json_decode($mastertemplate->template_data_json);
                                    $outletjsondata = json_decode($outletTemplateData->template_data_json);
                                    $masterheader = $outletTemplate->master_head_id;
                                    $outletheader = $outletTemplate->own_reference_head_id;
                                    $mastervalue = $masterjsondata->$masterheader ?? '';
                                    $outletvalue = $outletjsondata->$outletheader ?? '';

                                    if ($mastervalue == $outletvalue) {

                                        $outletexistcount++;
                                        $outletexist = true;
                                        //check if answer submitted
                                        // Get all required question IDs
                                        $outletactivities = [];

                                        if ($outletTemplate->activityType == 0) {
                                            $outletactivities[] = $outletTemplate->activity_group_name_id_or_activity_id;
                                        } elseif ($outletTemplate->activityType == 1) {
                                            $outletactivities = array_merge(
                                                $outletactivities,
                                                DB::table('activity_group_pivots')->where('activity_group_id', $outletTemplate->activity_group_name_id_or_activity_id)
                                                    ->pluck('activity_id')
                                                    ->toArray()
                                            );
                                        }
                                        $outletactivityRequiredQuestions = Question::whereIn('activity_id', $outletactivities)
                                            ->where('answer_type', 1)
                                            ->pluck('id')
                                            ->toArray();

                                        // Count how many required questions were submitted
                                        $outletsubmittedcount = TempUserActivityAnswersData::where('row_id', $outletTemplateData->id)
                                            ->whereIn('activity_id', $outletactivities)
                                            ->whereIn('question_id', $outletactivityRequiredQuestions)
                                            ->where('user_id', Auth::user()->id)
                                            ->distinct('question_id')
                                            ->count('question_id');

                                        // Check if any answer exists at all
                                        $is_outlet_answered = $submittedcount > 0;

                                        // Check if subjective question exists
                                        $subjectiveOutletExists = Question::whereIn('activity_id', $outletactivities)
                                            ->where('answer_type', 1)
                                            ->where('question_type', 'Subjective')
                                            ->exists();

                                        // Total required questions count
                                        $requiredOutanswerCount = count($outletactivityRequiredQuestions);

                                        // Final check
                                        if (!$is_outlet_answered) {
                                            $check_if_outlet_answered = false;
                                        } else {
                                            // Special rule: If subjective exists → allow >= OR == (business logic?)
                                            if ($subjectiveOutletExists) {
                                                // If subjective exists, allow ANY submitted as long as count meets requirement
                                                $check_if_outlet_answered = ($outletsubmittedcount >= $requiredOutanswerCount);
                                            } else {
                                                // Normal: submitted must be equal or greater
                                                $check_if_outlet_answered = ($outletsubmittedcount >= $requiredOutanswerCount);
                                            }
                                        }

                                        $outletotpVerificationDone = false;
                                        if ($project->is_otp_required == 1) {

                                            $activityOutletAllQuestions = Question::whereIn('activity_id', $outletactivities)
                                                ->pluck('id')
                                                ->toArray();

                                            $lastOutletQuestionAnswered = TempUserActivityAnswersData::where('row_id', $outletTemplateData->id)
                                                ->whereIn('activity_id', $outletactivities)
                                                ->whereIn('question_id', $activityOutletAllQuestions)
                                                ->where('user_id', Auth::user()->id)
                                                ->orderBy('id', 'desc')
                                                ->first();
                                            //                                          dd($lastQuestionAnswered);
                                            if (!empty($lastOutletQuestionAnswered->mobile_otp) && $lastOutletQuestionAnswered->otp_verified_status == 1) {
                                                $outletotpVerificationDone = true;
                                                $existingoutletcompletestatus++;
                                            }
                                        } else {
                                            $existingoutletcompletestatus++;
                                        }
                                    }
                                }

                                // dd($existingoutletcompletestatus, $outletexist);
                            }

                            //khushboo 09-03-2026

                            // dd($projectData);
                            $project_data_arr[] = [
                                'data_item' => $projectData,
                                'status' => $rowStatus,
                                // 'is_sent_back' => $isSentBack, 
                                'main_header' => $main_header,
                                'sub_header' => $sub_header,
                                'can_edit_data' => $project_temp_info->can_edit_data,
                                'otpVerificationDone' => $otpVerificationDone,
                                'header_data' => $result,
                                'outletexist' => $outletexist,
                                'isOtpRequired' => $project->is_otp_required,
                                'existingoutletcompletestatus' => $outletexistcount == $existingoutletcompletestatus ? true : false,
                                'outlet_data_add_on' => $data_add_on ?? 0
                            ];
                        }
                    }
                }
                // dd($project_data_arr);
            }
        }
        //        dd('ghgh');
        // if (empty($project_data_arr)) {
        //     abort(404);
        // }
        //   dd($project_data_arr);

        Session::put('project_dist_activity', ['project' => $project->id]);
        if (Session::has('project_outlet_activity')) {
            Session::forget('project_outlet_activity');
        }
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //                \Log::info('Time taken for userProjectData: ' . $executionTime . ' seconds.');
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();
        // dd($isOutletAssigned);

        return view('masters.users.project_data', compact('project_data_arr', 'isParent', 'isOutletAssigned', 'project_data_completed_arr', 'project', 'project_temp_info', 'projectTemplateInfoDetails', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole'));
    }



    public function userProjectChildData($type, Project $project, TemplateName $template, Activity $activity, ActivityGroup $group_info)
    {
        //         dd($type);
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $startIndex = (($currentPage - 1) * $perPage + 1) - 1;
        $endIndex = ($currentPage * $perPage) - 1;
        $group_session_id = null;
        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            $group_session_id = $group_info->id;
        }
        $user = Auth::user();

        $project_temp_info = ProjectTemplate::where('project_id', $project->id)
            ->where('template_name_id', $template->id)
            ->first();
        // dd($project_temp_info);
        $distributors_arr = [];
        $outlet_master_info = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first(); // this is to get the info of the master template of the project

        //khushboo 16-04-25
        $isAuditClosed = false;
        //khushboo 16-04-25

        $master_head_id = $project_temp_info->master_head_id;
        $outlet_master_sub_header_id = $outlet_master_info->sub_header;
        $outlet_master_main_header_id = $outlet_master_info->main_header;
        $outlet_master_completion_type = $outlet_master_info->completion_type;
        $outlet_master_min_completion = $outlet_master_info->min_completion;
        $master_head_info = TemplateNameHead::find($master_head_id);
        $master_head_name = !empty($master_head_info) ? $master_head_info->template_head_name : null;
        $own_head_id = $project_temp_info->own_reference_head_id;
        $own_head_info = TemplateNameHead::find($own_head_id);
        $cur_sequence_helper = 0;
        $activity_sequence = null;
        // This is to get the heads and key from outlet to the master template
        if (isset($group_info->id)) {
            $activity_sequence = ActivityGroupPivot::where('activity_id', $activity->id)
                ->where('activity_group_id', $group_info->id)
                ->first();
            if ($activity_sequence) {
                $cur_sequence_helper = $activity_sequence->sequence;
            }
        }

        $isOutletChecked = 0;
        //        dd($cur_sequence_helper);
        //        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {


        $dataAssignedIds = UserActivityDataAssign::where('project_template_id', $project_temp_info->id)
            ->where('activity_id', $activity->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id');

        $get_project_template_assigned_data = UserActivityDataAssign::with('dataAssign')
            ->where('project_template_id', $project_temp_info->id)
            ->where('activity_id', $activity->id)
            ->get()
            ->unique('activity_id');

        // dd($get_project_template_assigned_data);

        foreach ($get_project_template_assigned_data as $get_outlet_assigned) {

            $exists = false;
            $getRowIds = UserAuditAssigns::where('common_id', $get_outlet_assigned->common_id)
                ->distinct('row_id')->pluck('row_id');

            $get_rows_of_outlet = ProjectTemplateNameValuesNew::whereIn('id', $getRowIds)
                ->get();

            foreach ($get_rows_of_outlet as $get_row_of_outlet) {
                $exists = false;
                // Get the outlet assigned data for the current row
                $row_data_of_outlet_assigned_data = ProjectTemplateNameValuesNew::where('id', $get_row_of_outlet->id)
                    //                        ->where('template_name_head_id', $own_head_id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$own_head_id}\"')) as value")
                    )
                    ->first(); // this is giving me the value which is having the reference from the master db

                if ($row_data_of_outlet_assigned_data) {

                    $rendered_values = array_column($distributors_arr, 'value');
                    if ((in_array($row_data_of_outlet_assigned_data->value, $rendered_values))) {
                        $exists = true;
                    }
                    if (!$exists) {
                        $templateHeadData = TemplateNameHead::find($master_head_id);
                        $templateHeadName = $templateHeadData->template_head_name;

                        $masterHeadId = $master_head_id;   // e.g. 101
                        $masterHeadName = $templateHeadName; // e.g. "Outlet Name"

                        $get_master_row = ProjectTemplateNameValuesNew::query()
                            ->where('id', $get_row_of_outlet->id)
                            ->selectRaw("
                                                    id,
                                                    JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$masterHeadId}\"')) AS value,
                                                    '{$masterHeadId}'   AS head_id,
                                                    '{$masterHeadName}' AS template_head_name
                            ")
                            ->first();          // returns null if nothing matches

                        $get_main_header_val = ProjectTemplateNameValuesNew::where('id', $get_master_row->id)
                            //                            ->where('template_name_head_id', $outlet_master_main_header_id)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$outlet_master_main_header_id}\"')) as value")
                            )
                            ->first();

                        $dist_main_header = null;
                        if ($get_main_header_val) {
                            $dist_main_header = $get_main_header_val->value;
                        }
                        // dd($get_main_header_val);
                        // this will give the dist head and $row_data_of_outlet_assigned_data->value value
                        // check if index between starting and ending index

                        $dist_info = count($distributors_arr);

                        if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                            $distributors_arr[] = [
                                'value' => $row_data_of_outlet_assigned_data->value,
                                'main_header' => $dist_main_header,
                                'row_id' => $get_master_row->id
                            ];
                            continue;
                        }
                        $get_sub_header_val = ProjectTemplateNameValuesNew::where('id', $get_master_row->id)
                            //                            ->where('template_name_head_id', $outlet_master_sub_header_id)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$outlet_master_sub_header_id}\"')) as value")
                            )
                            ->first();

                        $dist_sub_header = null;
                        if ($get_sub_header_val) {
                            $dist_sub_header = $get_sub_header_val->value;
                        }

                        $get_count_of_outlets = ProjectTemplateNameValuesNew::where('project_template_id', $project_temp_info->id)
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, ?)) = ?", [
                                '$."' . $own_head_id . '"',
                                trim($row_data_of_outlet_assigned_data->value)
                            ])
                            ->get();

                        $completed_count = 0;
                        // Check if each outlet has been answered
                        foreach ($get_count_of_outlets as $outlet_data) {
                            //                        if($get_count_of_outlets > 0){
                            $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->id)
                                ->where('activity_id', $activity->id)
                                ->exists();
                            if ($check_if_answered) {
                                $completed_count++;
                            }
                        }

                        $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->id)
                            ->first();

                        // $min_value = $outlet_master_min_completion;
                        // $completion_type_value = $outlet_master_completion_type;
                        $min_value = 100;
                        $completion_type_value = '';
                        if ($check_if_distributor_row_setted) {
                            $min_value = $check_if_distributor_row_setted->min_completion;
                            $completion_type_value = $check_if_distributor_row_setted->completion_type;
                        }
                        // Add the current outlet data to the distributors array
                        $distributors_arr[] = [
                            'value' => $row_data_of_outlet_assigned_data->value,
                            'head_name' => $master_head_name,
                            'getDataOfRows' => $get_master_row,
                            'activity_id' => $activity->id,
                            'activity_name' => $activity->activity_name,
                            'template_id' => $template->id,
                            'project_id' => $project->id,
                            'project_template_id' => $project_temp_info->id,
                            'total_outlets' => count($get_count_of_outlets),
                            'total_outlet_answered' => $completed_count,
                            'main_header' => $dist_main_header,
                            'sub_header' => $dist_sub_header,
                            'min_completion' => $min_value,
                            'completion_type' => $completion_type_value
                        ];
                    }
                }
            }
        }

        $distributors_collection = collect($distributors_arr);
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $currentPageItems = $distributors_collection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedDistributors = new LengthAwarePaginator(
            $currentPageItems,
            $distributors_collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        //khushboo 16-04-25
        $showCloseAuditButton = false;
        // $isAuditClosed
        if ($type == 0) {

            $outletCompleteCount = 0;
            $outlet_temp_info_count = ProjectTemplate::where('project_id', $project->id)
                ->where('is_master', 0)
                ->count(); // get child template

            $currentOutletcompleted = 0;
            $totalOutletsData = 0;

            // dd(count($distributors_arr));
            foreach ($distributors_arr as $distributor_data) {
                $completed = 0;
                $totalOutletsData++;

                // if (isset($distributor_data['completion_type']) && $distributor_data['completion_type'] == 'Percentage') {
                //     if ($distributor_data['total_outlets'] > 0) {
                //         $dist_percent =
                //             ($distributor_data['total_outlet_answered'] /
                //                 $distributor_data['total_outlets']) *
                //             100;
                //         if ($dist_percent >= $distributor_data['min_completion']) {
                //             $completed = 1;
                //         }
                //     }
                // } else {
                //     if (
                //         isset($distributor_data['total_outlet_answered']) &&
                //         $distributor_data['total_outlet_answered'] >=
                //         $distributor_data['min_completion']
                //     ) {
                //         $completed = 1;
                //     }
                // }
                // if (isset($distributor_data['row_id']) && !empty($distributor_data['row_id'])) {
                //     $dataExists = ProjectMasterTemplates::where('row_id', $distributor_data['row_id'])
                //         ->where('completion_type', 'Number')->where('min_completion', 0)
                //         ->where('user_id', auth()->id())->exists();
                //     if ($dataExists) {
                //         $completed = 1;
                //     }
                // }
                if ($distributor_data['total_outlet_answered'] == $distributor_data['total_outlets']) {
                    $completed = 1;
                }
                if ($completed == 1) {
                    $currentOutletcompleted++;
                }
            }
            //check the total outlets that can be filled by auditor
            // dd($currentOutletcompleted, $totalOutletsData, (int) $outlet_master_min_completion);
            if ($totalOutletsData > 0 && $totalOutletsData > $outlet_master_min_completion && $totalOutletsData > $currentOutletcompleted) {
                if ($outlet_master_completion_type == 'Percentage') {
                    if ($totalOutletsData > 0) {
                        $dist_percent =
                            ($currentOutletcompleted /
                                $totalOutletsData) *
                            100;
                        if ($dist_percent >= $outlet_master_min_completion) {
                            $outletCompleteCount++;
                        }
                    }
                } else {
                    if (
                        $currentOutletcompleted >=
                        (int) $outlet_master_min_completion
                    ) {
                        $outletCompleteCount++;
                    }
                }
            }
            // dd($outletCompleteCount);
            if ($outletCompleteCount > 0) {
                $showCloseAuditButton = true;
            }
        }

        //khushboo 16-04-25
        // dd($isAuditClosed);
        // dd($distributors_arr);
        $currentUser = User::find($user->id);
        $currentUserROle = $currentUser->getRoleNames()->first();
        return view('masters.users.user_project_outlet_distributors', compact('showCloseAuditButton', 'distributors_arr', 'project', 'template', 'activity', 'paginatedDistributors', 'group_session_id', 'isAuditClosed', 'currentUserROle'));
    }


    //khushboo 16-04-25
    public function closeAuditData(Request $request)
    {
        // dd($request);
        try {

            $project_template_info = ProjectTemplate::where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_name_id)
                ->first();

            $distributorData = $request->distributorData;
            // dd($distributorData);
            foreach ($distributorData as $distributor_data) {
                // dd($distributor_data);

                if ((isset($distributor_data['getDataOfRows']) && !empty($distributor_data['getDataOfRows']))) {
                    foreach ($distributor_data['getDataOfRows'] as $rowdata) {
                        // dd($project_template_info->is_master);
                        if (!empty($rowdata)) {
                            // if ($project_template_info->is_master) {
                            ProjectMasterTemplates::updateOrCreate(
                                [
                                    'row_id' => $rowdata['row_id'],
                                    'project_template_id' => $project_template_info->id
                                ],
                                [
                                    'completion_type' => 'Number',
                                    'min_completion' => 0,
                                    'user_id' => auth()->id(), // Assuming you want to assign the current user
                                ]
                            );
                            // }
                        }
                    }
                }
                if (isset($distributor_data['row_id']) && !empty($distributor_data['row_id'])) {
                    ProjectMasterTemplates::updateOrCreate(
                        [
                            'row_id' => $distributor_data['row_id'],
                            'project_template_id' => $project_template_info->id
                        ],
                        [
                            'completion_type' => 'Number',
                            'min_completion' => 0,
                            'user_id' => auth()->id(), // Assuming you want to assign the current user
                        ]
                    );
                }
            }

            AuditorAssignedData::where('project_id', $request->project_id)
                ->where('template_name_id', $project_template_info->template_name_id)
                ->where('user_id', auth()->id())
                ->whereHas('dataAssign', function ($query) use ($request) {
                    $query->where('activity_id', $request->activity_id)
                        ->where('activity_group_id', $request->group_session_id);
                })
                ->update(['audit_closed' => 1]);

            return response()->json(['status' => true, 'message' => 'Audit Closed Successfully']);
        } catch (\Exception $e) {
            dd($e->getMessage());
            return response()->json(['status' => false, 'message' => 'Something went wrong']);
        }
    }

    //khushboo 16-04-25

    public function userProjectActivities($row_id, $status)
    {
        // dd($row_id, $status);
        if (empty($status) || $status == null) {
            $status = 0;
        }
        $user = Auth::user();
        $userAssignedActivities = [];
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
        $getHeadValues = DB::table('project_template_name_values_new')
            ->where('id', $row_id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateData->main_header}\"')) as head_value")
            )
            ->first();

        // dd($getHeadValues, $row_id, $projectTemplateData->main_header);

        $distributor_value = $getHeadValues->head_value;
        $project = Project::find($projectTemplateData->project_id);
        $project_master_template = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first();

        $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')
            ->where('project_id', $project->id)
            ->where('id', '!=', $project_master_template->id)
            ->get();

        //khushboo 05-07-25
        $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $projectTemplateData->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id');

        //        dd($distinct_data_assignIds);

        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
            ->whereIn("id", $distinct_data_assignIds)
            ->where('project_template_id', $projectTemplateData->id)
            ->where('project_id', $project->id)
            ->get();

        if ($data_assign_info->IsEmpty()) {
            $masterProjectTemplate = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                ->where('is_master', 1)->first();
            $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
                ->whereIn("id", $distinct_data_assignIds)
                ->where('project_template_id', $masterProjectTemplate->id)
                ->where('project_id', $project->id)
                ->get();
        }
        //khushboo 05-07-25

        //check if outlet assign also
        $with_data_check = 1;
        //        dd($projectTemplateData);
        if ($projectTemplateData->is_master == 1) {
            $childTemplateDataIds = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                ->where('is_master', 0)->pluck('id')->toArray();
            $data_assign_info_ids = $data_assign_info->where('is_outlet_assigned', 1)->pluck('id')->toArray();
            // dd($data_assign_info_ids);
            $checkoutletAssign = UserActivityDataAssign::where('user_id', $user->id)
                ->whereIn('project_template_id', $childTemplateDataIds)
                ->whereIn('data_assign_id', $data_assign_info_ids)
                ->exists();
            // dd($checkoutletAssign, $data_assign_info_ids);
            if ($data_assign_info_ids) {
                $emptyDataTemplateEixsts = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                    ->where('is_master', 0)
                    ->where('with_data', 0)
                    ->exists();
                // dd($emptyDataTemplateEixsts);
                if ($emptyDataTemplateEixsts) {
                    $with_data_check = 0;
                }
            }
        }

        //        dd($data_assign_info);
        foreach ($data_assign_info as $assigned_data) {
            $sequence = null;
            // dd($assigned_data);
            if (isset($assigned_data->activity_group_id)) {
                $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                    ->where('activity_group_id', $assigned_data->activity_group_id)
                    ->first();
                $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : '';
            }
            $group_name = $assigned_data->getActivityGroup ? $assigned_data->getActivityGroup->activity_group_name : null;
            $is_master_temp = $assigned_data->getProjectTemplate->is_master ? $assigned_data->getProjectTemplate->is_master : 0;
            $current_template_name = $assigned_data->templateName->template_name;
            $current_activity_name = $assigned_data->activityName->activity_name;
            $exists = false;
            foreach ($userAssignedActivities as $activity) {
                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                    $exists = true;
                    break; // Exit loop if a match is found
                }
            }

            $activityRequiredQuestions = Question::where('activity_id', $assigned_data->activity_id)
                // ->where('answer_type', 1)
                ->pluck('id')
                ->toArray();

            // Count how many questions were submitted for the original (sequence 0/null) submission
            $submittedcount = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $assigned_data->activity_id)
                ->whereIn('question_id', $activityRequiredQuestions)
                ->where('user_id', Auth::user()->id)
                ->where(function ($q) {
                    $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                })
                ->distinct('question_id')
                ->count('question_id');

            // Check if any answer exists at all
            $is_answered = $submittedcount > 0;

            // Check if subjective question exists
            $subjectiveExists = Question::where('activity_id', $assigned_data->activity_id)
                ->where('answer_type', 1)
                ->where('question_type', 'Subjective')
                ->exists();

            // Total required questions count (exclude Multi Response — they have no input field)
            $requiredCount = DB::table('questions')->where('activity_id', $assigned_data->activity_id)
                ->where('answer_type', 1)
                ->where('question_type', '!=', 'Multi Response')
                ->pluck('id')
                ->count();

            // Final check
            $check_if_answered = false;
            if ($is_answered) {
                // Special rule: If subjective exists → allow >= OR == (business logic?)
                if ($subjectiveExists) {
                    // If subjective exists, allow ANY submitted as long as count meets requirement
                    $check_if_answered = ($submittedcount >= $requiredCount);
                } else {
                    // Normal: submitted must be equal or greater
                    $check_if_answered = ($submittedcount >= $requiredCount);
                }
            }

            // --- Sendback check (original submission only, sequence 0/null) ---
            $isSentBack = false;
            if ($check_if_answered) {
                $latestAnswer = TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $assigned_data->activity_id)
                    ->where('user_id', Auth::user()->id)
                    ->where(function ($q) {
                        $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                    })
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestAnswer && $latestAnswer->status == 3) {
                    $isSentBack = true;
                    $check_if_answered = false; // treat as not completed so user can re-submit
                }
            }

            $otpVerificationDone = false;
            // dd($project);
            if ($project->is_otp_required == 1) {

                $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $assigned_data->activity_id)
                    ->whereIn('question_id', $activityRequiredQuestions)
                    ->where('user_id', Auth::user()->id)
                    ->orderBy('id', 'desc')
                    ->first();

                if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                    $otpVerificationDone = true;
                }
            }


            if (!$exists) {
                // Per-activity allow_repeat: check if this activity is in the add-on list
                $allowRepeat = false;
                if ($projectTemplateData->activity_add_on == 1) {
                    $addOnIds = json_decode($projectTemplateData->activity_add_on_activity_ids ?? '[]', true);
                    $allowRepeat = empty($addOnIds) || in_array((int)$assigned_data->activity_id, array_map('intval', $addOnIds));
                }

                // Fully closed: auditor clicked "Close" AND no sendback exists on any instance answer
                $isFullyClosed = false;
                if ($allowRepeat) {
                    $closeRecord = ActivityInstanceClose::where('row_id', $row_id)
                        ->where('activity_id', $assigned_data->activity_id)
                        ->where('user_id', $user->id)->exists();
                    if ($closeRecord) {
                        // Check if verifier has sent back any answer since closing
                        $sentBackExists = TempUserActivityAnswersData::where('row_id', $row_id)
                            ->where('activity_id', $assigned_data->activity_id)
                            ->where('user_id', $user->id)
                            ->where('status', 3)->exists();
                        $isFullyClosed = !$sentBackExists;
                    }
                }

                $repeatInstances = ActivityRepeatInstance::where('row_id', $row_id)
                    ->where('activity_id', $assigned_data->activity_id)
                    ->orderBy('activity_sequence')
                    ->get();

                $userAssignedActivities[] = [
                    'template_name_id' => $assigned_data->template_name_id,
                    'template_name' => $assigned_data->templateName->template_name,
                    'is_master' => $is_master_temp,
                    'activity_id' => $assigned_data->activity_id,
                    'activity_name' => $assigned_data->activityName->activity_name,
                    'group_id' => $assigned_data->activity_group_id,
                    'group_name' => $group_name,
                    'sequence' => $sequence,
                    'answer_submitted' => $check_if_answered,
                    'otpVerificationDone' => $otpVerificationDone,
                    'is_sent_back' => $isSentBack,
                    'allow_repeat' => $allowRepeat,
                    'is_fully_closed' => $isFullyClosed,
                    'repeat_instances' => $repeatInstances,
                    'project_data' => $project,
                ];
            }
        }
        // dd($userAssignedActivities, $status, $with_data_check);
        // if(count($userAssignedActivities) == 1 && $status == 0){
        //     return redirect()->route('user.project.row_id.activity', [
        //         'row_id' => $row_id,
        //         'activity' => $userAssignedActivities[0]['activity_id'],
        //     ]);
        // }
        $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();
        // dd($userAssignedActivities, $with_data_check);

        return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'row_id', 'status', 'distributor_value', 'with_data_check'));
    }

    public function userActivityInstancesList($row_id, Activity $activity, $group_info = null)
    {
        $user = Auth::user();
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
        $project = Project::find($projectTemplateData->project_id);

        $activityId = $activity->id;

        // Original submission status
        $activityRequiredQuestions = Question::where('activity_id', $activityId)->pluck('id')->toArray();
        $requiredCount = DB::table('questions')->where('activity_id', $activityId)
            ->where('answer_type', 1)->where('question_type', '!=', 'Multi Response')->count();
        $submittedCount = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)->where('user_id', $user->id)
            ->where(function ($q) { $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0); })
            ->distinct('question_id')->count('question_id');
        $originalSubmitted = $submittedCount >= $requiredCount && $submittedCount > 0;

        // Check sent back on original
        $originalSentBack = false;
        if ($originalSubmitted) {
            $latest = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $activityId)->where('user_id', $user->id)
                ->where(function ($q) { $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0); })
                ->orderBy('id', 'desc')->first();
            if ($latest && $latest->status == 3) {
                $originalSentBack = true;
                $originalSubmitted = false;
            }
        }

        // Repeat instances
        $repeatInstances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)->orderBy('activity_sequence')->get();

        // Build unified instance list
        $instances = [];
        $instances[] = [
            'label'          => 'Instance 1 (Original)',
            'activity_sequence' => 0,
            'submitted'      => $originalSubmitted,
            'sent_back'      => $originalSentBack,
            'pending'        => !$originalSubmitted && !$originalSentBack,
            'instance_id'    => null,
            'user_id'        => $user->id,
            'is_original'    => true,
        ];
        foreach ($repeatInstances as $inst) {
            $instSubmitted = $inst->status == 1;
            $instSentBack  = false;
            if ($instSubmitted) {
                $latestInst = TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $activityId)->where('user_id', $user->id)
                    ->where('activity_sequence', $inst->activity_sequence)
                    ->orderBy('id', 'desc')->first();
                if ($latestInst && $latestInst->status == 3) {
                    $instSentBack = true;
                    $instSubmitted = false;
                }
            }
            $instances[] = [
                'label'             => $inst->instance_label,
                'activity_sequence' => $inst->activity_sequence,
                'submitted'         => $instSubmitted,
                'sent_back'         => $instSentBack,
                'pending'           => !$instSubmitted && !$instSentBack,
                'instance_id'       => $inst->id,
                'user_id'           => $inst->user_id,
                'is_original'       => false,
            ];
        }

        return view('masters.users.activity_instances_list', compact(
            'instances', 'activity', 'project', 'row_id', 'group_info', 'projectTemplateData'
        ));
    }

    public function closeActivityInstances(Request $request)
    {
        $row_id      = $request->row_id;
        $activity_id = $request->activity_id;
        $user        = Auth::user();

        // Verify all instances are submitted before closing
        $repeatInstances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activity_id)->get();
        $pendingInstances = $repeatInstances->where('status', 0);
        if ($pendingInstances->count() > 0) {
            return response()->json(['success' => false, 'message' => 'All instances must be submitted before closing.']);
        }

        // Check original submission
        $activity = Activity::find($activity_id);
        $requiredCount = DB::table('questions')->where('activity_id', $activity_id)
            ->where('answer_type', 1)->where('question_type', '!=', 'Multi Response')->count();
        $submittedCount = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activity_id)->where('user_id', $user->id)
            ->where(function ($q) { $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0); })
            ->distinct('question_id')->count('question_id');

        if ($submittedCount < $requiredCount) {
            return response()->json(['success' => false, 'message' => 'The original activity answers are not yet submitted.']);
        }

        ActivityInstanceClose::updateOrCreate(
            ['row_id' => $row_id, 'activity_id' => $activity_id, 'user_id' => $user->id]
        );

        return response()->json(['success' => true]);
    }

    public function userProjectActivitiesOLDOct($row_id, $status)
    {
        $user = Auth::user();
        $userAssignedActivities = [];
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
        $getHeadValues = DB::table('project_template_name_values_new')
            ->where('id', $row_id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateData->main_header}\"')) as head_value")
            )
            ->first();

        $distributor_value = $getHeadValues->head_value;
        $project = Project::find($projectTemplateData->project_id);
        $project_master_template = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first();
        $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')
            ->where('project_id', $project->id)
            ->where('id', '!=', $project_master_template->id)
            ->get();

        //khushboo 05-07-25
        $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $projectTemplateData->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id');

        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
            ->whereIn("id", $distinct_data_assignIds)
            ->where('project_template_id', $projectTemplateData->id)
            ->where('project_id', $project->id)
            ->get();
        //khushboo 05-07-25

        foreach ($data_assign_info as $assigned_data) {
            $sequence = null;
            // dd($assigned_data);
            if (isset($assigned_data->activity_group_id)) {
                $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                    ->where('activity_group_id', $assigned_data->activity_group_id)
                    ->first();
                $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : '';
            }
            $group_name = $assigned_data->getActivityGroup ? $assigned_data->getActivityGroup->activity_group_name : null;
            $is_master_temp = $assigned_data->getProjectTemplate->is_master ? $assigned_data->getProjectTemplate->is_master : 0;
            $current_template_name = $assigned_data->templateName->template_name;
            $current_activity_name = $assigned_data->activityName->activity_name;
            $exists = false;
            foreach ($userAssignedActivities as $activity) {
                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                    $exists = true;
                    break; // Exit loop if a match is found
                }
            }

            if (!$exists) {
                $userAssignedActivities[] = [
                    'template_name_id' => $assigned_data->template_name_id,
                    'template_name' => $assigned_data->templateName->template_name,
                    'is_master' => $is_master_temp,
                    'activity_id' => $assigned_data->activity_id,
                    'activity_name' => $assigned_data->activityName->activity_name,
                    'group_id' => $assigned_data->activity_group_id,
                    'group_name' => $group_name,
                    'sequence' => $sequence,
                ];
            }
        }
        $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();
        return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'row_id', 'status', 'distributor_value'));
    }


    // this is to render the questions of the activity and take the answers from the user against the row ID
    public function row_data_activity($row_id, Activity $activity, ActivityGroup $group_info)
    {
        // 0 = original/default submission, >0 = a repeat instance (see activity_repeat_instances)
        // The query string value is encrypted by EncryptedUrlGenerator, so decrypt it first
        $raw_seq = request('activity_sequence', null);
        $activity_sequence = $raw_seq !== null ? (int) \App\Helpers\EncryptHelper::decrypt($raw_seq) : 0;

        $repeat_instance = null;
        if ($activity_sequence > 0) {
            $repeat_instance = ActivityRepeatInstance::where('row_id', $row_id)
                ->where('activity_id', $activity->id)
                ->where('activity_sequence', $activity_sequence)
                ->first();
        }

        $row_data = ProjectTemplateNameValuesNew::find($row_id);
        $getjsondata = json_decode($row_data->template_data_json);
        $template_name_values = [];
        foreach ($getjsondata as $key => $data) {
            $data = TemplateNameHead::find($key);
            $value = $data;
            $template_name_values[] = [
                'template_head_name' => $data->template_head_name ?? "",
                'value' => $data
            ];
        }

        $project_template = ProjectTemplate::with('getProject')->where('id', $row_data->project_template_id)->first();
        $project_id = $project_template->getProject->id;

        // Determine whether this activity supports repeat instances
        $addOnActivityIds = json_decode($project_template->activity_add_on_activity_ids ?? '[]', true);
        $allow_repeat = $project_template->activity_add_on == 1 &&
            (empty($addOnActivityIds) || in_array((int) $activity->id, array_map('intval', $addOnActivityIds)));

        // Load all repeat instances for nav bar
        $all_instances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activity->id)
            ->orderBy('activity_sequence')
            ->get();

        // Check if original (sequence 0) is submitted
        $original_submitted = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activity->id)
            ->where('user_id', Auth::id())
            ->where(function ($q) { $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0); })
            ->exists();

        // Check if this activity is fully closed
        $is_closed = ActivityInstanceClose::where('row_id', $row_id)
            ->where('activity_id', $activity->id)
            ->where('user_id', Auth::id())
            ->exists();

        //        dd($template_name_values);
        $related_questions = Question::with([
            'getOptions',
            'getSubjects.getOptions',
            // Level 1 sub-questions
            'subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.getSubjects.getOptions',
            // Level 2 sub-questions (Outlet inside Outlet)
            'subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.subQuestions.childQuestion.getSubjects.getOptions',
            // Level 3 sub-questions
            'subQuestions.childQuestion.subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
        ])->where('activity_id', $activity->id)->orderBy('question_sequence')->get();

        // BFS: collect ALL sub-question child IDs across all levels
        // so they are skipped in the main question render loop
        $sub_question_child_ids = [];
        $toProcess = $related_questions->pluck('id')->toArray();
        while (!empty($toProcess)) {
            $childIds = QuestionSubQuestion::whereIn('parent_question_id', $toProcess)
                ->pluck('child_question_id')->toArray();
            $newIds = array_diff($childIds, $sub_question_child_ids);
            if (empty($newIds))
                break;
            $sub_question_child_ids = array_merge($sub_question_child_ids, $newIds);
            $toProcess = array_values($newIds);
        }

        $parent_questions_ids = Question::where('is_parent', 1)->pluck('id')->toArray();
        //check if answer already submitted 
        // $check_submitted_data = TempUserActivityAnswersData::where('row_id', $row_id)->whereIn('question_id', $parent_questions_ids)->where('status', 0)->get();
        // dd($check_submitted_data->count(), $activity->id, $row_id);
        // dd($check_submitted_data);
        // if(!$check_submitted_data->isEmpty()){
        // return redirect()->back()->with('error', "Audit already completed.");
        // }

        //check if answer already submitted and sendback
        if ($activity_sequence > 0) {
            // Repeat instance: prefill with whatever was already saved for this instance
            $answer_data = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $activity->id)
                ->where('activity_sequence', $activity_sequence)
                ->get();
        } else {
            // Original instance: prefill with any previously saved answers (sent-back, submitted, etc.)
            $answer_data = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $activity->id)
                ->where(function ($q) {
                    $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                })
                ->get();
        }

        // True only when verifier has sent this specific instance back for correction (status = 3)
        $is_sent_back = $answer_data->contains('status', 3);

        $instance_common = compact('row_data', 'related_questions', 'sub_question_child_ids', 'group_info', 'template_name_values', 'project_id', 'activity_sequence', 'repeat_instance', 'allow_repeat', 'all_instances', 'original_submitted', 'is_closed', 'activity', 'is_sent_back');
        if (!$answer_data->isEmpty()) {
            return view('masters.users.activity_questions', array_merge($instance_common, compact('answer_data')));
        } else {
            return view('masters.users.activity_questions', $instance_common);
        }
    }

    /**
     * Create a new "repeat instance" for an activity on a given data row, so the
     * user can submit a fresh set of answers (same questions) under a new label.
     */
    public function add_activity_instance(Request $request)
    {
        $request->validate([
            'row_id' => 'required|integer',
            'activity_id' => 'required|integer',
            'instance_label' => 'required|string|max:255',
            'group_id' => 'nullable|integer',
        ]);

        $nextSequence = (int) ActivityRepeatInstance::where('row_id', $request->row_id)
            ->where('activity_id', $request->activity_id)
            ->max('activity_sequence') + 1;

        ActivityRepeatInstance::create([
            'row_id' => $request->row_id,
            'activity_id' => $request->activity_id,
            'activity_sequence' => $nextSequence,
            'instance_label' => $request->instance_label,
            'user_id' => Auth::id(),
            'status' => 0,
        ]);

        return redirect()->route('user.project.row_id.activity', [
            'row_id' => $request->row_id,
            'activity' => $request->activity_id,
            'group_info' => $request->group_id,
            'activity_sequence' => $nextSequence,
        ])->with('message', 'New activity instance created. Please fill in the answers.');
    }

    /**
     * Remove a repeat instance that the current user created and has not
     * yet submitted answers for. The original (sequence 0) submission can
     * never be removed via this method.
     */
    public function delete_activity_instance(Request $request)
    {
        $request->validate([
            'instance_id' => 'required|integer',
        ]);

        $instance = ActivityRepeatInstance::where('id', $request->instance_id)
            ->where('user_id', Auth::id())
            ->where('status', 0)
            ->first();

        if (!$instance) {
            return redirect()->back()->with('error', 'This instance can no longer be removed.');
        }

        TempUserActivityAnswersData::where('row_id', $instance->row_id)
            ->where('activity_id', $instance->activity_id)
            ->where('activity_sequence', $instance->activity_sequence)
            ->delete();

        $instance->delete();

        return redirect()->back()->with('message', 'Instance removed successfully.');
    }

    // this is to create the directory in the assets
    public function checkAndCreateDirectory($directory)
    {
        $publicPath = public_path($directory);
        // Check if the directory doesn't exist
        if (!File::exists($publicPath)) {
            // Create the directory
            File::makeDirectory($publicPath, 0755, true, true);
        }
    }




    public function row_activity_answers_working(Request $request)
    {
        DB::beginTransaction();
        try {
            // Check location
            if (empty($request->latitude) && empty($request->longitude)) {
                return redirect()->back()->withInput('error', "Location is required for answer submission.");
            }

            $userId = Auth::id();
            $last_sequence = (int) TempUserActivityAnswersData::max('same_answer_id') + 1;

            // Get project details
            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                return redirect()->back()->withInput('error', "Project Template Data Not Found");
            }

            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $otp_required = $projectTemp->getProjectTemplateData->getProject->is_otp_required == 1;

            // Prepare directories
            $baseDirectory = 'activityAnswerImages/';
            $projectDirectory = $baseDirectory . $projectName . '/';
            $this->checkAndCreateDirectory($projectDirectory);

            $currentMonthYear = Carbon::now()->format('FY');
            $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            $latitude = $request->latitude ?? null;
            $longitude = $request->longitude ?? null;
            $userDetails = User::find($userId);

            // Exclude non-question keys
            $excludedKeys = ['_token', 'user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude', 'save', '_method'];
            $questionAnswers = collect($request->all())->except($excludedKeys);

            // Allowed bind attributes for subjective questions
            $allowedBindAttributes = [
                'video',
                'audio',
                'dropdown',
                'multiselect',
                'image',
                'date',
                'datetime',
                'yesno',
                'fileupload',
                'freetext',
                'location'
            ];

            // Get submitted question IDs
            $submittedQuestionIds = $questionAnswers->keys()
                ->map(function ($key) {
                    preg_match('/^(\d+)/', (string) $key, $matches);
                    return isset($matches[1]) ? (int) $matches[1] : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Validate required questions
            $requiredQuestions = Question::where('activity_id', $request->activity_id)
                ->where('answer_type', 1)
                ->get();

            $requiredGroupedByType = $requiredQuestions->groupBy('question_type');
            $missingTypes = [];
            foreach ($requiredGroupedByType as $type => $questions) {
                $questionIds = $questions->pluck('id')->toArray();
                $intersection = array_intersect($submittedQuestionIds, $questionIds);
                if (empty($intersection)) {
                    $missingTypes[] = $type;
                }
            }

            if (!empty($missingTypes)) {
                return redirect()->back()->with('error', "Some required question types are missing from the submission.");
            }

            $imagesToProcess = [];

            foreach ($questionAnswers as $rawKey => $rawValue) {
                // Parse key to get question ID and attribute
                preg_match('/^(\d+)(.*)$/', (string) $rawKey, $parts);
                if (empty($parts[1])) {
                    continue;
                }

                $questionId = (int) $parts[1];
                $suffix = trim((string) ($parts[2] ?? ''));
                $attribute = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $suffix));

                // Get question
                $question = Question::find($questionId);
                if (!$question) {
                    continue;
                }

                // Initialize variables
                $user_answer = null;
                $file_path = null;
                $isFile = false;

                // Handle file uploads
                if ($request->hasFile($rawKey)) {
                    $file = $request->file($rawKey);
                    $isFile = true;

                    // Determine file validation rules
                    $rules = ['file_input' => 'required|file'];
                    $customMessages = [];

                    if ($question->question_type === 'Image' || $attribute === 'image') {
                        $rules['file_input'] .= '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                        $customMessages['file_input.image'] = "Only image files are allowed.";
                    } elseif ($question->question_type === 'File Upload' || $attribute === 'fileupload') {
                        $rules['file_input'] .= '|mimes:pdf,xls,xlsx,doc,docx,jpg,jpeg,png,webp|max:51200';
                        $customMessages['file_input.mimes'] = "Invalid file format. Allowed: PDF, Excel, Word, Images";
                    } elseif ($question->question_type === 'Audio' || $attribute === 'audio') {
                        $rules['file_input'] .= '|mimes:mp3,wav,ogg,m4a|max:51200';
                        $customMessages['file_input.mimes'] = "Invalid audio format. Allowed: MP3, WAV, OGG, M4A";
                    } elseif ($question->question_type === 'Video' || $attribute === 'video') {
                        $rules['file_input'] .= '|mimes:mp4,mov,avi,wmv,flv,webm|max:512000';
                        $customMessages['file_input.mimes'] = "Invalid video format. Allowed: MP4, MOV, AVI, WMV, FLV, WEBM";
                    } else {
                        $rules['file_input'] .= '|max:51200';
                    }

                    // Validate file
                    $validator = Validator::make(['file_input' => $file], $rules, $customMessages);
                    if ($validator->fails()) {
                        DB::rollBack();
                        return redirect()->back()->with(
                            'error',
                            "Invalid file for question: {$question->question}. " .
                            implode(' ', $validator->errors()->all())
                        );
                    }

                    // Generate unique filename with original extension
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    $safeName = Str::slug($originalName);
                    $fileName = $safeName . '_' . uniqid() . '.' . $extension;

                    $full_file_path = $projectMonthYearDirectory . '/' . $fileName;

                    // Move file to directory
                    $file->move(public_path($projectMonthYearDirectory), $fileName);
                    @chmod(public_path($full_file_path), 0777);

                    $user_answer = $full_file_path;
                    $file_path = $full_file_path;
                } else {
                    // Handle non-file inputs
                    if (is_array($rawValue)) {
                        $user_answer = implode(',', array_filter($rawValue));
                    } else {
                        $user_answer = trim((string) $rawValue);
                    }

                    // Format based on type
                    if (($question->question_type === 'Date' || $attribute === 'date') && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif (($question->question_type === 'Date & Time' || $attribute === 'datetime') && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y H:i');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif ($question->question_type === 'Yes / No' || $attribute === 'yesno') {
                        $normalized = strtolower($user_answer);
                        if (in_array($normalized, ['yes', '1', 'true', 'y'])) {
                            $user_answer = 'Yes';
                        } elseif (in_array($normalized, ['no', '0', 'false', 'n'])) {
                            $user_answer = 'No';
                        }
                    } elseif (
                        ($question->question_type === 'Audio' || $question->question_type === 'Video') &&
                        (str_starts_with($user_answer, 'data:video/') || str_starts_with($user_answer, 'data:audio/'))
                    ) {

                        $isFile = true;

                        // Extract the actual format from the data URL
                        // data:video/mp4;base64,... OR data:video/webm;base64,...
                        preg_match('/^data:(video|audio)\/([^;]+);/', $user_answer, $matches);

                        $fileType = $matches[1] ?? 'video'; // 'video' or 'audio'
                        $detectedExtension = $matches[2] ?? 'webm';

                        // Clean up extension (remove any parameters)
                        $detectedExtension = explode(';', $detectedExtension)[0];
                        $detectedExtension = explode('+', $detectedExtension)[0];

                        // Map common extensions
                        $extensionMap = [
                            'mp4' => 'mp4',
                            'webm' => 'webm',
                            'ogg' => 'ogv',
                            'mov' => 'mov',
                            'quicktime' => 'mov',
                            'x-matroska' => 'mkv',
                            'mpeg' => 'mp3',
                            'wav' => 'wav',
                            'x-wav' => 'wav',
                            'x-pn-wav' => 'wav',
                            'mp3' => 'mp3',
                            'm4a' => 'm4a',
                            'x-m4a' => 'm4a',
                            'aac' => 'aac',
                        ];

                        $extension = $extensionMap[$detectedExtension] ?? $detectedExtension;

                        // Validate size
                        $base64Content = explode(',', $user_answer)[1] ?? '';
                        $size = (strlen($base64Content) * 3) / 4; // Approximate size

                        if ($fileType === 'video' && $size > 52428800) { // 50MB
                            DB::rollBack();
                            return redirect()->back()->with(
                                'error',
                                "Video file is too large. Maximum size is 50MB."
                            );
                        }

                        if ($fileType === 'audio' && $size > 5242880) { // 5MB
                            DB::rollBack();
                            return redirect()->back()->with(
                                'error',
                                "Audio file is too large. Maximum size is 5MB."
                            );
                        }

                        // Decode and save
                        $base64Data = explode(',', $user_answer)[1] ?? '';
                        if (empty($base64Data)) {
                            DB::rollBack();
                            return redirect()->back()->with(
                                'error',
                                "Invalid {$fileType} data for question: {$question->question}"
                            );
                        }

                        $fileData = base64_decode($base64Data);
                        if ($fileData === false) {
                            DB::rollBack();
                            return redirect()->back()->with(
                                'error',
                                "Failed to decode {$fileType} data for question: {$question->question}"
                            );
                        }

                        // Generate filename with correct extension
                        $timestamp = time();
                        $randomStr = substr(md5(uniqid()), 0, 8);
                        $fileName = "{$fileType}_{$timestamp}_{$randomStr}.{$extension}";
                        $full_file_path = $projectMonthYearDirectory . '/' . $fileName;
                        $absolutePath = public_path($full_file_path);

                        // Ensure directory exists
                        if (!is_dir(dirname($absolutePath))) {
                            mkdir(dirname($absolutePath), 0777, true);
                        }

                        // Save the file
                        $bytesWritten = file_put_contents($absolutePath, $fileData);
                        if ($bytesWritten === false) {
                            DB::rollBack();
                            return redirect()->back()->with(
                                'error',
                                "Failed to save {$fileType} file for question: {$question->question}"
                            );
                        }

                        // Set proper permissions
                        chmod($absolutePath, 0644);

                        $user_answer = $full_file_path;
                        $file_path = $full_file_path;
                    }
                }

                // Handle Subjective questions with binded attributes
                if ($question->question_type === 'Subjective' && !empty($attribute) && in_array($attribute, $allowedBindAttributes)) {

                    // For binded attributes, we need to store them differently
                    // Since we don't have an attribute column, we'll encode the attribute in user_answer field
                    // or store in a separate identifier

                    // Create a unique identifier for binded attribute
                    $bindedIdentifier = "{$questionId}_{$attribute}";

                    // Check if this binded attribute already exists
                    $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where('question_id', $questionId)
                        ->where(function ($q) use ($attribute, $bindedIdentifier) {
                            // Try to identify binded answers
                            $q->where('user_answer', 'LIKE', "%{$attribute}%")
                                ->orWhere('user_answer', 'LIKE', "%{$bindedIdentifier}%");
                        })
                        ->first();

                    if ($existing) {
                        // Update existing
                        $existing->update([
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'updated_at' => now(),
                        ]);
                        $answer = $existing;
                    } else {
                        // Create new - store attribute info in user_answer if needed
                        // Or create a separate record with a marker
                        $answer = TempUserActivityAnswersData::create([
                            'user_id' => $userId,
                            'row_id' => $request->row_id,
                            'activity_id' => $request->activity_id,
                            'activity_group_name_id' => $request->group_id ?? 0,
                            'question_id' => $questionId,
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);
                    }

                    // Queue image for processing if it's an image
                    if ($attribute === 'image' && $file_path) {
                        $imagesToProcess[] = [
                            'file_path' => public_path($file_path),
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'answer' => $answer,
                            'directory' => $projectMonthYearDirectory,
                            'user_id' => $userDetails->id,
                        ];
                    }

                    continue; // Move to next question
                }

                // Handle main question answers (non-binded)
                // Remove WHERE clause checking for attribute column
                $query = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where('question_id', $questionId);

                // Handle Subjective questions (main answer)
                if ($question->question_type === 'Subjective') {
                    $existing = $query->first();

                    if (!$existing) {
                        // Create first subjective answer
                        $answer = TempUserActivityAnswersData::create([
                            'user_id' => $userId,
                            'row_id' => $request->row_id,
                            'activity_id' => $request->activity_id,
                            'activity_group_name_id' => $request->group_id ?? 0,
                            'question_id' => $questionId,
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);
                    } else {
                        // Check bracket logic for subjective answers
                        $subjectiveAnswer = $existing->user_answer ?? '';
                        $cleanedAnswer = preg_replace('/\s+/', ' ', trim($subjectiveAnswer));
                        preg_match_all('/\(([^)]+)\)/', $cleanedAnswer, $matches);
                        $bracketCount = count($matches[0]);

                        if ($bracketCount === 1) {
                            // Create additional subjective answer
                            $answer = TempUserActivityAnswersData::create([
                                'user_id' => $userId,
                                'row_id' => $request->row_id,
                                'activity_id' => $request->activity_id,
                                'activity_group_name_id' => $request->group_id ?? 0,
                                'question_id' => $questionId,
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                            ]);
                        } else {
                            // Update existing
                            $existing->update([
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'updated_at' => now(),
                            ]);
                            $answer = $existing;
                        }
                    }
                } else {
                    // Handle non-subjective questions
                    $existing = $query->first();

                    if (!$existing) {
                        $answer = TempUserActivityAnswersData::create([
                            'user_id' => $userId,
                            'row_id' => $request->row_id,
                            'activity_id' => $request->activity_id,
                            'activity_group_name_id' => $request->group_id ?? 0,
                            'question_id' => $questionId,
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);
                    } else {
                        $existing->update([
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'updated_at' => now(),
                        ]);
                        $answer = $existing;
                    }
                }

                // Queue image processing for Image questions
                if (($question->question_type === 'Image' || $attribute === 'image') && $file_path) {
                    $imagesToProcess[] = [
                        'file_path' => public_path($file_path),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'answer' => $answer ?? null,
                        'directory' => $projectMonthYearDirectory,
                        'user_id' => $userDetails->id,
                    ];
                }
            }

            DB::commit();

            // Process images in background
            if (!empty($imagesToProcess)) {
                dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
            }

            $message = "Activity Answer submitted successfully";

            // Handle redirects with OTP check
            if ($otp_required) {
                $message = $message . ". Please verify OTP";
                return redirect()->route('otp_verification_page', [
                    'row_id' => $request->row_id,
                    'activity' => $request->activity_id,
                ])->with('message', $message);
            }

            // Redirect based on session
            if (Session::has('project_outlet_activity')) {
                $params = Session::get('project_outlet_activity');
                return redirect(route('user.project.distributor.outlets', $params))
                    ->with('message', $message);
            }

            if (Session::has('project_dist_activity')) {
                $params = Session::get('project_dist_activity');
                return redirect(route('user.project_master.data', $params))
                    ->with('message', $message);
            }

            return redirect()->back()->with('message', $message);
        } catch (\Exception $e) {
            dd($e->getMessage());
            DB::rollBack();
            Log::error('Activity submit error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['_token'])
            ]);
            return redirect()->back()->with('error', 'Error submitting answers: ' . $e->getMessage());
        }
    }


    /**
     * AJAX endpoint: pre-upload a single image for a multi-image question.
     * Called immediately when the user selects a file so the binary transfer
     * happens while the user is still filling the form (not at submit time).
     * The geo-tagged path is returned and stored in a hidden input; the form
     * submit only sends small text payloads.
     */
    public function upload_activity_image_temp(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'image'    => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:51200',
                'row_id'   => 'required',
                'latitude' => 'nullable|numeric',
                'longitude'=> 'nullable|numeric',
            ]);

            $rowId = $request->row_id;
            $projectTemp = \App\Models\ProjectTemplateNameValuesNew::find($rowId);
            if (!$projectTemp) {
                return response()->json(['error' => 'Project not found'], 404);
            }

            $projectName         = $projectTemp->getProjectTemplateData->getProject->project_name;
            $dir                 = 'activityAnswerImages/' . $projectName . '/' . now()->format('FY');
            $this->checkAndCreateDirectory($dir);

            $file     = $request->file('image');
            $safeName = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $fileName = $safeName . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $fileName);
            @chmod(public_path("$dir/$fileName"), 0777);

            $rawAbsPath = public_path("$dir/$fileName");
            $latitude   = $request->latitude;
            $longitude  = $request->longitude;

            // Geo-tag synchronously here so submit only receives text paths
            if ($latitude && $longitude) {
                $geoPath = $this->convertToGeoImagePath($rawAbsPath, $latitude, $longitude, $dir, now());
            } else {
                $geoPath = "$dir/$fileName";
            }

            return response()->json(['path' => $geoPath]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('upload_activity_image_temp error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function row_activity_answers(Request $request)
    {
        DB::beginTransaction();
        // $params = Session::get('project_outlet_activity');
        // dd($request->all(), $params);
        try {
            // Check location
            if (empty($request->latitude) && empty($request->longitude)) {
                // return redirect()->back()->withInput('error', "Location is required for answer submission.");
                return redirect()->back()->with('error', "Location is required for answer submission.")->withInput();
            }


            $userId = Auth::id();
            $last_sequence = (int) TempUserActivityAnswersData::max('same_answer_id') + 1;

            // 0 = original/default submission, >0 = a repeat instance (see activity_repeat_instances)
            // Route param is most reliable (cannot be overridden by POST body or query string)
            $activitySequence = (int) $request->route('activity_sequence', 0);

            // Get project details
            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                // return redirect()->back()->withInput('error', "Project Template Data Not Found");
                return redirect()->back()->with('error', "Project Template Data Not Found")->withInput();
            }


            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $otp_required = $projectTemp->getProjectTemplateData->getProject->is_otp_required == 1;

            // Prepare directories
            $baseDirectory = 'activityAnswerImages/';
            $projectDirectory = $baseDirectory . $projectName . '/';
            $this->checkAndCreateDirectory($projectDirectory);

            $currentMonthYear = Carbon::now()->format('FY');
            $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            $latitude = $request->latitude ?? null;
            $longitude = $request->longitude ?? null;
            $userDetails = User::find($userId);

            // Exclude non-question keys
            $excludedKeys = ['_token', 'user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude', 'save', '_method', 'activity_sequence'];
            $questionAnswers = collect($request->all())->except($excludedKeys);

            // Allowed bind attributes for subjective questions
            $allowedBindAttributes = [
                'video',
                'audio',
                'dropdown',
                'multiselect',
                'image',
                'date',
                'datetime',
                'yesno',
                'fileupload',
                'freetext',
                'location'
            ];

            // Get submitted question IDs (all keys that start with a question ID)
            $submittedQuestionIds = $questionAnswers->keys()
                ->map(function ($key) {
                    preg_match('/^(\d+)/', (string) $key, $matches);
                    return isset($matches[1]) ? (int) $matches[1] : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // ── Build answered question IDs (non-empty values only) ────────────
            // $submittedQuestionIds includes ALL keys even with empty values.
            // We need a separate set of IDs where the user actually provided a value.
            $answeredQuestionIds = [];

            foreach ($questionAnswers as $key => $value) {
                preg_match('/^(\d+)/', (string) $key, $matches);
                if (!isset($matches[1])) continue;
                $qid = (int) $matches[1];

                if (is_array($value)) {
                    // Multi-select: at least one non-empty option
                    $nonEmpty = array_filter($value, fn($v) => $v !== null && trim((string) $v) !== '');
                    if (!empty($nonEmpty)) {
                        $answeredQuestionIds[] = $qid;
                    }
                } elseif ($value !== null && trim((string) $value) !== '') {
                    $answeredQuestionIds[] = $qid;
                }
            }

            // File uploads (Image, File Upload, Audio, Video) are not in $request->all()
            foreach ($request->files->all() as $fileKey => $fileValue) {
                preg_match('/^(\d+)/', (string) $fileKey, $matches);
                if (!isset($matches[1])) continue;
                if ($fileValue !== null) {
                    $answeredQuestionIds[] = (int) $matches[1];
                }
            }

            $answeredQuestionIds = array_unique($answeredQuestionIds);

            // ── Validate required questions ────────────────────────────────────
            // Rules:
            //  1. Parent/standalone questions (is_parent=1): validate by answer_type
            //  2. Sub-question children (via question_sub_questions, always visible): validate by answer_type
            //  3. Old-style conditional children (is_parent=0 with parent_question_id): NOT validated
            //     server-side — we cannot know if their parent trigger was met
            //  4. Outlet type questions: NEVER validated — they have no input field

            $activityQuestionIds = Question::where('activity_id', $request->activity_id)
                ->pluck('id')->toArray();

            // Required parent/standalone questions
            $requiredIds = Question::whereIn('id', $activityQuestionIds)
                ->where('answer_type', 1)
                ->where('question_type', '!=', 'Multi Response')
                ->where('is_parent', 1)
                ->pluck('id')->toArray();

            // BFS: collect all sub-question child IDs across ALL nesting levels
            $allSubChildIds = [];
            $toProcess = $activityQuestionIds;
            while (!empty($toProcess)) {
                $childBatch = QuestionSubQuestion::whereIn('parent_question_id', $toProcess)
                    ->pluck('child_question_id')->toArray();
                $newIds = array_diff($childBatch, $allSubChildIds);
                if (empty($newIds)) break;
                $allSubChildIds = array_merge($allSubChildIds, $newIds);
                $toProcess = array_values($newIds);
            }

            // Required sub-question children at all levels that are ALWAYS visible
            // (skip those with parent_question_id set — those are conditional, only shown on trigger)
            $requiredSubIds = Question::whereIn('id', $allSubChildIds)
                ->where('answer_type', 1)
                ->where('question_type', '!=', 'Multi Response')
                ->whereNull('parent_question_id')
                ->pluck('id')->toArray();

            // Check against $answeredQuestionIds (not just submitted keys — values must be non-empty)
            $allRequiredIds = array_unique(array_merge($requiredIds, $requiredSubIds));
            $missingIds = array_diff($allRequiredIds, $answeredQuestionIds);

            if (!empty($missingIds)) {
                // Look up the question texts for the missing IDs to give a clear message
                $missingQuestions = Question::whereIn('id', $missingIds)->pluck('question')->toArray();
                $missingList = implode(', ', $missingQuestions);
                return redirect()->back()
                    ->with('error', "Please fill all required (*) questions before submitting. Missing: {$missingList}")
                    ->withInput();
            }

            $imagesToProcess = [];

            // Helper function to decode and save base64 media
            $decodeAndSaveMediaBase64 = function (string $dataUrl, string $attributeHint, string $projectMonthYearDirectory, &$file_path) {
                $attributeHint = strtolower($attributeHint) === 'audio' ? 'audio' : 'video';

                // Check if this is already a file path (not base64)
                if (!str_starts_with($dataUrl, 'data:')) {
                    $file_path = $dataUrl;
                    return $dataUrl;
                }

                if (!preg_match('/^data:(video|audio)\/([^;,]+)/i', $dataUrl, $typeMatches)) {
                    return 'ERROR:Invalid media data.';
                }

                $mediaType = strtolower($typeMatches[1]);
                $base64Marker = 'base64,';
                $base64Pos = strripos($dataUrl, $base64Marker);

                if ($base64Pos === false) {
                    return 'ERROR:Invalid media encoding.';
                }

                $base64Data = substr($dataUrl, $base64Pos + strlen($base64Marker));
                $base64Data = preg_replace('/\s+/', '', $base64Data);
                $base64Data = str_replace(' ', '+', $base64Data);

                $fileData = base64_decode($base64Data, true);

                if ($fileData === false || strlen($fileData) < 100) {
                    return 'ERROR:Unable to decode media file.';
                }

                $sizeBytes = strlen($fileData);

                if ($mediaType === 'video' && $sizeBytes > 524288000) {
                    return 'ERROR:Video file is too large. Maximum size is 500MB.';
                }

                if ($mediaType === 'audio' && $sizeBytes > 52428800) {
                    return 'ERROR:Audio file is too large. Maximum size is 50MB.';
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->buffer($fileData) ?: '';

                $extensionMap = [
                    'video/mp4' => 'mp4',
                    'video/webm' => 'webm',
                    'video/quicktime' => 'mov',
                    'video/x-msvideo' => 'avi',
                    'video/x-ms-wmv' => 'wmv',
                    'video/x-matroska' => 'mkv',
                    'video/3gpp' => '3gp',
                    'audio/mpeg' => 'mp3',
                    'audio/mp3' => 'mp3',
                    'audio/wav' => 'wav',
                    'audio/x-wav' => 'wav',
                    'audio/ogg' => 'ogg',
                    'audio/webm' => 'webm',
                    'audio/mp4' => 'm4a',
                    'audio/aac' => 'aac',
                ];

                $extension = $extensionMap[$detectedMime] ?? null;

                if (!$extension) {
                    $mimeSubtype = strtolower($typeMatches[2] ?? '');
                    $mimeSubtype = preg_replace('/[^a-z0-9]/', '', $mimeSubtype);

                    $fallbackMap = [
                        'mp4' => 'mp4',
                        'webm' => 'webm',
                        'ogg' => 'ogv',
                        'mov' => 'mov',
                        'quicktime' => 'mov',
                        'avi' => 'avi',
                        'wmv' => 'wmv',
                        'mkv' => 'mkv',
                        'mpeg' => 'mp3',
                        'mp3' => 'mp3',
                        'wav' => 'wav',
                        'm4a' => 'm4a',
                        'aac' => 'aac',
                    ];

                    $extension = $fallbackMap[$mimeSubtype] ?? ($mediaType === 'audio' ? 'webm' : 'mp4');
                }

                $fileName = $mediaType . '_' . time() . '_' . Str::random(10) . '.' . $extension;
                $fullFilePath = $projectMonthYearDirectory . '/' . $fileName;
                $absolutePath = public_path($fullFilePath);

                if (!is_dir(dirname($absolutePath))) {
                    mkdir(dirname($absolutePath), 0777, true);
                }

                if (file_put_contents($absolutePath, $fileData) === false) {
                    return 'ERROR:Failed to save media file.';
                }

                @chmod($absolutePath, 0644);
                $file_path = $fullFilePath;
                return $fullFilePath;
            };

            $processedQuestionIds = []; // track every question_id we touched or skipped

            foreach ($questionAnswers as $rawKey => $rawValue) {


                // Parse key to get question ID and attribute
                preg_match('/^(\d+)(.*)$/', (string) $rawKey, $parts);
                if (empty($parts[1])) {
                    continue;
                }

                $questionId = (int) $parts[1];
                $suffix = trim((string) ($parts[2] ?? ''));
                $attribute = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $suffix));

                // Get question
                $question = Question::find($questionId);
                if (!$question) {
                    continue;
                }

                $processedQuestionIds[] = $questionId;

                // Initialize variables
                $user_answer = null;
                $file_path = null;
                $isFile = false;

                // Handle file uploads
                if ($request->hasFile($rawKey)) {
                    $file = $request->file($rawKey);
                    $isFile = true;

                    // Determine file validation rules
                    $rules = ['file_input' => 'required|file'];
                    $customMessages = [];

                    if ($question->question_type === 'Image' || $attribute === 'image') {
                        $rules['file_input'] .= '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:51200';
                    } elseif ($question->question_type === 'File Upload' || $attribute === 'fileupload') {
                        $rules['file_input'] .= '|mimes:pdf,xls,xlsx,doc,docx,jpg,jpeg,png,webp|max:51200';
                    } elseif ($question->question_type === 'Audio' || $attribute === 'audio') {
                        $rules['file_input'] .= '|mimes:mp3,wav,ogg,m4a,aac,webm|max:51200';
                    } elseif ($question->question_type === 'Video' || $attribute === 'video') {
                        $rules['file_input'] .= '|mimes:mp4,mov,avi,wmv,flv,webm,mkv,3gp|max:512000';
                    } else {
                        $rules['file_input'] .= '|max:51200';
                    }

                    // Validate file
                    $validator = Validator::make(['file_input' => $file], $rules, $customMessages);
                    if ($validator->fails()) {
                        DB::rollBack();
                        return redirect()->back()->with(
                            'error',
                            "Invalid file for question: {$question->question}. " .
                            implode(' ', $validator->errors()->all())
                        );
                    }

                    // Generate unique filename
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    $safeName = Str::slug($originalName);
                    $fileName = $safeName . '_' . uniqid() . '.' . $extension;
                    $fullFilePath = $projectMonthYearDirectory . '/' . $fileName;

                    // Delete old file if exists
                    // $existingRecord = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    //     ->where('activity_id', $request->activity_id)
                    //     ->where('question_id', $questionId)
                    //     ->first();

                    // if ($existingRecord && $existingRecord->user_answer) {
                    //     $oldFilePath = public_path($existingRecord->user_answer);
                    //     if (file_exists($oldFilePath)) {
                    //         @unlink($oldFilePath);
                    //     }
                    // }

                    // Delete old file if exists — scoped correctly for subjective dependent
                    $existingRecord = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where('activity_id', $request->activity_id)
                        ->where($this->activitySequenceFilter($activitySequence))
                        ->where('question_id', $questionId)
                        ->when(!empty($attribute), fn($q) => $q->where('subjective_parent', 0))
                        ->first();

                    if ($existingRecord && $existingRecord->user_answer) {
                        $oldFilePath = public_path($existingRecord->user_answer);
                        if (file_exists($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }

                    // Move file to directory
                    $file->move(public_path($projectMonthYearDirectory), $fileName);
                    @chmod(public_path($fullFilePath), 0777);

                    $user_answer = $fullFilePath;
                    $file_path = $fullFilePath;
                } else {
                    // Handle non-file inputs
                    if (is_array($rawValue)) {
                        $user_answer = implode(',', array_filter($rawValue));
                    } else {
                        $user_answer = trim((string) $rawValue);
                    }

                    // Format based on type
                    if (($question->question_type === 'Date' || $attribute === 'date') && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif (($question->question_type === 'Date & Time' || $attribute === 'datetime') && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y H:i');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif ($question->question_type === 'Yes / No' || $attribute === 'yesno') {
                        $normalized = strtolower($user_answer);
                        if (in_array($normalized, ['yes', '1', 'true', 'y'])) {
                            $user_answer = 'Yes';
                        } elseif (in_array($normalized, ['no', '0', 'false', 'n'])) {
                            $user_answer = 'No';
                        }
                    } elseif (
                        ($question->question_type === 'Audio' || $question->question_type === 'Video') &&
                        (str_starts_with($user_answer, 'data:video/') || str_starts_with($user_answer, 'data:audio/'))
                    ) {
                        // Handle new recording
                        $result = $decodeAndSaveMediaBase64(
                            $user_answer,
                            strtolower($question->question_type),
                            $projectMonthYearDirectory,
                            $file_path
                        );

                        if (str_starts_with((string) $result, 'ERROR:')) {
                            Log::warning('Media decode failed for question ' . $questionId . ': ' . substr($result, 6));
                            continue;
                        }

                        // Delete old media file if exists
                        $existingRecord = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where('activity_id', $request->activity_id)
                            ->where($this->activitySequenceFilter($activitySequence))
                            ->where('question_id', $questionId)
                            ->first();

                        if ($existingRecord && $existingRecord->user_answer) {
                            $oldFilePath = public_path($existingRecord->user_answer);
                            if (file_exists($oldFilePath)) {
                                @unlink($oldFilePath);
                            }
                        }

                        $user_answer = $result;
                        $isFile = true;
                    } elseif (
                        ($question->question_type === 'Audio' || $question->question_type === 'Video') &&
                        !empty($user_answer) &&
                        !str_starts_with($user_answer, 'data:')
                    ) {
                        // Existing file path - keep it as is
                        // ── Strip the base URL if asset() added it ──
                        $baseUrl = url('/');
                        if (str_starts_with($user_answer, $baseUrl)) {
                            $user_answer = ltrim(str_replace($baseUrl, '', $user_answer), '/');
                        }
                        // Also handle urldecoded spaces
                        $user_answer = urldecode($user_answer);

                        $isFile = true;
                    }
                }

                // ============================================
                // HANDLE SUBJECTIVE QUESTIONS WITH subjective_parent
                // ============================================
                if ($question->question_type === 'Subjective') {

                    // Get the main subject value from the request
                    $mainSubjectValue = $questionAnswers[$questionId] ?? '';

                    // Check if this is the main subjective question or a dependent attribute
                    if (empty($attribute)) {
                        // ============================================
                        // MAIN SUBJECTIVE QUESTION (subjective_parent = 1)
                        // ============================================

                        // Find existing main subjective record
                        $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where('activity_id', $request->activity_id)
                            ->where($this->activitySequenceFilter($activitySequence))
                            ->where('question_id', $questionId)
                            ->where('subjective_parent', 1)
                            ->first();

                        // Find existing main subjective record
                        $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where('activity_id', $request->activity_id)
                            ->where($this->activitySequenceFilter($activitySequence))
                            ->where('question_id', $questionId)
                            ->where('subjective_parent', 1)
                            ->first();

                        // ── NEW: if main subject changed, wipe old dependent record + its file ──
                        if ($existing && $existing->user_answer !== $mainSubjectValue) {
                            $oldDependent = TempUserActivityAnswersData::where('row_id', $request->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->where($this->activitySequenceFilter($activitySequence))
                                ->where('question_id', $questionId)
                                ->where('subjective_parent', 0)
                                ->first();

                            if ($oldDependent) {
                                // Delete the physical file if it exists
                                if ($oldDependent->user_answer && !str_starts_with($oldDependent->user_answer, 'data:')) {
                                    $oldFilePath = public_path($oldDependent->user_answer);
                                    if (file_exists($oldFilePath)) {
                                        @unlink($oldFilePath);
                                    }
                                }
                                $oldDependent->delete();
                            }
                        }
                        // ── END NEW ──

                        if ($existing) {
                            // Update existing main subjective answer
                            $existing->update([
                                'user_answer' => $mainSubjectValue,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'subjective_parent' => 1,
                                'updated_at' => now(),
                                'status' => 0
                            ]);
                            $answer = $existing;
                        } else {
                            // Create new main subjective answer
                            $answer = TempUserActivityAnswersData::create([
                                'user_id' => $userId,
                                'row_id' => $request->row_id,
                                'activity_id' => $request->activity_id,
                                'activity_sequence' => $activitySequence,
                                'activity_group_name_id' => $request->group_id ?? 0,
                                'question_id' => $questionId,
                                'user_answer' => $mainSubjectValue,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'subjective_parent' => 1, // Main subjective question
                                'status' => 0
                            ]);
                        }
                    } elseif (in_array($attribute, $allowedBindAttributes)) {
                        // ============================================
                        // DEPENDENT SUBJECTIVE QUESTION (subjective_parent = 0)
                        // ============================================

                        // Skip if user_answer is empty (no value provided)
                        if (empty($user_answer) && !$isFile) {
                            continue;
                        }

                        // Handle media files for dependent attributes
                        if (
                            in_array($attribute, ['audio', 'video']) &&
                            (str_starts_with($user_answer, 'data:video/') || str_starts_with($user_answer, 'data:audio/'))
                        ) {
                            $result = $decodeAndSaveMediaBase64(
                                $user_answer,
                                $attribute,
                                $projectMonthYearDirectory,
                                $file_path
                            );

                            if (str_starts_with((string) $result, 'ERROR:')) {
                                Log::warning('Subjective media decode failed for question ' . $questionId . ' attr ' . $attribute . ': ' . substr($result, 6));
                                continue;
                            }

                            $user_answer = $result;
                            $isFile = true;
                            $file_path = $result;
                        }

                        // Find existing dependent record for this attribute scoped to the correct instance
                        $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where('activity_id', $request->activity_id)
                            ->where($this->activitySequenceFilter($activitySequence))
                            ->where('question_id', $questionId)
                            ->where('subjective_parent', 0)
                            ->first();

                        if ($existing) {
                            // Delete old file if replacing with new file
                            if ($isFile && $existing->user_answer && $existing->user_answer !== $user_answer) {
                                $oldFilePath = public_path($existing->user_answer);
                                if (file_exists($oldFilePath) && !str_starts_with($existing->user_answer, 'data:')) {
                                    @unlink($oldFilePath);
                                }
                            }

                            // Update existing dependent answer
                            $existing->update([
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'subjective_parent' => 0,
                                'updated_at' => now(),
                                'status' => 0
                            ]);
                            $answer = $existing;
                        } else {
                            // Create new dependent answer
                            $answer = TempUserActivityAnswersData::create([
                                'user_id' => $userId,
                                'row_id' => $request->row_id,
                                'activity_id' => $request->activity_id,
                                'activity_sequence' => $activitySequence,
                                'activity_group_name_id' => $request->group_id ?? 0,
                                'question_id' => $questionId,
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'subjective_parent' => 0, // Dependent subjective question
                                'status' => 0
                            ]);
                        }

                        // Queue image for processing if it's an image
                        if (in_array($attribute, ['image', 'Image']) && $file_path) {
                            $imagesToProcess[] = [
                                'file_path' => public_path($file_path),
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'answer' => $answer,
                                'directory' => $projectMonthYearDirectory,
                                'user_id' => $userDetails->id,
                                'status' => 0
                            ];
                        }

                        continue; // Skip to next iteration after handling dependent attribute
                    }
                } else {

                    // ============================================
                    // HANDLE NON-SUBJECTIVE QUESTIONS
                    // ============================================
                    $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where('activity_id', $request->activity_id)
                        ->where($this->activitySequenceFilter($activitySequence))
                        ->where('question_id', $questionId)
                        ->first();

                    // If parent question answer changed, delete old child answers
                    if ($existing && $existing->user_answer !== $user_answer) {
                        $childQuestions = Question::where('parent_question_id', $questionId)
                            ->pluck('id')
                            ->toArray();

                        if (!empty($childQuestions)) {
                            $allChildIds = $this->getAllChildQuestionIds($questionId);

                            $oldAnswers = TempUserActivityAnswersData::where('row_id', $request->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->where($this->activitySequenceFilter($activitySequence))
                                ->whereIn('question_id', $allChildIds)
                                ->get();

                            foreach ($oldAnswers as $oldAnswer) {
                                if ($oldAnswer->user_answer && !str_starts_with($oldAnswer->user_answer, 'data:')) {
                                    $oldFilePath = public_path($oldAnswer->user_answer);
                                    if (file_exists($oldFilePath)) {
                                        @unlink($oldFilePath);
                                    }
                                }
                            }

                            TempUserActivityAnswersData::where('row_id', $request->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->where($this->activitySequenceFilter($activitySequence))
                                ->whereIn('question_id', $allChildIds)
                                ->delete();
                        }
                    }

                    // ── UPDATE if exists, CREATE if not ──
                    if ($existing) {
                        // Delete old file if replacing with a new file
                        if ($isFile && $existing->user_answer && $existing->user_answer !== $user_answer) {
                            $oldFilePath = public_path($existing->user_answer);
                            if (file_exists($oldFilePath) && !str_starts_with($existing->user_answer, 'data:')) {
                                @unlink($oldFilePath);
                            }
                        }

                        $existing->update([
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'updated_at' => now(),
                            'status' => 0,
                        ]);
                        $answer = $existing;
                    } else {
                        $answer = TempUserActivityAnswersData::create([
                            'user_id' => $userId,
                            'row_id' => $request->row_id,
                            'activity_id' => $request->activity_id,
                            'activity_sequence' => $activitySequence,
                            'activity_group_name_id' => $request->group_id ?? 0,
                            'question_id' => $questionId,
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'status' => 0,
                        ]);
                    }
                }

                // Queue image processing for Image questions
                if (($question->question_type === 'Image' || $attribute === 'image') && $file_path && isset($answer)) {
                    $imagesToProcess[] = [
                        'file_path' => public_path($file_path),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'answer' => $answer,
                        'directory' => $projectMonthYearDirectory,
                        'user_id' => $userDetails->id,
                    ];
                }
            }

            // ── Handle pre-uploaded multi-image paths (multi_images_{qid}[] hidden inputs) ──
            // These come from the AJAX pre-upload endpoint and are already geo-tagged on disk.
            // We just need to store the JSON array in the DB.
            $multiImageKeys = array_filter(array_keys($request->all()), fn($k) => str_starts_with($k, 'multi_images_'));
            foreach ($multiImageKeys as $multiKey) {
                $questionId = (int) str_replace('multi_images_', '', $multiKey);
                if (!$questionId) continue;
                $question = Question::find($questionId);
                if (!$question || $question->question_type !== 'Image') continue;

                $paths = array_filter((array)($request->input($multiKey) ?? []), fn($p) => !empty(trim($p)));
                if (empty($paths)) continue;

                $jsonAnswer = json_encode(array_values($paths));
                $processedQuestionIds[] = $questionId;

                $existing = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where($this->activitySequenceFilter($activitySequence))
                    ->where('question_id', $questionId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'user_answer'    => $jsonAnswer,
                        'same_answer_id' => $last_sequence,
                        'latitude'       => $latitude,
                        'longitude'      => $longitude,
                        'updated_at'     => now(),
                        'status'         => 0,
                    ]);
                } else {
                    TempUserActivityAnswersData::create([
                        'user_id'                 => $userId,
                        'row_id'                  => $request->row_id,
                        'activity_id'             => $request->activity_id,
                        'activity_sequence'       => $activitySequence,
                        'activity_group_name_id'  => $request->group_id ?? 0,
                        'question_id'             => $questionId,
                        'user_answer'             => $jsonAnswer,
                        'same_answer_id'          => $last_sequence,
                        'latitude'                => $latitude,
                        'longitude'               => $longitude,
                        'status'                  => 0,
                    ]);
                }
            }

            DB::commit();

            // Now safely reset status 3→0 for ALL records of every question
            // that was part of this submission (whether value changed or not)
            if (!empty($processedQuestionIds)) {
                TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where($this->activitySequenceFilter($activitySequence))
                    ->whereIn('question_id', array_unique($processedQuestionIds))
                    ->where('status', 3)
                    ->update(['status' => 0]);
            }
            // Process images in background
            if (!empty($imagesToProcess)) {
                dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
            }

            // Mark this repeat instance as submitted
            if ($activitySequence > 0) {
                ActivityRepeatInstance::where('row_id', $request->row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where('activity_sequence', $activitySequence)
                    ->update(['status' => 1]);
            }

            $message = "Activity Answer submitted successfully";

            // AJAX path — return JSON so the activity_questions page can handle inline OTP modal
            if ($request->ajax() || $request->wantsJson()) {
                if ($otp_required) {
                    return response()->json([
                        'success' => true,
                        'otp_required' => true,
                        'message' => $message . '. Please verify OTP.',
                        'row_id' => $request->row_id,
                        'activity_id' => $request->activity_id,
                    ]);
                }
                return response()->json(['success' => true, 'otp_required' => false, 'message' => $message]);
            }

            // Non-AJAX (legacy) path — keep existing redirect behaviour
            if ($otp_required) {
                $projectId = $projectTemp->getProjectTemplateData->getProject->id;
                $message = $message . ". Please verify OTP";
                return redirect()->route('otp_verification_page', [
                    'row_id' => $request->row_id,
                    'activity' => $request->activity_id,
                    'project_id' => $projectId
                ])->with('message', $message);
            }

            // Redirect based on session
            if (Session::has('project_outlet_activity')) {
                $params = Session::get('project_outlet_activity');
                return redirect(route('user.project.distributor.outlets', $params))
                    ->with('message', $message);
            }

            if (Session::has('project_dist_activity')) {
                $params = Session::get('project_dist_activity');
                return redirect(route('user.project_master.data', $params))
                    ->with('message', $message);
            }

            return redirect()->back()->with('message', $message);
        } catch (\Exception $e) {
            // dd($e->getMessage());
            DB::rollBack();
            Log::error('Activity submit error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['_token'])
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Error submitting answers: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error submitting answers: ' . $e->getMessage());
        }
    }


    /**
     * Returns a closure usable in ->where() that scopes a TempUserActivityAnswersData
     * query to a given "repeat instance" (activity_sequence).
     * 0 (default/original submission) matches both NULL and 0, since existing rows
     * predating this feature have a NULL activity_sequence.
     */
    private function activitySequenceFilter(int $activitySequence)
    {
        return function ($q) use ($activitySequence) {
            if ($activitySequence > 0) {
                $q->where('activity_sequence', $activitySequence);
            } else {
                $q->where(function ($q2) {
                    $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                });
            }
        };
    }

    private function getAllChildQuestionIds(int $parentId): array
    {
        $directChildren = Question::where('parent_question_id', $parentId)
            ->pluck('id')
            ->toArray();

        $allIds = $directChildren;

        foreach ($directChildren as $childId) {
            $grandchildren = $this->getAllChildQuestionIds($childId);
            $allIds = array_merge($allIds, $grandchildren);
        }

        return $allIds;
    }


    private function getExtensionFromMime($mimeType)
    {
        $mimeMap = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/webm' => 'weba',
            'audio/x-wav' => 'wav',
        ];

        return $mimeMap[$mimeType] ?? null;
    }




    public function userProjectActivityDistributorOutletData($row_id, $distributor_value)
    {
        // dd($distributor_value, $row_id);
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);

        $add_project_data_title = "Add ";
        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;
        $row_renderred_arr = [];
        $project_data_arr = [];

        // $master_head_id = $projectTemplate->master_head_id;
        // $templatejson = json_decode($projectTemplateNameValue->template_data_json);
        // dd($templatejson, $master_head_id);
        // $own_head_id = $projectTemplate->own_reference_head_id;

        $projectTemplateHeaders = [];

        // dd($projectTemplateHeaders);

        $project_temp_info = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->first();

        // ADD THIS LINE after the above:
        $projectTemplateInfoDetails = $project_temp_info ? $project_temp_info->getTemplate : null;

        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $childTemplatesIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('id')->toArray();

        $childOwnReferenceIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('own_reference_head_id')->toArray();

        $childMasterHeadIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('master_head_id')->toArray();

        $templateData = json_decode($projectTemplateNameValue->template_data_json, true);

        $distributors = [];

        $distributorHeadMap = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get()
            ->groupBy('master_head_id')
            ->map(function ($rows) {
                return $rows->pluck('own_reference_head_id')->toArray();
            });

        foreach ($distributorHeadMap as $masterHeadId => $childHeadIds) {

            $distributors[] = $templateData[$masterHeadId] ?? null;
        }


        // dd($childOwnReferenceIds, $childMasterHeadIds, $distributors);


        //        $outlet_items = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
        //            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->own_reference_head_id\"')) = ?", [trim($distributor_value)])
        //            ->get();

        // Get the parent template (distributor template) to find its header IDs
        $parentTemplate = ProjectTemplate::find($projectTemplate->id);

        // Get all child templates with their mapping info
        $childTemplatesWithMappings = [];
        foreach ($childTemplatesData as $childTemplate) {
            $childTemplatesWithMappings[$childTemplate->id] = [
                'template' => $childTemplate,
                'master_head_id' => $childTemplate->master_head_id,
                'own_reference_head_id' => $childTemplate->own_reference_head_id,
                'template_name' => $childTemplate->getTemplate->template_name ?? $childTemplate->template_name,
                'headers' => $childTemplate->getTemplate->getTemplateHeads ?? [],
            ];
        }

        // Get all header values from the parent template row
        $parentRowData = ProjectTemplateNameValuesNew::find($row_id);
        $parentTemplateData = json_decode($parentRowData->template_data_json, true);

        // Map distributor value to corresponding child templates
        $templateMappingData = [];

        foreach ($childTemplatesWithMappings as $childId => $childInfo) {
            $masterHeadId = $childInfo['master_head_id'];
            $ownReferenceId = $childInfo['own_reference_head_id'];

            if ($masterHeadId && isset($parentTemplateData[$masterHeadId])) {
                // This child template is linked to this distributor
                $templateMappingData[$childId] = [
                    'master_head_id' => $masterHeadId,
                    'master_head_value' => $parentTemplateData[$masterHeadId],
                    'own_reference_head_id' => $ownReferenceId,
                    'own_reference_value' => $parentTemplateData[$ownReferenceId] ?? null,
                ];
            }
        }



        $activities = [];
        foreach ($childTemplatesData as $childTemp) {

            if ($childTemp->activityType == 0) {
                $activities[] = $childTemp->activity_group_name_id_or_activity_id;
            } elseif ($childTemp->activityType == 1) {
                $activities = array_merge(
                    $activities,
                    DB::table('activity_group_pivots')->where('activity_group_id', $childTemp->activity_group_name_id_or_activity_id)
                        ->pluck('activity_id')
                        ->toArray()
                );
            }
        }

        $checkPrentOutletAssign = DataAssign::where('project_template_id', $projectTemplate->id)
            ->where('is_outlet_assigned', 1)
            ->exists();

        // dd($checkPrentOutletAssign);
        $getAssignedChildTemplateIDs = [];

        if ($checkPrentOutletAssign) {

            $parentDataAssignIds = DataAssign::where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

            $dataAssignCommonIds = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id');

            $getAssignedChildTemplateIDs = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('project_template_id')
                ->pluck('project_template_id')->toArray();

            $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')
                ->pluck('row_id');

            //            dd($getRowIds, $distributor_value);

            // $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
            //     ->whereIn('id', $getRowIds)
            //     ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", $distributors)
            //     ->get();

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIds)
                ->where(function ($query) use ($distributors) {
                    foreach ($distributors as $value) {
                        $query->orWhereRaw(
                            "JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL",
                            [$value]
                        );
                    }
                })
                ->get();

            // dd($outlet_items, $childTemplatesIds, $getRowIds, $distributors, $row_id);

        } else {

            $dataAssignCommonIdsnew = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('activity_id', $activities)
                ->distinct('common_id')
                ->pluck('common_id');

            $getRowIdsnew = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIdsnew)
                ->distinct('row_id')
                ->pluck('row_id');

            // $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
            //     ->whereIn('id', $getRowIdsnew)
            //     ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$distributor_value])
            //     ->get();

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIdsnew)
                ->where(function ($query) use ($distributors) {
                    foreach ($distributors as $value) {
                        $query->orWhereRaw(
                            "JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL",
                            [$value]
                        );
                    }
                })
                ->get();
        }


        if ($checkPrentOutletAssign && $outlet_items->IsEmpty()) {

            $childDataAssignIds = DB::table('data_assigns')->whereIn('project_template_id', $childTemplatesIds)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

            $dataAssignCommonIds = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('activity_id', $activities)
                ->distinct('common_id')
                ->pluck('common_id');

            $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')
                ->pluck('row_id');

            // $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
            //     ->whereIn('id', $getRowIds)
            //     ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$distributor_value])
            //     ->get();

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIds)
                ->where(function ($query) use ($distributors) {
                    foreach ($distributors as $value) {
                        $query->orWhereRaw(
                            "JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL",
                            [$value]
                        );
                    }
                })
                ->get();
        }


        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;
                // $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                //     ->whereIn('activity_id', $activities)
                //     ->exists();

                $activityRequiredQuestions = Question::whereIn('activity_id', $activities)
                    ->where('answer_type', 1)
                    ->where('is_parent', 1)
                    ->pluck('id')
                    ->toArray();

                // Count how many required questions were submitted
                $submittedcount = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                    ->whereIn('activity_id', $activities)
                    ->whereIn('question_id', $activityRequiredQuestions)
                    ->where('user_id', Auth::user()->id)
                    ->distinct('question_id')
                    ->count('question_id');

                // Check if any answer exists at all
                $is_answered = $submittedcount > 0;

                // Check if subjective question exists
                $subjectiveExists = Question::whereIn('activity_id', $activities)
                    ->where('answer_type', 1)
                    ->where('question_type', 'Subjective')
                    ->exists();

                // Total required questions count
                $requiredCount = count($activityRequiredQuestions);

                // Final check
                if (!$is_answered) {
                    $check_if_answered = false;
                } else {
                    // Special rule: If subjective exists → allow >= OR == (business logic?)
                    if ($subjectiveExists) {
                        // If subjective exists, allow ANY submitted as long as count meets requirement
                        $check_if_answered = ($submittedcount >= $requiredCount);
                    } else {
                        // Normal: submitted must be equal or greater
                        $check_if_answered = ($submittedcount >= $requiredCount);
                    }
                }

                $otpVerificationDone = false;
                if ($project->is_otp_required == 1) {


                    $activityAllQuestions = Question::whereIn('activity_id', $activities)
                        ->where('is_parent', 1)
                        ->pluck('id')
                        ->toArray();


                    $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                        ->whereIn('activity_id', $activities)
                        ->whereIn('question_id', $activityAllQuestions)
                        ->where('user_id', Auth::user()->id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                        $otpVerificationDone = true;
                    }
                }

                $templateNameData = ProjectTemplate::where('id', $outlet_item->project_template_id)->first();

                $projectTemplateInfoDetails = $templateNameData->getTemplate;
                $projectTemplateHeadersMapped = $projectTemplateInfoDetails->getTemplateHeads;
                // dd()

                $main_header_info = ProjectTemplateNameValuesNew::where('id', $outlet_item->id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$templateNameData->main_header}\"')) as value")
                    )
                    ->first();

                $sub_header_info = ProjectTemplateNameValuesNew::where('id', $outlet_item->id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$templateNameData->sub_header}\"')) as value")
                    )
                    ->first();

                $main_header = $main_header_info->value ?? null;
                $sub_header = $sub_header_info->value ?? null;

                $projecttemplatedata = ProjectTemplate::find($outlet_item->project_template_id);

                $templateJson = DB::table('project_template_name_values_new')->where('id', $outlet_item->id)->value('template_data_json');
                // dd($templateJson);

                $templateData = json_decode($templateJson, true); // associative array

                // dd($templateData);
                $result = $projectTemplateHeadersMapped->map(function ($header) use ($templateData) {
                    // dd($templateData, $header);
                    return [
                        'head_id' => $header->id,
                        'head_name' => $header->template_head_name,
                        'value' => $templateData[$header->id] ?? null,
                    ];
                });

                // dd($result);

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $check_if_answered ? 'completed' : 'pending',
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                    'can_edit_data' => $projecttemplatedata->can_edit_data,
                    'otpVerificationDone' => $otpVerificationDone,
                    'header_data' => $result,
                    'isOtpRequired' => $project->is_otp_required
                ];
            }
        }

        $withoutDataTemplate = $childTemplatesData->where('with_data', 0)->count();
        if (empty($projectTemplateHeaders) && !empty($getAssignedChildTemplateIDs)) {
            foreach ($childTemplatesData as $childData) {
                // dd($childData->id, $getAssignedChildTemplateIDs);
                // dd($childData->data_add_on, $childData->with_data, in_array($childData->id, $getAssignedChildTemplateIDs), $getAssignedChildTemplateIDs, $childData->id, $parentDataAssignIds);
                if (($childData->with_data == 0 || $childData->data_add_on == 1) && (in_array($childData->id, $getAssignedChildTemplateIDs) || $checkPrentOutletAssign)) {
                    $projectTemplateHeaders[] = [
                        'id' => $childData->id,
                        'data' => $childData->getTemplate->getTemplateHeads,
                        'template_name' => $childData->getTemplate->template_name
                    ];
                }
            }
        }
        // dd($projectTemplateHeaders, $getAssignedChildTemplateIDs);

        //        dd($project_data_arr);
        $isOutletAssigned = 0;
        $isParent = 0;
        Session::put('project_outlet_activity', ['row_id' => $row_id, 'distributor_value' => $distributor_value]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();

        // dd($projectTemplateInfoDetails, $templateMappingData);
        // In userProjectActivityDistributorOutletData(), change the return to:
        return view('masters.users.project_data', compact(
            'project_data_arr',
            'isParent',
            'row_id',
            'isOutletAssigned',
            'project',
            'project_temp_info',
            'projectTemplateHeaders',
            'add_project_data_title',
            'currentUserRole',
            'templateMappingData',
            'childTemplatesWithMappings',
            'projectTemplateInfoDetails'  // ← ADD THIS
        ));

        // return view('masters.users.project_data', compact('project_data_arr', 'isParent', 'row_id', 'isOutletAssigned', 'project', 'project_temp_info', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole', 'templateMappingData', 'childTemplatesWithMappings'));
    }






    public function getTemplateHeadData(Request $request)
    {
        try {
            $row_id = $request->row_id;
            $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
            $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
            $projectTemplateHeads = $projectTemplateData->getTemplate->getTemplateHeads->pluck('id', 'template_head_name');
            $template_json_data = json_decode($projectTemplateNameValue->template_data_json, true);
            // now you can safely map because $projectTemplateHeads is still a collection
            $mapped = $projectTemplateHeads->map(function ($id, $name) use ($template_json_data) {
                return [
                    'id' => $id,
                    'name' => $name,
                    'value' => $template_json_data[$id] ?? null,
                ];
            })->values()->toArray();
            return response()->json(['status' => true, 'data' => $mapped, 'project_template_id' => $projectTemplateData->id]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }


    public function edit_project_data_template(Request $request)
    {
        try {
            $row_id = $request->row_id;
            $project_template_id = $request->project_template_id;
            $excludedKeys = ['row_id', 'project_template_id'];
            $row_data = collect($request->all())->except($excludedKeys)->map(function ($value) {
                return $value === null ? '' : $value;
            });
            $templateJsonData = ProjectTemplateNameValuesNew::find($row_id);
            $templateJsonData->update([
                'template_data_json' => json_encode($row_data),
            ]);
            return response()->json(['status' => true, 'message' => 'Data Updated Successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function otp_verification_page($row_id, $activity_id, $project_id)
    {
        return view('masters.users.otp_verification', compact('row_id', 'activity_id', 'project_id'));
    }


    public function send_otp(Request $request)
    {
        //        dd($request->all());
        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'row_id' => 'required|numeric'
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = User::find(auth()->user()->id);
            if (empty($userdata)) {
                return response([
                    'status' => 401,
                    'message' => 'User Not Found',
                ], 401);
            }
            //  dd($userdata);

            //khushboo 02-05-2025
            $getAllQuestionIds = Question::where('activity_id', $request->activity_id)
                ->pluck('id')
                ->toArray();

            $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->whereIn('question_id', $getAllQuestionIds)
                ->orderBy('id', 'DESC')->first();

            if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                return response([
                    'status' => 409,
                    'message' => 'OTP has already been generated.',
                ], 409);
            }

            if ($lastQuestionAnswered->otp_verified_status == 1) {
                return response([
                    'success' => false,
                    'message' => 'OTP has already been verified.',
                ], 409);
            }

            $phone = $request->mobile_no;
            $otpCode = rand(100000, 999999); // 6-digit OTP

            // Send SMS via SMS Gateway Center API
            $response = Http::asForm()->withoutVerifying()->post('https://unify.smsgateway.center/SMSApi/send', [
                'userid' => 'tnbttech',
                'password' => '79aJfwhX',
                'mobile' => $phone,
                'senderid' => 'TNBTTH',
                'dltEntityId' => '1701173978747429878',
                'msg' => 'Hi, Please find below OTP for your Document Verification: ' . $otpCode . '-TNBT Tech',
                'sendMethod' => 'quick',
                'msgType' => 'text',
                'dltTemplateId' => '1707173997125309765',
                'output' => 'json',
                'duplicatecheck' => 'true',
                // 'test'        => 'true' // uncomment for test mode if needed
            ]);

            // $responseJson = $response->json();
            // dd($response);
            if ($response->successful()) {

                TempUserActivityAnswersData::where('id', $lastQuestionAnswered->id)->update([
                    'mobile_no' => $request->mobile_no,
                    'mobile_otp' => $otpCode
                ]);

                //khushboo 02-05-2025

                return response([
                    'success' => true,
                    'message' => 'OTP Send Successfully',
                ], 200);
            } else {

                return response([
                    'success' => false,
                    'message' => "Failed To Send OTP",
                ], 200);
            }
        } catch (\Exception $e) {

            return response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    public function verify_otp(Request $request)
    {
        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'row_id' => 'required|numeric',
                'mobile_otp' => 'required|numeric',
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = User::find(auth()->user()->id);
            if (empty($userdata)) {
                return response([
                    'success' => false,
                    'message' => 'User Not Found',
                ], 401);
            }

            $getAllQuestionIds = Question::where('activity_id', $request->activity_id)
                ->pluck('id')
                ->toArray();

            $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->whereIn('question_id', $getAllQuestionIds)
                ->where('mobile_no', $request->mobile_no)
                ->where('mobile_otp', $request->mobile_otp)
                ->orderBy('id', 'DESC')
                ->exists();

            $lastQuestionAnsweredData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->whereIn('question_id', $getAllQuestionIds)
                ->where('mobile_no', $request->mobile_no)
                ->where('mobile_otp', $request->mobile_otp)
                ->orderBy('id', 'DESC')
                ->first();

            if ($lastQuestionAnswered) {
                TempUserActivityAnswersData::where('id', $lastQuestionAnsweredData->id)->update([
                    'otp_verified_status' => 1
                ]);

                if (Session::has('project_outlet_activity')) {
                    $params = Session::get('project_outlet_activity');
                    $redirectUrl = route('user.project.distributor.outlets', $params);
                }
                if (Session::has('project_dist_activity')) {
                    $params = Session::get('project_dist_activity');
                    $redirectUrl = route('user.project_master.data', $params);
                }

                return response([
                    'success' => true,
                    'message' => 'OTP Verified Successfully',
                    'redirectUrl' => $redirectUrl
                ], 200);
            } else {

                return response([
                    'success' => false,
                    'message' => 'OTP Not Verified',
                ], 200);
            }
        } catch (\Exception $e) {
            return response([
                'success' => false,
                'message' => 'OTP Not Verified' . $e->getMessage(),
            ], 200);
        }
    }
}
