<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityGroupPivot;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\User;
use App\Models\SubjectQuestion;
use App\Models\ProjectMasterTemplates;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\SubjectDropdown;
use App\Models\Question;
use App\Models\TemplateName;
use App\Models\ClosedAudits;
use App\Models\TemplateNameHead;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use Carbon\Carbon;
use App\Models\TempUserActivityAnswersData;
use Dflydev\DotAccessData\Data;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ConvertAuditorSelfiesToGeoSelfies;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    //

    public function getAllProject(Request $request)
    {

        try {

            $user_id = $request->user_id;
            $data = '';
            $uniqueProjectsAssignIds = UserActivityDataAssign::where('user_id', $user_id)
                ->distinct()
                ->pluck('data_assign_id');

            $uniqueUserProjects = DataAssign::whereIn('id', $uniqueProjectsAssignIds)->distinct()->pluck('project_id');
            $userProjects = Project::whereIn('id', $uniqueUserProjects)->select('id', 'project_name')->get();

            return response([
                'status' => 200,
                'message' => 'success',
                'data' => $userProjects
            ], 200);

        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function getAllActivity($row_id, $user_id, $is_outlet_assigned = 0)
    {

        try {

            $user = $user_id;
            $userAssignedActivities = [];
            $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
            $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
            $projectInfo = Project::find($projectTemplateData->project_id);
            $project_master_template = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                ->where('is_master', 1)
                ->first();

            $template_name_id = $projectTemplateData->template_name_id;
            $data_add_on = $projectTemplateData->data_add_on == 1 ? true : false;

            $getHeadValues = DB::table('project_template_name_values_new')
                ->where('id', $row_id)
                ->select(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateData->main_header}\"')) as head_value")
                )
                ->get();

            $distributor_value = $getHeadValues[0]->head_value;

            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')
                ->where('project_id', $projectTemplateData->project_id)
                ->where('id', '!=', $project_master_template->id)
                ->get();


            //khushboo 07-07-2025

            $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user)
                ->where('project_template_id', $projectTemplateData->id)
                ->distinct('data_assign_id')
                ->pluck('data_assign_id');
            //khushboo 07-07-2025
            // dd($distinct_data_assignIds);

            $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
                ->where('project_id', $projectTemplateData->project_id)
                ->where('project_template_id', $projectTemplateData->id)
                ->whereIn("id", $distinct_data_assignIds)
                ->get();

            $checkOutletAssign = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
                ->where('project_id', $projectTemplateData->project_id)
                ->whereIn("id", $distinct_data_assignIds)
                ->where('is_outlet_assigned', 1)
                ->get();

            // dd($checkOutletAssign);
            if (!empty($checkOutletAssign)) {

                $data_assign_info = UserActivityDataAssign::with('projectTemplateData', 'activityInfo')
                    ->where('project_template_id', $projectTemplateData->id)
                    ->get();

            }
            // dd($data_assign_info);

            $otpRequiredStatus = false;
            $distinct_data_assign_activity_Ids = UserActivityDataAssign::where('user_id', $user)
                ->where('project_template_id', $projectTemplateData->id)
                ->distinct('activity_id')
                ->pluck('activity_id')->toArray();
            // dd($distinct_data_assign_activity_Ids);

            if (
                $projectInfo->is_otp_required == 1 &&
                !empty($projectTemplateData->activity_otp_required_ids)
            ) {
                $otpRequiredIds = json_decode($projectTemplateData->activity_otp_required_ids, true);

                if (!empty($otpRequiredIds) && count(array_intersect($distinct_data_assign_activity_Ids, $otpRequiredIds)) > 0) {
                    $otpRequiredStatus = true;
                }
            }

            $groupActivity = null;
            if ($projectTemplateData->activityType == 1) {
                $groupActivity = $projectTemplateData->activity_group_name_id_or_activity_id;
            }
            // dd($data_assign_info);
            foreach ($data_assign_info as $assigned_data) {
                // dd('gnjhgj');
                $sequence = null;
                // dd($assigned_data);
                if (isset($assigned_data->activity_group_id)) {
//                    $groupActivity = $assigned_data->activity_group_id;
                    $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                        ->where('activity_group_id', $assigned_data->activity_group_id)
                        ->first();
                    $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : 1;
                }
                $group_name = $assigned_data->getActivityGroup ? $assigned_data->getActivityGroup->activity_group_name : null;
                $is_master_temp = $assigned_data->getProjectTemplate && $assigned_data->getProjectTemplate->is_master ? $assigned_data->getProjectTemplate->is_master : $assigned_data->projectTemplateData->is_master;

                $current_template_name =
                    $assigned_data->templateName ?
                        $assigned_data->templateName->template_name :
                        $assigned_data->projectTemplateData->getTemplate->template_name;


                $current_activity_name =
                    $assigned_data->activityName ?
                        $assigned_data->activityName->activity_name :
                        $assigned_data->activityInfo->activity_name;

                $exists = false;

                // dd($activity, $current_template_name, $current_activity_name);

                foreach ($userAssignedActivities as $activity) {
                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                        $exists = true;
                        break; // Exit loop if a match is found
                    }
                }

                $is_activity_answered = false;
                $status = 'pending';
                $totalQuestions = Question::where('activity_id', $assigned_data->activity_id)->where('answer_type', 1)->count();
                $totalQuestionsIdsArray = Question::where('activity_id', $assigned_data->activity_id)
                    ->where('answer_type', 1)->pluck('id')->toArray();
                $getAnsweredQuestionsCount = DB::table('temp_user_activity_answers_data')
                    ->where('activity_id', $assigned_data->activity_id)
                    ->where('row_id', $row_id)
                    ->whereIn('question_id', $totalQuestionsIdsArray)
                    // ->where('user_id', $user_id)
                    ->count();

                if ($getAnsweredQuestionsCount > 0 || $totalQuestions == $getAnsweredQuestionsCount) {
                    $is_activity_answered = true;
                    $getAllQuestionIds = Question::where('activity_id', $assigned_data->activity_id)->pluck('id')->toArray();
                    //otp verified status
                    $lastQuestionAnswered = DB::table('temp_user_activity_answers_data')
                        ->where('activity_id', $assigned_data->activity_id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->where('row_id', $row_id)
                        // ->where('user_id', $user_id)
                        ->orderBy('id', 'DESC')->first();
                    //rejected status
                    $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $row_id)
                        ->where('activity_id', $assigned_data->activity_id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->where('status', 4)
                        ->count();
                    if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1) && ($projectInfo->is_otp_required == 1)) {
                        $status = 'completed';
                    } else if (($projectInfo->is_otp_required == 1)) {
                        $status = 'Awaiting OTP Verify';
                    } else {
                        $status = 'completed';
                    }
                    if ($rejectedAnswersCount > 0) {
                        $status = "rejected";
                    }

                }

                $is_distributor_assign = false;
                // dd($projectTemplateData);
                if ($projectTemplateData->is_master == 1) {
                    $getAuditCommonIds = DB::table('user_audit_assigns')->where('row_id', $row_id)
                        ->distinct('common_id')
                        ->pluck('common_id')
                        ->toArray();

                    $getActivities = DB::table('user_activity_data_assigns')
                        ->where('project_template_id', $projectTemplateData->id)
                        ->whereIn('common_id', $getAuditCommonIds)
                        ->distinct('activity_id')
                        ->pluck('activity_id')->toArray();

                    if (count($getActivities) > 0) {
                        $is_distributor_assign = true;
                    }
                }

                if (!$exists) {
                    $userAssignedActivities[] = [
                        'template_name_id' => $assigned_data->template_name_id,
                        'template_name' => $current_template_name,
                        'is_master' => $is_master_temp,
                        'activity_id' => $assigned_data->activity_id,
                        'activity_name' => $current_activity_name,
                        'group_id' => $groupActivity ?? null,
                        'group_name' => $group_name,
                        'sequence' => (string)$sequence,
                        'otp_required' => $otpRequiredStatus,
                        'status' => $status,
                        'is_activity_answered' => $is_activity_answered,
                        'is_distributor_assign' => $is_distributor_assign
                    ];
                }

//                if($projectTemplateData->is_master == 0){
//
//                }
//                if ($assigned_data->is_outlet_assigned) {
//                    foreach ($project_other_templates as $other_project_template_info) {
//                        if ($other_project_template_info->activityType) {
//                            $group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->find($other_project_template_info->activity_group_name_id_or_activity_id);
//                            foreach ($group_info->get_group_activities as $group_activity_info) {
//                                $current_template_name = $other_project_template_info->getTemplate->template_name;
//                                $current_activity_name = $group_activity_info->getActivityInfo->activity_name;
//                                $exists = false;
//                                foreach ($userAssignedActivities as $activity) {
//                                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
//                                        $exists = true;
//                                        break; // Exit loop if a match is found
//                                    }
//                                }
//
//                                if (!$exists) {
//                                    $userAssignedActivities[] = [
//                                        'template_name_id' => $other_project_template_info->getTemplate->id,
//                                        'template_name' => $other_project_template_info->getTemplate->template_name,
//                                        'is_master' => 0,
//                                        'activity_id' => $group_activity_info->activity_id,
//                                        'activity_name' => $group_activity_info->getActivityInfo->activity_name,
//                                        'group_id' => $group_info->id, // group id
//                                        'group_name' => $group_info->activity_group_name,
//                                        'sequence' => $group_activity_info->sequence
//                                    ];
//                                }
//                            }
//                        } else {
//                            $current_template_name = $other_project_template_info->getTemplate->template_name;
//                            $current_activity_name = $other_project_template_info->activity->activity_name;
//                            $exists = false;
//                            foreach ($userAssignedActivities as $activity) {
//                                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
//                                    $exists = true;
//                                    break; // Exit loop if a match is found
//                                }
//                            }
//
//                            if (!$exists) {
//                                $userAssignedActivities[] = [
//                                    'template_name_id' => $other_project_template_info->getTemplate->id,
//                                    'template_name' => $other_project_template_info->getTemplate->template_name,
//                                    'is_master' => 0,
//                                    'activity_id' => $other_project_template_info->activity->id,
//                                    'activity_name' => $other_project_template_info->activity->activity_name,
//                                    'group_id' => null,
//                                    'group_name' => null,
//                                    'sequence' => null
//                                ];
//                            }
//                        }
//                    }
//                }
            }


            $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();


            return response([
                'status' => 200,
                'message' => 'success',
                'row_id' => $row_id,
                'activity_group_id' => $groupActivity,
                'add_outlet' => $data_add_on,
                'project_id' => $projectInfo->id,
                'template_name_id' => $template_name_id,
                'is_outlet_assigned' => $is_outlet_assigned,
                'distributor_value' => $distributor_value,
                'data' => $userAssignedActivities
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }


    public function getAllActivity_Old($row_id, $user_id, $is_outlet_assigned)
    {

        try {

            $user = $user_id;
            $userAssignedActivities = [];
            $groupActivity = null;
            $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
            $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
            $projectInfo = Project::find($projectTemplateData->project_id);
            $project_master_template = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                ->where('is_master', 1)
                ->first();

            $template_name_id = $projectTemplateData->template_name_id;
            $data_add_on = $projectTemplateData->data_add_on = 1 ? true : false;

            $getHeadValues = DB::table('project_template_name_values_new')
                ->where('id', $row_id)
                ->select(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateData->main_header}\"')) as head_value")
                )
                ->get();

            $distributor_value = $getHeadValues[0]->head_value;

            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')
                ->where('project_id', $projectTemplateData->project_id)
                ->where('id', '!=', $project_master_template->id)
                ->get();


            //khushboo 07-07-2025
            $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user)
                ->where('project_template_id', $projectTemplateData->id)
                ->distinct('data_assign_id')
                ->pluck('data_assign_id');
            //khushboo 07-07-2025

            $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
                ->where('project_id', $projectTemplateData->project_id)
                ->where('project_template_id', $projectTemplateData->id)
                ->whereIn("id", $distinct_data_assignIds)
                ->get();

            $otpRequiredStatus = false;
            $distinct_data_assign_activity_Ids = UserActivityDataAssign::where('user_id', $user)
                ->where('project_template_id', $projectTemplateData->id)
                ->distinct('activity_id')
                ->pluck('activity_id')->toArray();

            if (
                $projectInfo->is_otp_required == 1 &&
                !empty($projectTemplateData->activity_otp_required_ids)
            ) {
                $otpRequiredIds = json_decode($projectTemplateData->activity_otp_required_ids, true);

                if (!empty($otpRequiredIds) && count(array_intersect($distinct_data_assign_activity_Ids, $otpRequiredIds)) > 0) {
                    $otpRequiredStatus = true;
                }
            }

            foreach ($data_assign_info as $assigned_data) {

                $sequence = null;
                if (isset($assigned_data->activity_group_id)) {
                    $groupActivity = $assigned_data->activity_group_id;
                    $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                        ->where('activity_group_id', $assigned_data->activity_group_id)
                        ->first();
                    $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : 1;
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

                $is_activity_answered = false;
                $status = 'pending';
                $totalQuestions = Question::where('activity_id', $assigned_data->activity_id)->count();
                $getAnsweredQuestionsCount = DB::table('temp_user_activity_answers_data')
                    ->where('activity_id', $assigned_data->activity_id)
                    ->where('row_id', $row_id)
                    ->where('user_id', $user_id)
                    ->count();

                if ($totalQuestions == $getAnsweredQuestionsCount) {
                    $is_activity_answered = true;
                    $getAllQuestionIds = Question::where('activity_id', $assigned_data->activity_id)->pluck('id')->toArray();
                    //otp verified status
                    $lastQuestionAnswered = DB::table('temp_user_activity_answers_data')
                        ->where('activity_id', $assigned_data->activity_id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->where('row_id', $row_id)
                        ->where('user_id', $user_id)
                        ->orderBy('id', 'DESC')->first();
                    //rejected status
                    $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $row_id)
                        ->where('activity_id', $assigned_data->activity_id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->where('status', 4)
                        ->count();

                    if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                        $status = 'completed';
                    } else if ($rejectedAnswersCount > 0) {
                        $status = "rejected";
                    } else {
                        $status = 'Awaiting OTP Verify';
                    }

                }

                $is_distributor_assign = false;
                if ($projectTemplateData->is_master == 1) {
                    $getAuditCommonIds = DB::table('user_audit_assigns')->where('row_id', $row_id)
                        ->distinct('common_id')
                        ->pluck('common_id')
                        ->toArray();

                    $getActivities = DB::table('user_activity_data_assigns')
                        ->where('project_template_id', $projectTemplateData->id)
                        ->whereIn('common_id', $getAuditCommonIds)
                        ->distinct('activity_id')
                        ->pluck('activity_id')->toArray();

                    if (count($getActivities) > 0) {
                        $is_distributor_assign = true;
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
                        'sequence' => (string)$sequence,
                        'otp_required' => $otpRequiredStatus,
                        'status' => $status,
                        'is_activity_answered' => $is_activity_answered,
                        'is_distributor_assign' => $is_distributor_assign
                    ];
                }

//                if($projectTemplateData->is_master == 0){
//
//                }
//                if ($assigned_data->is_outlet_assigned) {
//                    foreach ($project_other_templates as $other_project_template_info) {
//                        if ($other_project_template_info->activityType) {
//                            $group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->find($other_project_template_info->activity_group_name_id_or_activity_id);
//                            foreach ($group_info->get_group_activities as $group_activity_info) {
//                                $current_template_name = $other_project_template_info->getTemplate->template_name;
//                                $current_activity_name = $group_activity_info->getActivityInfo->activity_name;
//                                $exists = false;
//                                foreach ($userAssignedActivities as $activity) {
//                                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
//                                        $exists = true;
//                                        break; // Exit loop if a match is found
//                                    }
//                                }
//
//                                if (!$exists) {
//                                    $userAssignedActivities[] = [
//                                        'template_name_id' => $other_project_template_info->getTemplate->id,
//                                        'template_name' => $other_project_template_info->getTemplate->template_name,
//                                        'is_master' => 0,
//                                        'activity_id' => $group_activity_info->activity_id,
//                                        'activity_name' => $group_activity_info->getActivityInfo->activity_name,
//                                        'group_id' => $group_info->id, // group id
//                                        'group_name' => $group_info->activity_group_name,
//                                        'sequence' => $group_activity_info->sequence
//                                    ];
//                                }
//                            }
//                        } else {
//                            $current_template_name = $other_project_template_info->getTemplate->template_name;
//                            $current_activity_name = $other_project_template_info->activity->activity_name;
//                            $exists = false;
//                            foreach ($userAssignedActivities as $activity) {
//                                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
//                                    $exists = true;
//                                    break; // Exit loop if a match is found
//                                }
//                            }
//
//                            if (!$exists) {
//                                $userAssignedActivities[] = [
//                                    'template_name_id' => $other_project_template_info->getTemplate->id,
//                                    'template_name' => $other_project_template_info->getTemplate->template_name,
//                                    'is_master' => 0,
//                                    'activity_id' => $other_project_template_info->activity->id,
//                                    'activity_name' => $other_project_template_info->activity->activity_name,
//                                    'group_id' => null,
//                                    'group_name' => null,
//                                    'sequence' => null
//                                ];
//                            }
//                        }
//                    }
//                }
            }
            $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();

            return response([
                'status' => 200,
                'message' => 'success',
                'row_id' => $row_id,
                'add_outlet' => $data_add_on,
                'project_id' => $projectInfo->id,
                'template_name_id' => $template_name_id,
                'is_outlet_assigned' => $is_outlet_assigned,
                'distributor_value' => $distributor_value,
                'data' => $userAssignedActivities
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function projectDistributorData(Request $request)
    {
        $startTime = microtime(true);
        $user = $request->user_id;
        $project_data_arr = [];

        $project_template_details = DB::table('project_templates')->where('project_id', $request->project_id)
            ->get();
        $project_temp_info = DB::table('project_templates')->where('project_id', $request->project_id)
            ->where('is_master', 1)
            ->first();
        $template_name_id = $project_temp_info->template_name_id;
        $can_edit_data = false;
        if ($project_temp_info->can_edit_data == 1) {
            $can_edit_data = true;
        }

        //khushboo 17-05-25
        $add_outlet = false;
        if ($project_temp_info->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-25

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        $projectInfo = Project::findOrFail($request->project_id);

        if (
            $projectInfo->is_otp_required == 1 &&
            !empty($project_temp_info->activity_otp_required_ids)
        ) {
            $otpRequiredIds = json_decode($project_temp_info->activity_otp_required_ids, true);

//            if (!empty($otpRequiredIds) && in_array($project_temp_info->activity_group_name_id_or_activity_id, $otpRequiredIds)) {
            $otpRequiredStatus = 1;
//            }
        }
        //khushboo 07-04-2025

        // dd($request->project_id, $request->template_id);
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;

        $totalcompletedproject = 0;
        $totalproject = 0;

        $isOutletAssigned = 0;

        $distributorsValueNotAssigned = [];
        $row_renderred_arr = [];
        $project_template_details = collect($project_template_details)
            ->sortByDesc(function ($item) {
                return $item->is_master; // 1 first, then 0
            })
            ->values(); // reset keys

        $project_temp_child_info_Ids = DB::table('project_templates')
            ->where('project_id', $project_temp_info->project_id)
            ->where('is_master', 0)->pluck('id')->toArray();


        $childProjectTemplatesData = DB::table('project_template_name_values_new')
            ->whereIn('project_template_id', $project_temp_child_info_Ids)->get();

        foreach ($project_template_details as $tempKey => $tempData) {

            $getDataAssignIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('id')
                ->pluck('id');

            $OutletAssignedExists = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->where('is_outlet_assigned', 1)
                ->exists();


            $getDataAssignTemplateHeadIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('template_name_head_id')
                ->pluck('template_name_head_id');

            $checkingIfProjectAssigned = DB::table('user_activity_data_assigns')->where('user_id', $user)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->exists();

            $dataAssignCommonIds = DB::table('user_activity_data_assigns')->where('user_id', $user)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->where('project_template_id', $tempData->id)
                ->distinct('common_id')
                ->pluck('common_id')
                ->toArray();

            $activitiesids = DB::table('user_activity_data_assigns')->where('user_id', $user)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->where('project_template_id', $tempData->id)
                ->distinct('activity_id')
                ->pluck('activity_id')
                ->toArray();

            $getRowIds = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')->pluck('row_id')->toArray();
            // dd($getRowIds);

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
                                $isOutletAssigned = 1;   // ✅ set true only here
                            }
                        } else {

                            $getHeadValues = DB::table('project_template_name_values_new')
                                ->whereIn('id', $getRowIds)
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();
//                            dd($getHeadValues);

                            $getAllMasterRowHeads = DB::table('project_template_name_values_new')
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();

                            $onlyInHeadValues = $getHeadValues->pluck('head_value')->filter()->diff(
                                $getAllMasterRowHeads->pluck('head_value')->filter()
                            );

                            $onlyInMaster = $getAllMasterRowHeads->pluck('head_value')->filter()->diff(
                                $getHeadValues->pluck('head_value')->filter()
                            );

                            // Combined non-matching values
                            $nonMatching = $onlyInHeadValues->merge($onlyInMaster)->unique();

                            // convert collection → array and merge
                            $distributorsValueNotAssigned = array_merge($distributorsValueNotAssigned, $nonMatching->values()->toArray());

                        }

                        $project_templates_data = ProjectTemplate::where('project_id', $request->project_id)
                            ->where('is_master', 1)
                            ->first();

                        if (count($getHeadValues) > 0) {
                            foreach ($getHeadValues as $headValue) {
//                                dd($headValue->head_value);
                                if ($headValue->head_value) {
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
                $projectTemplateIDs = array_column($projectTemplateHeadsAssigned, 'project_template_id');
//                dd($projectTemplateIDs);

                if ($tempData->is_master == 1) {

                    $allTemplates = DB::table('project_template_name_values_new')
                        ->select('id as row_id', 'project_template_id', 'template_data_json')
                        ->whereIn('project_template_id', $projectTemplateIDs)
                        ->get();

                    if ($OutletAssignedExists) {
                        $isOutletAssigned = 1;
                    }

                } else if ($tempData->is_master == 0) {

                    $templateHeadValues = array_column($projectTemplateHeadsAssigned, 'head_value');

                    $mainHeaderValues = DB::table('project_template_name_values_new')
                        ->where(function ($query) use ($templateHeadValues) {
                            foreach ($templateHeadValues as $value) {
                                $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                            }
                        })
                        ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$templateMainHeaderId\"')) as main_header_value"));

                    $allTemplates = DB::table('project_template_name_values_new')
                        ->select('*', 'id as row_id')
                        ->whereIn('id', $getRowIds)
                        ->whereIn('project_template_id', $projectTemplateIDs)
                        ->where(function ($query) use ($mainHeaderValues) {
                            foreach ($mainHeaderValues as $value) {
                                $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                            }
                        })
                        ->get();
//                    dd($allTemplates, $projectTemplateIDs, $mainHeaderValues);

                }

                $templateHeads = DB::table('template_name_heads')
                    ->select('id', 'template_name_id', 'template_head_name', 'created_at', 'updated_at', 'deleted_at')
                    ->get()
                    ->keyBy('id');

                $allTemplateValues = [];
                // dd($templateHeads, $allTemplates);

                foreach ($allTemplates as $row) {
                    $jsonData = json_decode($row->template_data_json, true);
                    // dd($templateHeads, $jsonData);
                    foreach ($jsonData as $templateNameHeadId => $value) {
                        // if (isset($templateHeads[$templateNameHeadId])) {

                        $head = $templateHeads[$templateNameHeadId] ?? null;
//                         dd($head, $value);

                        $allTemplateValues[$row->project_template_id][] = (object)[
                            'row_id' => $row->row_id,
                            'project_template_id' => $row->project_template_id,
                            'template_name_head_id' => $templateNameHeadId,
                            'template_data_json' => $row->template_data_json,
                            'value' => $value,
                            'head_id' => $templateNameHeadId,
                            'head_template_name_id' => $head->template_name_id ?? "",
                            'template_head_name' => $head->template_head_name ?? "",
                            'head_created_at' => $head->created_at ?? "",
                            'head_updated_at' => $head->updated_at ?? "",
                            'head_deleted_at' => $head->deleted_at ?? "",
                        ];
                        // }
                    }
                }

//                 dd($allTemplateValues);
                // Group by row_id from all project templates
                $allTemplateValuesFlat = collect($allTemplateValues)->flatMap(function ($group) {
                    return $group;
                });

                $rowGroupedValues = $allTemplateValuesFlat->groupBy('row_id');
                // dd($projectTemplateHeadsAssigned, $allTemplateValues);

                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
                    $template_id = $assigned_value_info['project_template_id'];
                    // echo $assigned_value_info['head_value'];
                    $templateValues = collect($allTemplateValues[$template_id] ?? []);
//                    dd($templateValues);
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
//                    dd($activities);
                    $previous_sequence_answered_rows = [];
                    foreach ($activities as $activityId) {

                        $rows_in_templates = $templateValues->pluck('row_id')->unique()->toArray();
                        $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')
                            ->where('activity_id', $activityId)
                            ->whereIn('row_id', $rows_in_templates)
                            ->distinct('row_id')
                            ->pluck('row_id')->toArray();
                        if (empty($previous_sequence_answered_rows)) {
                            // For the first iteration, just set the array
                            $previous_sequence_answered_rows = $get_previous_sequence_answered;
                        } else {
                            // For subsequent iterations, keep only common values between the arrays
                            $previous_sequence_answered_rows = array_intersect($previous_sequence_answered_rows, $get_previous_sequence_answered);
                        }
                    }

                    $previous_sequence_answered_rows = array_unique($previous_sequence_answered_rows);

                    $projectDataArr = $templateValues
                        ->where('template_name_head_id', $assigned_value_info['head_id'])
                        ->whereIn('row_id', $getRowIds)
                        ->where('value', $assigned_value_info['head_value']);

                    if ($projectDataArr->IsEmpty()) {
                        $projectDataArr = collect($templateValues)
                            ->where('template_name_head_id', $assigned_value_info['head_id'])
                            ->where('value', $assigned_value_info['head_value']);
                    }

                    $getAllQuestionIds = DB::table('questions')
                        ->whereIn('activity_id', $activities)
                        ->where('answer_type', 1)
                        ->pluck('id')
                        ->toArray();

                    $all_row_ids = array_column($projectDataArr->toArray(), 'row_id');

                    $allTemplateActivityAnswers = DB::table('temp_user_activity_answers_data')
                        ->whereIn('activity_id', $activitiesids)
                        ->whereIn('row_id', $all_row_ids)
                        // ->whereIn('question_id', $getAllQuestionIds)
                        ->get()
                        ->groupBy('row_id'); // group it for faster per-row access

                    // dd($projectDataArr);
                    foreach ($projectDataArr as $projectData) {


                        if (!in_array($projectData->row_id, $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData->row_id;

                            $totalQuestions = count($getAllQuestionIds);

                            $filteredAnswers = $allTemplateActivityAnswers[$projectData->row_id] ?? collect();

                            // $answeredCount = $filteredAnswers
                            //     ->pluck('question_id')
                            //     ->unique()
                            //     ->count();

                            $answeredCount = DB::table('temp_user_activity_answers_data')
                                ->whereIn('activity_id', $activitiesids)
                                ->whereIn('row_id', $all_row_ids)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->pluck('question_id')
                                ->unique()
                                ->count();


                            $allAnswered = $answeredCount === $totalQuestions;
                            $otpVerificationDone = false;

                            // if ($allAnswered) {
                            $lastQuestionAnswered = $filteredAnswers
                                ->sortByDesc('id')
                                ->first();

                            if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                                $otpVerificationDone = true;
                                $allAnswered = true;
                            }
                            // }

                            $get_row_header = $rowGroupedValues[$projectData->row_id]
                                ->firstWhere('template_name_head_id', $main_header_id);

                            $main_header = null;
                            if ($get_row_header) {
                                $main_header = $get_row_header->value;
                            }

                            $get_row_sub_header = $rowGroupedValues[$projectData->row_id]
                                ->firstWhere('template_name_head_id', $sub_header_id);

                            $sub_header = null;
                            if ($get_row_sub_header) {
                                $sub_header = $get_row_sub_header->value;
                            }

                            //khushboo 02-05-2025
                            $projectStatus = "pending";
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
                                // } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                // $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }
                            //khushboo 02-05-2025

                            $get_head_name = [
                                'id' => $projectData->head_id,
                                'template_name_id' => $projectData->head_template_name_id,
                                'template_head_name' => $projectData->template_head_name,
                                'created_at' => $projectData->head_created_at,
                                'updated_at' => $projectData->head_updated_at,
                                'deleted_at' => $projectData->head_deleted_at,
                            ];

                            //khushboo 28-06-25
                            $latitudeValue = null;
                            $longitudeValue = null;

                            $latitudeHeadData = DB::table('template_name_heads')
                                ->where('template_name_id', $project_temp_info->template_name_id)
                                ->whereIn('template_head_name', ['Latitude', 'Longitude', 'latitude', 'longitude', 'lat', 'long', 'Lat', 'Long'])
                                ->first();

                            $longitudeHeadData = DB::table('template_name_heads')
                                ->where('template_name_id', $project_temp_info->template_name_id)
                                ->whereIn('template_head_name', ['Latitude', 'Longitude', 'latitude', 'longitude', 'lat', 'long', 'Lat', 'Long'])
                                ->first();

                            $latitudeHeaders = ['Latitude', 'latitude', 'lat', 'Lat'];
                            $longitudeHeaders = ['Longitude', 'longitude', 'long', 'Long'];

                            $latitudeValue = optional(
                                $rowGroupedValues[$projectData->row_id] ?? collect()
                            )->first(function ($item) use ($latitudeHeaders) {
                                return in_array($item->template_head_name, $latitudeHeaders);
                            })?->value;

                            $longitudeValue = optional(
                                $rowGroupedValues[$projectData->row_id] ?? collect()
                            )->first(function ($item) use ($longitudeHeaders) {
                                return in_array($item->template_head_name, $longitudeHeaders);
                            })?->value;

                            //khushboo 28-06-25
                            $totalproject++;
                            if ($projectStatus == 'completed') {
                                $totalcompletedproject++;
                            }


                            $is_distributor_assign = true;
                            if (in_array($assigned_value_info['head_value'], $distributorsValueNotAssigned)) {
                                $is_distributor_assign = false;
                            }

                            $tempjsondata = json_decode($projectData->template_data_json, true);

                            // get only values, ignore keys
                            $allValues = array_values($tempjsondata);


                            $exists = $childProjectTemplatesData
                                ->whereIn('project_template_id', $project_temp_child_info_Ids)
                                ->contains(function ($item) use ($allValues) {
                                    $json = json_decode($item->template_data_json, true);
                                    return count(array_intersect($allValues, $json)) > 0;
                                });

                            // dd($exists); // true or false


                            $project_data_arr[] = [
                                'data_item' => (array)$projectData + [
                                        'get_head_name' => $get_head_name,
                                        'get_data_of_rows' => $rowGroupedValues[$projectData->row_id] ?? collect()
                                    ],
                                'status' => $projectStatus,
                                'main_header' => $main_header,
                                'sub_header' => $sub_header,
                                'latitude' => $latitudeValue,
                                'longitude' => $longitudeValue,
                                'is_outlet_assigned' => $isOutletAssigned && $exists ? 1 : 0,
                                'is_distributor_assign' => $is_distributor_assign
                            ];
                        }
                    }
                }
            }
        }

        if ($totalcompletedproject == $totalproject) {
            $add_outlet = false;
        }

        Session::put('project_dist_activity', ['project' => $request->project_id]);
        if (Session::has('project_outlet_activity')) {
            Session::forget('project_outlet_activity');
        }
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        if (empty($project_data_arr)) {
            return response([
                'status' => 401,
                'message' => 'success',
                'data' => $project_data_arr,
            ], 401);
        }

        $project_data_arr = collect($project_data_arr);
        // Now you can use the where method
        if ($request->status) {
            $project_data_arr = $project_data_arr->where('status', $request->status);
        }

        if ($request->keyword) {
            $project_data_arr = $project_data_arr->filter(function ($item) use ($request) {
                return strpos(strtolower($item['main_header']), strtolower($request->keyword)) !== false ||
                    strpos(strtolower($item['sub_header']), strtolower($request->keyword)) !== false;
            });
        }

        $distributors_collection = collect($project_data_arr);
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $currentPageItems = $distributors_collection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedDistributors = new LengthAwarePaginator(
            $currentPageItems,
            $distributors_collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->except('_token')]
        );

        // for empty or partial data
        if ($project_temp_info->with_data == 0) {
            $add_outlet = true;
        }

        return response([
            'status' => 200,
            'message' => 'success',
            'project_id' => $request->project_id,
            'template_id' => $template_name_id,
            'data' => $paginatedDistributors,
            'otp_required_status' => (string)$otpRequiredStatus,
            'add_outlet' => $add_outlet,
            'can_edit_data' => $can_edit_data
        ], 200);

    }


    public function projectDistributorDataOld09September(Request $request)
    {
        $startTime = microtime(true);
        $row_renderred_arr = [];
        $user = $request->user_id;
        $project_data_arr = [];

        $project_data_completed_arr = [];
        $project_template_details = DB::table('project_templates')->where('project_id', $request->project_id)
            ->get();

        $project_temp_info = DB::table('project_templates')->where('project_id', $request->project_id)
            ->where('is_master', 1)
            ->first();

        $template_name_id = $project_temp_info->template_name_id;

        //khushboo 17-05-25
        $add_outlet = false;
        if ($project_temp_info->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-25

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        $projectInfo = Project::findOrFail($request->project_id);

        if (
            $projectInfo->is_otp_required == 1 &&
            !empty($project_temp_info->activity_otp_required_ids)
        ) {
            $otpRequiredIds = json_decode($project_temp_info->activity_otp_required_ids, true);

//            if (!empty($otpRequiredIds) && in_array($project_temp_info->activity_group_name_id_or_activity_id, $otpRequiredIds)) {
            $otpRequiredStatus = 1;
//            }
        }
        //khushboo 07-04-2025


        // dd($request->project_id, $request->template_id);
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;

        $totalcompletedproject = 0;
        $totalproject = 0;

        $isOutletAssigned = 0;
        $distributorsValueNotAssigned = [];
        $project_template_details = collect($project_template_details)
            ->sortByDesc(function ($item) {
                return $item->is_master; // 1 first, then 0
            })
            ->values(); // reset keys

        foreach ($project_template_details as $tempKey => $tempData) {

            $getDataAssignIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('id')
                ->pluck('id');

            $OutletAssignedExists = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->where('is_outlet_assigned', 1)
                ->exists();

            $getDataAssignTemplateHeadIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $tempData->template_name_id)
                ->distinct('template_name_head_id')
                ->pluck('template_name_head_id');

            $checkingIfProjectAssigned = DB::table('user_activity_data_assigns')->where('user_id', $user)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->exists();

            $dataAssignCommonIds = DB::table('user_activity_data_assigns')->where('user_id', $user)
                ->whereIn('data_assign_id', $getDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id')
                ->toArray();

            $getRowIds = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')->pluck('row_id')->toArray();

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
                                $isOutletAssigned = 1;   // ✅ set true only here
                            }
                        } else {

                            $getHeadValues = DB::table('project_template_name_values_new')
                                ->whereIn('id', $getRowIds)
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();
//                            dd($getHeadValues);

                            $getAllMasterRowHeads = DB::table('project_template_name_values_new')
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();

                            $onlyInHeadValues = $getHeadValues->pluck('head_value')->filter()->diff(
                                $getAllMasterRowHeads->pluck('head_value')->filter()
                            );

                            $onlyInMaster = $getAllMasterRowHeads->pluck('head_value')->filter()->diff(
                                $getHeadValues->pluck('head_value')->filter()
                            );

                            // Combined non-matching values
                            $nonMatching = $onlyInHeadValues->merge($onlyInMaster)->unique();

                            // convert collection → array and merge
                            $distributorsValueNotAssigned = array_merge($distributorsValueNotAssigned, $nonMatching->values()->toArray());

                        }

                        $project_templates_data = ProjectTemplate::where('project_id', $request->project_id)
                            ->where('is_master', 1)
                            ->first();

                        if (count($getHeadValues) > 0) {
                            foreach ($getHeadValues as $headValue) {
//                                dd($headValue->head_value);
                                if ($headValue->head_value) {
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
                $projectTemplateIDs = array_column($projectTemplateHeadsAssigned, 'project_template_id');
//                dd($projectTemplateIDs);

                if ($tempData->is_master == 1) {

                    $allTemplates = DB::table('project_template_name_values_new')
                        ->select('id as row_id', 'project_template_id', 'template_data_json')
                        ->whereIn('project_template_id', $projectTemplateIDs)
                        ->get();

                    if ($OutletAssignedExists) {
                        $isOutletAssigned = 1;
                    }

                } else if ($tempData->is_master == 0) {

                    $templateHeadValues = array_column($projectTemplateHeadsAssigned, 'head_value');

                    $mainHeaderValues = DB::table('project_template_name_values_new')
                        ->where(function ($query) use ($templateHeadValues) {
                            foreach ($templateHeadValues as $value) {
                                $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                            }
                        })
                        ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$templateMainHeaderId\"')) as main_header_value"));

                    $allTemplates = DB::table('project_template_name_values_new')
                        ->select('*', 'id as row_id')
                        ->whereIn('project_template_id', $projectTemplateIDs)
                        ->where(function ($query) use ($mainHeaderValues) {
                            foreach ($mainHeaderValues as $value) {
                                $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                            }
                        })
                        ->get();
//                    dd($allTemplates, $projectTemplateIDs, $mainHeaderValues);

                }

                $templateHeads = DB::table('template_name_heads')
                    ->select('id', 'template_name_id', 'template_head_name', 'created_at', 'updated_at', 'deleted_at')
                    ->get()
                    ->keyBy('id');

                $allTemplateValues = [];
//                dd($allTemplates);

                foreach ($allTemplates as $row) {
                    $jsonData = json_decode($row->template_data_json, true);

                    foreach ($jsonData as $templateNameHeadId => $value) {
                        if (isset($templateHeads[$templateNameHeadId])) {
                            $head = $templateHeads[$templateNameHeadId];

                            $allTemplateValues[$row->project_template_id][] = (object)[
                                'row_id' => $row->row_id,
                                'project_template_id' => $row->project_template_id,
                                'template_name_head_id' => $templateNameHeadId,
                                'value' => $value,
                                'head_id' => $templateNameHeadId,
                                'head_template_name_id' => $head->template_name_id,
                                'template_head_name' => $head->template_head_name,
                                'head_created_at' => $head->created_at,
                                'head_updated_at' => $head->updated_at,
                                'head_deleted_at' => $head->deleted_at,
                            ];
                        }
                    }
                }

//                dd($allTemplateValues);
                // Group by row_id from all project templates
                $allTemplateValuesFlat = collect($allTemplateValues)->flatMap(function ($group) {
                    return $group;
                });

                $rowGroupedValues = $allTemplateValuesFlat->groupBy('row_id');
//                print_r($projectTemplateHeadsAssigned);
                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
                    $template_id = $assigned_value_info['project_template_id'];
                    $templateValues = collect($allTemplateValues[$template_id] ?? []);
//                    dd($templateValues);
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
//                    dd($activities);
                    $previous_sequence_answered_rows = [];
                    foreach ($activities as $activityId) {

                        $rows_in_templates = $templateValues->pluck('row_id')->unique()->toArray();
                        $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')
                            ->where('activity_id', $activityId)
                            ->whereIn('row_id', $rows_in_templates)
                            ->distinct('row_id')
                            ->pluck('row_id')->toArray();
                        if (empty($previous_sequence_answered_rows)) {
                            // For the first iteration, just set the array
                            $previous_sequence_answered_rows = $get_previous_sequence_answered;
                        } else {
                            // For subsequent iterations, keep only common values between the arrays
                            $previous_sequence_answered_rows = array_intersect($previous_sequence_answered_rows, $get_previous_sequence_answered);
                        }
                    }

                    $previous_sequence_answered_rows = array_unique($previous_sequence_answered_rows);

                    $projectDataArr = $templateValues
                        ->where('template_name_head_id', $assigned_value_info['head_id'])
                        ->whereIn('row_id', $previous_sequence_answered_rows)
                        ->where('value', $assigned_value_info['head_value']);

                    if ($projectDataArr->IsEmpty()) {
                        $projectDataArr = collect($templateValues)
                            ->where('template_name_head_id', $assigned_value_info['head_id'])
                            ->where('value', $assigned_value_info['head_value']);
                    }

                    $getAllQuestionIds = DB::table('questions')
                        ->whereIn('activity_id', $activities)
                        ->pluck('id')
                        ->toArray();

                    $all_row_ids = array_column($projectDataArr->toArray(), 'row_id');

                    $allTemplateActivityAnswers = DB::table('temp_user_activity_answers_data')
                        ->where('activity_id', $request->activity_id)
                        ->whereIn('row_id', $all_row_ids)
                        ->get()
                        ->groupBy('row_id'); // group it for faster per-row access

                    foreach ($projectDataArr as $projectData) {

                        if (!in_array($projectData->row_id, $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData->row_id;

                            $totalQuestions = count($getAllQuestionIds);

                            $filteredAnswers = $allTemplateActivityAnswers[$projectData->row_id] ?? collect();

                            $answeredCount = $filteredAnswers
                                ->pluck('question_id')
                                ->unique()
                                ->count();

                            $allAnswered = $answeredCount === $totalQuestions;
                            $otpVerificationDone = false;

//                            if ($allAnswered) {
                            $lastQuestionAnswered = $filteredAnswers
                                ->sortByDesc('id')
                                ->first();

                            if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                                $otpVerificationDone = true;
                                $allAnswered = true;
                            }
//                            }

                            $get_row_header = $rowGroupedValues[$projectData->row_id]
                                ->firstWhere('template_name_head_id', $main_header_id);

                            $main_header = null;
                            if ($get_row_header) {
                                $main_header = $get_row_header->value;
                            }

                            $get_row_sub_header = $rowGroupedValues[$projectData->row_id]
                                ->firstWhere('template_name_head_id', $sub_header_id);

                            $sub_header = null;
                            if ($get_row_sub_header) {
                                $sub_header = $get_row_sub_header->value;
                            }

                            //khushboo 02-05-2025
                            $projectStatus = "pending";
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
//                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
//                                $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }
                            //khushboo 02-05-2025

                            $get_head_name = [
                                'id' => $projectData->head_id,
                                'template_name_id' => $projectData->head_template_name_id,
                                'template_head_name' => $projectData->template_head_name,
                                'created_at' => $projectData->head_created_at,
                                'updated_at' => $projectData->head_updated_at,
                                'deleted_at' => $projectData->head_deleted_at,
                            ];

                            //khushboo 28-06-25
                            $latitudeValue = null;
                            $longitudeValue = null;

                            $latitudeHeadData = DB::table('template_name_heads')
                                ->where('template_name_id', $project_temp_info->template_name_id)
                                ->whereIn('template_head_name', ['Latitude', 'Longitude', 'latitude', 'longitude', 'lat', 'long', 'Lat', 'Long'])
                                ->first();

                            $longitudeHeadData = DB::table('template_name_heads')
                                ->where('template_name_id', $project_temp_info->template_name_id)
                                ->whereIn('template_head_name', ['Latitude', 'Longitude', 'latitude', 'longitude', 'lat', 'long', 'Lat', 'Long'])
                                ->first();

                            $latitudeHeaders = ['Latitude', 'latitude', 'lat', 'Lat'];
                            $longitudeHeaders = ['Longitude', 'longitude', 'long', 'Long'];

                            $latitudeValue = optional(
                                $rowGroupedValues[$projectData->row_id] ?? collect()
                            )->first(function ($item) use ($latitudeHeaders) {
                                return in_array($item->template_head_name, $latitudeHeaders);
                            })?->value;

                            $longitudeValue = optional(
                                $rowGroupedValues[$projectData->row_id] ?? collect()
                            )->first(function ($item) use ($longitudeHeaders) {
                                return in_array($item->template_head_name, $longitudeHeaders);
                            })?->value;

                            //khushboo 28-06-25
                            $totalproject++;
                            if ($projectStatus == 'completed') {
                                $totalcompletedproject++;
                            }

                            $is_distributor_assign = true;
                            if (in_array($assigned_value_info['head_value'], $distributorsValueNotAssigned)) {
                                $is_distributor_assign = false;
                            }

                            $project_data_arr[] = [
                                'data_item' => (array)$projectData + [
                                        'get_head_name' => $get_head_name,
                                        'get_data_of_rows' => $rowGroupedValues[$projectData->row_id] ?? collect()
                                    ],
                                'status' => $projectStatus,
                                'main_header' => $main_header,
                                'sub_header' => $sub_header,
                                'latitude' => $latitudeValue,
                                'longitude' => $longitudeValue,
                                'is_outlet_assigned' => $isOutletAssigned,
                                'is_distributor_assign' => $is_distributor_assign
                            ];
                        }
                    }
                }
            }
        }

        if ($totalcompletedproject == $totalproject) {
            $add_outlet = false;
        }

        Session::put('project_dist_activity', ['project' => $request->project_id]);
        if (Session::has('project_outlet_activity')) {
            Session::forget('project_outlet_activity');
        }
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        if (empty($project_data_arr)) {
            return response([
                'status' => 401,
                'message' => 'success',
                'data' => $project_data_arr,
            ], 401);
        }

        $project_data_arr = collect($project_data_arr);
        // Now you can use the where method
        if ($request->status) {
            $project_data_arr = $project_data_arr->where('status', $request->status);
        }

        if ($request->keyword) {
            $project_data_arr = $project_data_arr->filter(function ($item) use ($request) {
                return strpos(strtolower($item['main_header']), strtolower($request->keyword)) !== false ||
                    strpos(strtolower($item['sub_header']), strtolower($request->keyword)) !== false;
            });
        }


        $distributors_collection = collect($project_data_arr);
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


        return response([
            'status' => 200,
            'message' => 'success',
            'project_id' => $request->project_id,
            'template_id' => $template_name_id,
            'data' => $paginatedDistributors,
            'otp_required_status' => (string)$otpRequiredStatus,
            'add_outlet' => $add_outlet,

        ], 200);


    }


    public function projectOutletData(Request $request)
    {

        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $startIndex = (($currentPage - 1) * $perPage + 1) - 1;
        $endIndex = ($currentPage * $perPage) - 1;
        $group_session_id = null;
        if (!isset($request->group_info_id)) {
            $group_info = null;
        } else {
            $group_session_id = $request->group_info_id;
        }
        $user = User::find($request->user_id);

        //khushboo 16-04-25
        $isAuditClosed = false;
        //khushboo 16-04-25

        $totalNumberOfOutlets = 0;
        $projectCompletedCount = 0;

        $activity = Activity::find($request->activity_id);

        $projectTemplateInfo = DB::table('project_templates')->where('project_id', $request->project_id)->get();

        $project_temp_info = $projectTemplateInfo->where('template_name_id', $request->template_id)
            ->first();

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        $projectInfo = Project::findOrFail($request->project_id);

        if (
            $projectInfo->is_otp_required == 1 &&
            !empty($project_temp_info->activity_otp_required_ids)
        ) {
            $otpRequiredIds = json_decode($project_temp_info->activity_otp_required_ids, true);

            if (!empty($otpRequiredIds) && in_array($request->activity_id, $otpRequiredIds)) {
                $otpRequiredStatus = 1;
            }
        }
        //khushboo 07-04-2025

        $distributors_arr = [];
        $outlet_master_info = $projectTemplateInfo->where('is_master', 1)
            ->first(); // this is to get the info of the master template of the project
//        dd($outlet_master_info->project_id);
        $activity_group_name_id_or_activity_id = $outlet_master_info->activity_group_name_id_or_activity_id;

        $master_head_id = $project_temp_info->master_head_id;
        $outlet_master_sub_header_id = $outlet_master_info->sub_header;
        $outlet_master_main_header_id = $outlet_master_info->main_header;
        $outlet_master_completion_type = $outlet_master_info->completion_type;
        $outlet_master_min_completion = $outlet_master_info->min_completion;
        $templateHeadName = DB::table('template_name_heads')->get();
        $master_head_info = $templateHeadName->where('id', $master_head_id)->first();
//        dd($master_head_info);
        $master_head_name = !empty($master_head_info) ? $master_head_info->template_head_name : '';
        $own_head_id = $project_temp_info->own_reference_head_id;
        $own_head_info = $templateHeadName->where('id', $own_head_id)->first();
        $cur_sequence_helper = 0;
        $activity_sequence = null;
        // This is to get the heads and key from outlet to the master template
        if (isset($request->group_info_id)) {
            $activity_sequence = DB::table('activity_group_pivots')->where('activity_id', $request->activity_id)
                ->where('activity_group_id', $request->group_info_id)
                ->first();
            if ($activity_sequence) {
                $cur_sequence_helper = $activity_sequence->sequence;
            }
        }

        $allTemplates = DB::table('project_template_name_values_new')
            ->select('id as row_id', 'project_template_id', 'template_data_json')
            ->where('project_template_id', $project_temp_info->id)
            ->get();

        $templateHeads = DB::table('template_name_heads')
            ->get()
            ->keyBy('id');

        $allTemplateNamesValues = [];
        foreach ($allTemplates as $row) {
            $jsonData = json_decode($row->template_data_json, true);
            foreach ($jsonData as $headId => $value) {
                if (isset($templateHeads[$headId])) {
                    $head = $templateHeads[$headId];
                    $allTemplateNamesValues[] = (object)[
                        'row_id' => $row->row_id,
                        'project_template_id' => $row->project_template_id,
                        'template_name_head_id' => $headId,
                        'value' => $value,
                        'head_id' => $headId,
                        'head_template_name_id' => $head->template_name_id,
                        'template_head_name' => $head->template_head_name,
                        'head_created_at' => $head->created_at,
                        'head_updated_at' => $head->updated_at,
                        'head_deleted_at' => $head->deleted_at,
                    ];
                }
            }
        }

        $allTemplateNamesValues = collect($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

//        dd($cur_sequence_helper);
//        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {

        $get_project_template_assigned_data = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $project_temp_info->id)
            ->where('activity_id', $request->activity_id)
            ->get();

        $dataAssignCommonIds = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $project_temp_info->id)
            ->where('activity_id', $request->activity_id)
            ->distinct('common_id')
            ->pluck('common_id');

        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

//            dd($get_distinct_data_assigned_ids);
        $getDataAssignTemplateHeadIds = DataAssign::whereIn('id', $get_distinct_data_assigned_ids)
            ->pluck('template_name_head_id')
            ->unique();

        $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
            ->pluck('row_id');

//            dd($getRowIds);
        foreach ($get_project_template_assigned_data as $assigned) {

            if ($assigned->audit_closed == 1) {
                $isAuditClosed = true;
            }

            foreach ($getDataAssignTemplateHeadIds as $headId) {

//                    dd($assigned->dataAssign->is_outlet_assigned);
                if ($assigned->dataAssign->is_outlet_assigned == 1) {

                    $getHeadValues = DB::table('project_template_name_values_new')
                        ->whereIn('id', $getRowIds)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$project_temp_info->own_reference_head_id}\"')) as head_value")
                        )
                        ->pluck('head_value');

                } else {

                    $getHeadValues = DB::table('project_template_name_values_new')
                        ->whereIn('id', $getRowIds)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                        )
                        ->pluck('head_value');

                }

//                dd($getHeadValues);
                foreach ($getHeadValues as $headValue) {
                    $get_rows_of_outlet = $getRowIds;

                    foreach ($get_rows_of_outlet as $outletRowId) {

                        $row_id = $outletRowId;
                        $row_data_group = $allTemplateGroupedByRow[$row_id] ?? collect();

                        $row_data = $row_data_group->firstWhere('template_name_head_id', $own_head_id);

                        if ($row_data && !in_array($row_data->value, array_column($distributors_arr, 'value'))) {
//                            echo 'count';
                            $get_master_row = $allTemplateNamesValues
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data->value)
                                ->where('project_template_id', $project_temp_info->id)
                                ->first();

//                                dd($get_master_row);

                            $dist_main_header = optional($allTemplateNamesValues
                                ->where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $own_head_id)
                                ->first())->value;

                            $dist_info = count($distributors_arr);
//                                dd($endIndex, $startIndex, $dist_info);
//                                if ($dist_info >= $startIndex && $dist_info <= $endIndex) {
//                                    continue;
//                                }

                            $get_sub_header_val = $allTemplateNamesValues->where('row_id', $get_master_row->row_id)
                                ->whereIn('template_name_head_id', [$project_temp_info->sub_header, $outlet_master_sub_header_id])
                                ->first();

                            $dist_sub_header = optional($get_sub_header_val)->value;
//                                dd($get_sub_header_val);

                            $get_count_of_outlets = $allTemplateNamesValues
                                ->where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data->value);

//                                dd($get_count_of_outlets);

                            $completed_count = $get_count_of_outlets->filter(function ($outlet) use ($request) {
                                return DB::table('temp_user_activity_answers_data')
                                    ->where('row_id', $outlet->row_id) // ✅ FIXED
                                    ->where('activity_id', $request->activity_id)
                                    ->exists();
                            })->count();

                            $masterTemplateRow = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)->first();
                            $min_value = $masterTemplateRow->min_completion ?? $outlet_master_min_completion;
                            $completion_type_value = $masterTemplateRow->completion_type ?? $outlet_master_completion_type;

                            $questionIds = Question::where('activity_id', $activity->id)->pluck('id')->toArray();
                            $answeredCount = DB::table('temp_user_activity_answers_data')->where('row_id', $get_master_row->row_id)
                                ->where('activity_id', $activity->id)
                                ->whereIn('question_id', $questionIds)
                                ->distinct('question_id')
                                ->count('question_id');

                            $allAnswered = $answeredCount === count($questionIds);
                            $otpVerificationDone = false;

                            if ($allAnswered) {
                                $lastAnswer = DB::table('temp_user_activity_answers_data')->where('row_id', $get_master_row->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->whereNotNull('mobile_otp')
                                    ->orderByDesc('id')
                                    ->first();

                                $otpVerificationDone = $lastAnswer && $lastAnswer->otp_verified_status == 1;
                            }

                            $projectStatus = match (true) {
                                $allAnswered && $otpVerificationDone && $otpRequiredStatus == 1 => "completed",
                                $allAnswered && !$otpVerificationDone && $otpRequiredStatus == 1 => "Awaiting OTP Verify",
                                $allAnswered => "completed",
                                default => "pending",
                            };

                            if ($projectStatus === "completed") {
                                $projectCompletedCount++;
                            }

                            $totalNumberOfOutlets++;

                            $distributors_arr[] = [
                                'value' => $row_data->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $allTemplateGroupedByRow[$get_master_row->row_id] ?? collect(),
                                'activity_id' => $request->activity_id,
                                'activity_name' => $activity->activity_name,
                                'template_id' => $request->template_id,
                                'project_id' => $request->project_id,
                                'project_template_id' => $project_temp_info->id,
                                'total_outlets' => count($get_count_of_outlets),
                                'total_outlet_answered' => $completed_count,
                                'main_header' => $dist_main_header,
                                'sub_header' => $dist_sub_header,
                                'min_completion' => $min_value,
                                'completion_type' => $completion_type_value,
                                'status' => $projectStatus
                            ];
                        }
                    }
                }

            }
        }

//    }

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

        //khushboo 17-05-25
        $add_outlet = false;
        if ($project_temp_info->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-25

        if ($totalNumberOfOutlets > 0 && $projectCompletedCount > 0 && $totalNumberOfOutlets == $projectCompletedCount) {
            $add_outlet = false;
        }

        return response([
            'status' => 200,
            'message' => 'success',
            // 'data'=>$distributors_arr,
            'data' => $paginatedDistributors,
            'group_session_id' => $group_session_id,
            'otp_required_status' => (string)$otpRequiredStatus, // khushboo 07-04-2025
            'show_audit_close_button' => false, // khushboo 17-04-2025
            'is_audit_closed' => false, // khushboo 17-04-2025
            'add_outlet' => $add_outlet
        ]);

    }


    public function auditCloseSubmit(Request $request)
    {

        try {

            $projectData = Project::find($request->project_id);
            $projectTemplateInfo = ProjectTemplate::where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->first();
            $activity = Activity::find($request->activity_id);
            $user = User::find($request->user_id);
            $distributor_value = $request->distributor_value;

            $checkIfDataAssignExist = UserActivityDataAssign::where('user_id', $user->id)
                ->where('activity_id', $activity->id)
                ->where('project_template_id', $projectTemplateInfo->id)
                ->exists();

            if ($checkIfDataAssignExist) {

                $getDataAssignedCommonId = UserActivityDataAssign::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->distinct('common_id')
                    ->pluck('common_id');

                $getDataAssignedIds = UserActivityDataAssign::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->distinct('data_assign_id')
                    ->pluck('data_assign_id');

                $getAuditorRowIds = UserAuditAssigns::whereIn('common_id', $getDataAssignedCommonId)
                    ->distinct('row_id')->pluck('row_id');

                $getAssignedData = DataAssign::whereIn('id', $getDataAssignedIds)->get();
                $getAssignedTemplateHeadIds = [];
                foreach ($getAssignedData as $assignedData) {
                    if ($assignedData->is_outlet_assigned == 1) {
                        $getAssignedTemplateHeadIds[] = $projectTemplateInfo->own_reference_head_id;
                    } else {
                        $getAssignedTemplateHeadIds[] = $assignedData->template_name_head_id;
                    }
                }
                // Convert to array if it's a collection
                $templateHeadIds = is_array($getAssignedTemplateHeadIds) ? $getAssignedTemplateHeadIds : $getAssignedTemplateHeadIds->toArray();
//                dd($templateHeadIds, $distributor_value);
                $fetchRowIdWithValue = DB::table('project_template_name_values_new')
                    ->whereIn('id', $getAuditorRowIds)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->where(function ($query) use ($templateHeadIds, $distributor_value) {
                        foreach ($templateHeadIds as $headId) {
                            $query->orWhereRaw(
                                "JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$headId\"')) = ?",
                                [trim($distributor_value)]
                            );
                        }
                    })
                    ->pluck('id')
                    ->toArray();
//                dd($fetchRowIdWithValue);
                $main_header = TemplateNameHead::find($projectTemplateInfo->main_header);
                $sub_header = TemplateNameHead::find($projectTemplateInfo->sub_header);

                $allAnswers = DB::table('temp_user_activity_answers_data')
                    ->whereIn('row_id', $fetchRowIdWithValue)
                    ->get();

                foreach ($fetchRowIdWithValue as $rowId) {

                    $checkIfAnswerAlreadySubmitted = $allAnswers->where('row_id', $rowId)->isNotEmpty();
//                    dd($checkIfAnswerAlreadySubmitted);

                    if (!$checkIfAnswerAlreadySubmitted) {
                        $checkIfExists = ClosedAudits::where('project_template_id', $projectTemplateInfo->id)
                            ->where('main_header', $main_header->template_head_name)
                            ->where('sub_header', $sub_header->template_head_name)
                            ->where('activity_id', $activity->id)
                            ->where('row_id', $rowId)
                            ->where('user_id', $user->id)
                            ->where('distributor_value', $distributor_value)
                            ->exists();
                        if (!$checkIfExists) {
                            ClosedAudits::insert([
                                'project_template_id' => $projectTemplateInfo->id,
                                'main_header' => $main_header->template_head_name,
                                'sub_header' => $sub_header->template_head_name,
                                'activity_id' => $activity->id,
                                'row_id' => $rowId,
                                'user_id' => $user->id,
                                'distributor_value' => $distributor_value,
                                'audit_closed_status' => 1
                            ]);
                        }
                    }
                }
            }

            return response()->json(['status' => 200, 'message' => 'Audit Closed Successfully']);
        } catch (\Exception $e) {
//            dd($e->getMessage());
            return response()->json(['status' => 201, 'message' => 'Something went wrong']);
        }

    }


    public function getAllQuestion(Request $request)
    {
        $row_id = $request->row_id;
        $group_info = $request->group_info;
        $activity_id = $request->activity_id;

        $related_questions = Question::with(['getOptions:id,option,question_id'])->where('activity_id', $activity_id)->orderBy('question_sequence')->get();

        foreach ($related_questions as $item) {
            if ($item->question_type == 'Subjective') {
                $subjective = SubjectQuestion::where('question_id', $item->id)->select('id', 'question_id', 'subject as option', 'answer_type')->get();
                $item->get_options = $subjective;
                $item->makeHidden(['getOptions']);
            }
        }

        return response([
            'status' => 200,
            'message' => 'success',
            'data' => $related_questions,
            'group_session_id' => $group_info,
        ]);
    }


    public function myProjectsDistributorOutletsData(Request $request, $rowId, $distributor_value, $userId)
    {
//         dd($userId);
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($rowId);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
        $user = User::find($userId);

        $project = Project::find($projectTemplate->project_id);

        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $can_edit_data = false;

        $childTemplatesIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('id')->toArray();

        $childTempOwnRefIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('own_reference_head_id')->toArray();

        //khushboo 17-05-2025
        $add_outlet = false;
        //khushboo 17-05-2025

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        // $projectInfo = Project::findOrFail($project->id);
        // dd($project->is_otp_required, $projectTemplate->activity_otp_required_ids);
        if (($project->is_otp_required == 1) && !empty($projectTemplate->activity_otp_required_ids) && !empty(json_decode($projectTemplate->activity_otp_required_ids))) {
            $otpRequiredStatus = 1;
        }
        //khushboo 07-04-2025

        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;

        $activities = [];
        foreach ($childTemplatesData as $childTemp) {

            // for empty or partial template data
            if ($childTemp->can_edit_data == 1) {
                $can_edit_data = true;
            }

            if ($childTemp->data_add_on == 1 || $childTemp->with_data == 0) {
                $add_outlet = true;
            }

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

        $rows_in_templates = DB::table('project_template_name_values_new')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->pluck('id')
            ->toArray();

        $allTemplates = DB::table('project_template_name_values_new')
            ->select('id as row_id', 'project_template_id', 'template_data_json')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();

        $templateHeads = DB::table('template_name_heads')
            ->get()
            ->keyBy('id');

        $allTemplateNamesValues = [];
        foreach ($allTemplates as $row) {
            $jsonData = json_decode($row->template_data_json, true);
            foreach ($jsonData as $headId => $value) {
                if (isset($templateHeads[$headId])) {
                    $head = $templateHeads[$headId];
                    $allTemplateNamesValues[] = (object)[
                        'row_id' => $row->row_id,
                        'project_template_id' => $row->project_template_id,
                        'template_name_head_id' => $headId,
                        'template_data_json' => $row->template_data_json,
                        'value' => $value,
                        'head_id' => $headId,
                        'head_template_name_id' => $head->template_name_id,
                        'template_head_name' => $head->template_head_name,
                        'head_created_at' => $head->created_at,
                        'head_updated_at' => $head->updated_at,
                        'head_deleted_at' => $head->deleted_at,
                    ];
                }
            }
        }

        $allTemplateNamesValues = collect($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

        $childDataAssignIds = DB::table('data_assigns')->whereIn('project_template_id', $childTemplatesIds)
            ->where('is_outlet_assigned', 1)
            ->pluck('id')->toArray();

        $get_project_template_assigned_data = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('data_assign_id', $childDataAssignIds)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();

        $dataAssignCommonIds = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->whereIn('activity_id', $activities)
            ->distinct('common_id')
            ->pluck('common_id');

        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

        $getRowIds = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIds)
            ->distinct('row_id')
            ->pluck('row_id');

        $is_to_check_previous_submitted = 1;

        // the below will return as the row_id which are answered in the previous sequece
        foreach ($activities as $previous_sequence_activity) {
            $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')
                ->whereIn("row_id", $rows_in_templates)
                ->where('activity_id', $previous_sequence_activity)
                ->distinct('row_id')
                ->pluck('row_id')->toArray();

            if (empty($previous_sequence_answered_rows)) {
                // For the first iteration, just set the array
                $previous_sequence_answered_rows = $get_previous_sequence_answered;
            } else {
                // For subsequent iterations, keep only common values between the arrays
                $previous_sequence_answered_rows = array_intersect($previous_sequence_answered_rows, $get_previous_sequence_answered);
            }
        }

//        $outlet_items = $allTemplateNamesValues
//            ->whereIn('project_template_id', $childTemplatesIds)
//            ->where('template_name_head_id', $childTempOwnRefIds)
//            ->where('value', $distributor_value);

        $checkPrentOutletAssign = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
            ->where('is_outlet_assigned', 1)
            ->exists();

        $getChildTemplateAssignedIds = [];

        if ($checkPrentOutletAssign) {

            $parentDataAssignIds = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

//            dd($parentDataAssignIds, $projectTemplate->id);
            $dataAssignCommonIdsnew = DB::table('user_activity_data_assigns')->where('user_id', $userId)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id');

            $getChildTemplateAssignedIds = DB::table('user_activity_data_assigns')->where('user_id', $userId)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('project_template_id')
                ->pluck('project_template_id')->toArray();

            $getRowIdsnew = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIdsnew)
                ->distinct('row_id')
                ->pluck('row_id');

//            dd($getRowIds);

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIdsnew)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

//            dd($outlet_items, $childTemplatesIds, $getRowIds, $distributor_value, $allTemplateNamesValues);

        }
        else {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });
        }

        // dd($outlet_items->IsEmpty());
        if ($checkPrentOutletAssign && $outlet_items->IsEmpty()) {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

            // dd($outlet_items, $getRowIds);

        }

        //outlet assign data with different head value
        if ($outlet_items->IsEmpty()) {

            $tempjsondata = json_decode($projectTemplateNameValue->template_data_json, true);

            // get only values, ignore keys
            $allValues = array_values($tempjsondata);

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($allValues) {
                    $json = json_decode($item->template_data_json, true);

                    // check if any value from $allValues exists in this row's JSON
                    return count(array_intersect($allValues, $json)) > 0;
                });

        }

        $outlet_items = $outlet_items->values(); // Re-index the collection

        $projectCompletedStatus = 0;
        $auditCompleteCounts = 0;

        $allAnswers = collect(DB::table('temp_user_activity_answers_data')
            ->whereIn('row_id', $getRowIds)
            ->get());

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->row_id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;

                // Attach get_head_name to the data_item
                $outlet_item->get_head_name = $templateHeads[$outlet_item->template_name_head_id] ?? null;

                $headRows = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();
                $outlet_item->get_data_of_rows = $headRows->map(function ($row) use ($templateHeads) {
                    $rowCopy = clone $row; // prevent recursive reference
                    $rowCopy->get_head_name = $templateHeads[$row->template_name_head_id] ?? null;
                    return $rowCopy;
                })->values();

                $check_if_answered = $allAnswers->where('row_id', $outlet_item->row_id)
//                    ->where('activity_id', $activity->id)
                    ->isNotEmpty();

                $row_group = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();

                $templateNameData = DB::table('project_templates')->where('id', $outlet_item->project_template_id)->first();

                //khushboo 02-05-2025
                $getAllQuestionIds = DB::table('questions')
                    ->whereIn('activity_id', $activities)
                    ->pluck('id')
                    ->toArray();

                $getAllRequiredQuestionIds = DB::table('questions')
                    ->whereIn('activity_id', $activities)
                    ->where('answer_type', 1)
                    ->pluck('id')
                    ->toArray();

                $totalQuestions = count($getAllRequiredQuestionIds);
//                 dd($totalQuestions);

                $answeredCount = $allAnswers->where('row_id', $outlet_item->row_id)
                    ->whereIn('activity_id', $activities)
                    ->whereIn('question_id', $getAllRequiredQuestionIds)
                    ->unique('question_id')
                    ->count('question_id');

                // $allAnswered = $answeredCount === $totalQuestions;
                $allAnswered = false;
                // dd($answeredCount, $totalQuestions);
//                dd($totalQuestions, $answeredCount);
                if ($answeredCount === $totalQuestions) {
                    $allAnswered = true;
                }

                // dd($totalQuestions,);
                // $allAnswered = true;
                $otpVerificationDone = false;
                // if ($allAnswered) {
                $lastQuestionAnswered = $allAnswers->where('row_id', $outlet_item->row_id)
//                        ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->whereNotNull('mobile_otp')
                    ->sortByDesc('id')
                    ->first();
//                     dd($lastQuestionAnswered);
                if (!empty($lastQuestionAnswered) && !empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                    $otpVerificationDone = true;
                    $allAnswered = true;
                }
                // }


                $projectStatus = "pending";
                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "completed";
                    // } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                    // $projectStatus = "Awaiting OTP Verify";
                } else if ($allAnswered) {
                    $projectStatus = "completed";
                }
                //khushboo 02-05-2025

                if ($projectStatus == "completed") {
                    $projectCompletedStatus++;
                }


                $main_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->main_header))->value;
                $sub_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->sub_header))->value;

                $checkIfExists = ClosedAudits::whereIn('project_template_id', $childTemplatesIds)
                    ->where('main_header', $main_header)
                    ->where('sub_header', $sub_header)
                    ->whereIn('activity_id', $activities)
                    ->where('row_id', $outlet_item->row_id)
                    ->where('user_id', $user->id)
                    ->where('distributor_value', $distributor_value)
                    ->exists();

                if ($checkIfExists) {
                    $projectStatus = "completed";
                    $auditCompleteCounts++;
                }

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'project_id' => $project->id,
                    'template_id' => $templateNameData->template_name_id,
                    'status' => $projectStatus,
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                    'add_outlet' => $templateNameData->data_add_on = 1 ? true : false,
                ];
            }
        }

        $projectTemplateNames = [];
        if (!empty($getChildTemplateAssignedIds)) {
            foreach ($childTemplatesData as $childData) {
                if (($childData->with_data == 0 || $childData->data_add_on == 1) && in_array($childData->id, $getChildTemplateAssignedIds)) {
                    $projectTemplateNames[] = [
                        'template_id' => $childData->getTemplate->id,
                        'project_id' => $childData->project_id,
                        'template_name' => $childData->getTemplate->template_name
                    ];
                }
            }
        }

        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }

        $masterPorjectTemplate = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 1)->first();

        $minimumCount = $masterPorjectTemplate->min_completion;
        // dd($projectTemplate);
        $showCLoseAuditOption = false;
        $IsAuditClosed = false;

//         dd($projectCompletedStatus, (int) $minimumCount);
        if ($minimumCount && $projectCompletedStatus > 0 && $projectCompletedStatus >= (int)$minimumCount) {
            $showCLoseAuditOption = true;
        }

        if ($auditCompleteCounts > 0) {
            $showCLoseAuditOption = false;
        }

        $project_data_arr = collect($project_data_arr);

        // Now you can use the where method
        if ($request->status) {
            $project_data_arr = $project_data_arr->where('status', $request->status);
        }
        if ($request->keyword) {
            $project_data_arr = $project_data_arr->filter(function ($item) use ($request) {
                return strpos(strtolower($item['main_header']), strtolower($request->keyword)) !== false ||
                    strpos(strtolower($item['sub_header']), strtolower($request->keyword)) !== false;
            });
        }

        $distributors_collection = collect($project_data_arr);
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

        $childTemplatesNameIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('template_name_id')->toArray();

        return response([
            'status' => 200,
            'message' => 'sucesss',
            'project_id' => $project->id,
            'template_id' => $childTemplatesNameIds[0],
            'distributor_value' => $distributor_value,
            'otp_required_status' => $otpRequiredStatus,
            'add_outlet' => $add_outlet,
            'show_audit_close_button' => $showCLoseAuditOption,
            'data' => $paginatedDistributors,
            'can_edit_data' => $can_edit_data,
            'template_names' => $projectTemplateNames

        ], 200);
    }


    public function myProjectsDistributorOutletsDataOldSept10(Request $request, $rowId, $distributor_value, $userId)
    {
//         dd($userId);
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($rowId);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
        $user = User::find($userId);

        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;

        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $childTemplatesIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('id')->toArray();

        $childTempOwnRefIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('own_reference_head_id')->toArray();

        //khushboo 17-05-2025
        $add_outlet = false;
        if ($projectTemplate->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-2025

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        // $projectInfo = Project::findOrFail($project->id);
        // dd($project->is_otp_required, $projectTemplate->activity_otp_required_ids);
        if (($project->is_otp_required == 1) && !empty($projectTemplate->activity_otp_required_ids) && !empty(json_decode($projectTemplate->activity_otp_required_ids))) {
            $otpRequiredStatus = 1;
        }

        //khushboo 07-04-2025

        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;

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

        $rows_in_templates = DB::table('project_template_name_values_new')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->pluck('id')
            ->toArray();

        $childMainHeaderId = $childTemp->main_header;
//            $parentTempJsonData = json_decode($projectTemplateNameValue->template_data_json);

        $allTemplates = DB::table('project_template_name_values_new')
            ->select('id as row_id', 'project_template_id', 'template_data_json')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();


        $templateHeads = DB::table('template_name_heads')
            ->get()
            ->keyBy('id');

        $allTemplateNamesValues = [];
        foreach ($allTemplates as $row) {
            $jsonData = json_decode($row->template_data_json, true);
            foreach ($jsonData as $headId => $value) {
                if (isset($templateHeads[$headId])) {
                    $head = $templateHeads[$headId];
                    $allTemplateNamesValues[] = (object)[
                        'row_id' => $row->row_id,
                        'project_template_id' => $row->project_template_id,
                        'template_name_head_id' => $headId,
                        'template_data_json' => $row->template_data_json,
                        'value' => $value,
                        'head_id' => $headId,
                        'head_template_name_id' => $head->template_name_id,
                        'template_head_name' => $head->template_head_name,
                        'head_created_at' => $head->created_at,
                        'head_updated_at' => $head->updated_at,
                        'head_deleted_at' => $head->deleted_at,
                    ];
                }
            }
        }

        $allTemplateNamesValues = collect($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

        $childDataAssignIds = DB::table('data_assigns')->whereIn('project_template_id', $childTemplatesIds)
            ->where('is_outlet_assigned', 1)
            ->pluck('id')->toArray();

        $get_project_template_assigned_data = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('data_assign_id', $childDataAssignIds)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();

        $dataAssignCommonIds = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->whereIn('activity_id', $activities)
            ->distinct('common_id')
            ->pluck('common_id');

        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

        $getRowIds = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIds)
            ->distinct('row_id')
            ->pluck('row_id');

        $is_to_check_previous_submitted = 1;

        // the below will return as the row_id which are answered in the previous sequece
        foreach ($activities as $previous_sequence_activity) {
            $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')
                ->whereIn("row_id", $rows_in_templates)
                ->where('activity_id', $previous_sequence_activity)
                ->distinct('row_id')
                ->pluck('row_id')->toArray();

            if (empty($previous_sequence_answered_rows)) {
                // For the first iteration, just set the array
                $previous_sequence_answered_rows = $get_previous_sequence_answered;
            } else {
                // For subsequent iterations, keep only common values between the arrays
                $previous_sequence_answered_rows = array_intersect($previous_sequence_answered_rows, $get_previous_sequence_answered);
            }
        }

//        $outlet_items = $allTemplateNamesValues
//            ->whereIn('project_template_id', $childTemplatesIds)
//            ->where('template_name_head_id', $childTempOwnRefIds)
//            ->where('value', $distributor_value);

        $checkPrentOutletAssign = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
            ->where('is_outlet_assigned', 1)
            ->exists();

        if ($checkPrentOutletAssign) {

            $parentDataAssignIds = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

//            dd($parentDataAssignIds, $projectTemplate->id);
            $dataAssignCommonIdsnew = DB::table('user_activity_data_assigns')->where('user_id', $userId)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id');
//

            $getRowIdsnew = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIdsnew)
                ->distinct('row_id')
                ->pluck('row_id');

//            dd($getRowIds);

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIdsnew)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

//            dd($outlet_items, $childTemplatesIds, $getRowIds, $distributor_value, $allTemplateNamesValues);

        } else {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });
        }

//        dd($outlet_items->IsEmpty());
        if ($checkPrentOutletAssign && $outlet_items->IsEmpty()) {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

//            dd($outlet_items, $getRowIds);
        }

        //for master main header mapped
        if ($outlet_items->IsEmpty()) {

//            $getDistributorOutletValue = DB::table('project_template_name_values_new')
//                ->whereIn('row_id', $getRowIds)
//                ->where('project_template_id', $projectTemplate->id)
//                ->select(
//                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplateData->main_header}\"')) as head_value")
//                )
//                ->get();

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

        }

        //outlet assign data with different head value
        if ($outlet_items->IsEmpty()) {


            $tempjsondata = json_decode($projectTemplateNameValue->template_data_json, true);

            // get only values, ignore keys
            $allValues = array_values($tempjsondata);


            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($allValues) {
                    $json = json_decode($item->template_data_json, true);

                    // check if any value from $allValues exists in this row's JSON
                    return count(array_intersect($allValues, $json)) > 0;
                });

        }

//        dd($outlet_items, $previous_sequence_answered_rows);

//        if ($is_to_check_previous_submitted && !empty($previous_sequence_answered_rows)) {
//            $outlet_items = $outlet_items->whereIn('row_id', $previous_sequence_answered_rows);
//        }

        $outlet_items = $outlet_items->values(); // Re-index the collection

//        dd($outlet_items);

        $projectCompletedStatus = 0;
        $auditCompleteCounts = 0;

        $allAnswers = collect(DB::table('temp_user_activity_answers_data')
            ->whereIn('row_id', $getRowIds)
            ->get());

//        dd($outlet_items);

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->row_id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;

                // Attach get_head_name to the data_item
                $outlet_item->get_head_name = $templateHeads[$outlet_item->template_name_head_id] ?? null;

                $headRows = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();
                $outlet_item->get_data_of_rows = $headRows->map(function ($row) use ($templateHeads) {
                    $rowCopy = clone $row; // prevent recursive reference
                    $rowCopy->get_head_name = $templateHeads[$row->template_name_head_id] ?? null;
                    return $rowCopy;
                })->values();

                $check_if_answered = $allAnswers->where('row_id', $outlet_item->row_id)
//                    ->where('activity_id', $activity->id)
                    ->isNotEmpty();

                $row_group = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();

                //khushboo 02-05-2025
                $getAllQuestionIds = DB::table('questions')
//                    ->where('activity_id', $activity->id)
                    ->pluck('id')
                    ->toArray();

                $totalQuestions = count($getAllQuestionIds);
//                 dd($totalQuestions);

                $answeredCount = $allAnswers->where('row_id', $outlet_item->row_id)
//                    ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->unique('question_id')
                    ->count('question_id');

                // $allAnswered = $answeredCount === $totalQuestions;
                $allAnswered = false;
//                dd($totalQuestions, $answeredCount);
                if ($answeredCount === $totalQuestions) {
                    $allAnswered = true;
                }

                // dd($answeredCount, $totalQuestions);
                // $allAnswered = true;
                $otpVerificationDone = false;
//                if ($allAnswered) {
                $lastQuestionAnswered = $allAnswers->where('row_id', $outlet_item->row_id)
//                        ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->whereNotNull('mobile_otp')
                    ->sortByDesc('id')
                    ->first();
//                     dd($lastQuestionAnswered);
                if (!empty($lastQuestionAnswered) && !empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                    $otpVerificationDone = true;
                    $allAnswered = true;
                }
//                }

//                 dd($otpVerificationDone);
                $projectStatus = "pending";
                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "completed";
//                } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
//                    $projectStatus = "Awaiting OTP Verify";
                } else if ($allAnswered) {
                    $projectStatus = "completed";
                }
                //khushboo 02-05-2025

                if ($projectStatus == "completed") {
                    $projectCompletedStatus++;
                }

                $templateNameData = DB::table('project_templates')->where('id', $outlet_item->project_template_id)->first();

                $main_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->main_header))->value;
                $sub_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->sub_header))->value;

                $checkIfExists = ClosedAudits::whereIn('project_template_id', $childTemplatesIds)
                    ->where('main_header', $main_header)
                    ->where('sub_header', $sub_header)
                    ->whereIn('activity_id', $activities)
                    ->where('row_id', $outlet_item->row_id)
//                    ->where('user_id', $user->id)
                    ->where('distributor_value', $distributor_value)
                    ->exists();

                if ($checkIfExists) {
                    $projectStatus = "completed";
                    $auditCompleteCounts++;
                }

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'project_id' => $project->id,
                    'template_id' => $templateNameData->template_name_id,
                    'status' => $projectStatus,
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                    'add_outlet' => $templateNameData->data_add_on = 1 ? true : false,
                ];
            }
        }

//        }

//        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'distributor_value' => $distributor_value]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }

        $masterPorjectTemplate = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 1)->first();

        $minimumCount = $masterPorjectTemplate->min_completion;
        // dd($projectTemplate);
        $showCLoseAuditOption = false;
        $IsAuditClosed = false;

//         dd($projectCompletedStatus, (int) $minimumCount);
        if ($minimumCount && $projectCompletedStatus > 0 && $projectCompletedStatus >= (int)$minimumCount) {
            $showCLoseAuditOption = true;
        }

        if ($auditCompleteCounts > 0) {
            $showCLoseAuditOption = false;
        }

        $project_data_arr = collect($project_data_arr);

        // Now you can use the where method
        if ($request->status) {
            $project_data_arr = $project_data_arr->where('status', $request->status);
        }
        if ($request->keyword) {
            $project_data_arr = $project_data_arr->filter(function ($item) use ($request) {
                return strpos(strtolower($item['main_header']), strtolower($request->keyword)) !== false ||
                    strpos(strtolower($item['sub_header']), strtolower($request->keyword)) !== false;
            });
        }

        $distributors_collection = collect($project_data_arr);
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

        $childTemplatesNameIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('template_name_id')->toArray();

        return response([
            'status' => 200,
            'message' => 'sucesss',
            'project_id' => $project->id,
            'template_id' => $childTemplatesNameIds[0],
            'distributor_value' => $distributor_value,
            'otp_required_status' => $otpRequiredStatus,
            'add_outlet' => $add_outlet,
            'show_audit_close_button' => $showCLoseAuditOption,
            'data' => $paginatedDistributors,

        ], 200);
    }


    public function checkAndCreateDirectory($directory)
    {
        $publicPath = public_path($directory);
        // Check if the directory doesn't exist
        if (!File::exists($publicPath)) {
            // Create the directory
            File::makeDirectory($publicPath, 0755, true, true);
        }
    }


    public function QuestionAnswer(Request $request)
    {

        $validatedData = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,docx|max:2048',
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // Store the file in the 'public' folder and get the file path
            $path = $file->store('uploads', 'public'); // This saves in storage/app/public/uploads

            // If you need the full URL, use asset() function to get the public URL
            $url = asset('storage/' . $path); // URL to access the file from the public folder


            return response()->json([
                'message' => 'File uploaded successfully',
                'url' => $url
            ]);
        }
    }

    public function row_activity_answers(Request $request)
    {
        try {

//        dd($request->all());
            $userId = $request->user_id;
            $last_sequence = TempUserActivityAnswersData::max('same_answer_id') + 1;

            $baseDirectory = 'activityAnswerImages/';
            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $projectDirectory = $baseDirectory . $projectName . '/';
            $this->checkAndCreateDirectory($projectDirectory);

            $currentMonthYear = Carbon::now()->format('FY');
            $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            $latitude = $request->latitude ?? null;
            $longitude = $request->longitude ?? null;

            $userDetails = User::find($userId);

            // Remove non-question fields
            $excludedKeys = ['user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude'];
            $questionAnswers = collect($request->all())->except($excludedKeys);

            $submittedQuestionIds = array_map('intval', array_keys($questionAnswers->toArray()));
            // dd($submittedQuestionIds);
            // Get only **required** questions (answer_type == 1) for the given activity
            $requiredQuestions = Question::where('activity_id', $request->activity_id)
                ->where('answer_type', 1)
                ->get();
            // Group required questions by type
            $requiredGroupedByType = $requiredQuestions->groupBy('question_type');
            // Track missing question types
            $missingTypes = [];

            foreach ($requiredGroupedByType as $type => $questions) {

                $questionIds = $questions->pluck('id')->toArray();
                $intersection = array_intersect($submittedQuestionIds, $questionIds);

                if (empty($intersection)) {
                    // None of the required questions of this type were submitted
                    $missingTypes[] = $type;
                }
            }
//        dd($missingTypes);
            if (!empty($missingTypes)) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Some required question types are missing from the submission.',
                    'missing_question_types' => $missingTypes
                ], 422);
            }
            $imagesToProcess = [];

//        dd($questionAnswers);

            foreach ($questionAnswers as $key => $value) {
//            if (!is_numeric($key)) {
//                continue; // Ignore invalid question IDs
//            }

                $questionTypeArray = ['Dropdown', 'Image', 'Audio', 'Date', 'Date & Time', 'Dropdown', 'File Upload', 'Free Text', 'Image', 'Location', 'Multi select', 'Video'];

                $questionId = $key; // or "123"

                preg_match('/^(\d+)([a-zA-Z]*)$/', $questionId, $matches);

                $numberPart = $matches[1] ?? null; // "123"
                $stringPart = $matches[2] ?? null; // "Image"

//            echo $stringPart;

                if ($stringPart === '') {
                    $questionId = (int)$key;
                } else {
                    $questionId = (int)$numberPart;
                }

                $question = Question::find($questionId);

                if (empty($question)) {
                    continue; // Skip if question doesn't exist
                }

                $user_answer = null;
                $file_path = null;

                // Check if answer is a file
                if ($request->hasFile($key)) {
                    $file = $request->file($key);

                    $rules = [];
                    $customMessages = [];

                    if ($question->question_type == 'Image') {
                        $rules['file_input'] = 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                        $customMessages['file_input.required'] = "Image is required";
                        $customMessages['file_input.image'] = "Only image files (jpeg, png, etc.) are allowed.";
                        $customMessages['file_input.mimes'] = "Invalid image format ";
                    } elseif ($question->question_type == 'File Upload') {
                        $rules['file_input'] = 'required|file|mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/jpg,image/png,image/webp|max:51200';
                        $customMessages['file_input.required'] = "Invalid File Type";
                        $customMessages['file_input.mimetypes'] = "Invalid File Type";
                    }

                    $validator = Validator::make(['file_input' => $file], $rules, $customMessages);

                    if ($validator->fails()) {
                        return response()->json([
                            'status' => 422,
                            'message' => "Invalid file for question ID: $questionId",
                            'question_type' => $question->question_type,
                            'errors' => $validator->errors()->all(),
                        ], 422);
                    }


                    // Store file
                    $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                    $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                    $file->move(public_path($projectMonthYearDirectory), $file_name);
                    chmod(public_path($full_file_path), 0777);

                    $user_answer = $full_file_path;
                    $file_path = $full_file_path;

                } else {

//                if (empty($value)) {
//                    return response()->json([
//                        'status' => 422,
//                        'message' => "Please Enter a Valid Answer",
//                    ], 422);
//                }

                    // Treat as text answer
                    $user_answer = $value;

                    // Handle date formatting
                    if ($question->question_type === 'Date' && !empty($user_answer)) {
                        try {
                            $user_answer = \Carbon\Carbon::parse($user_answer)->format('d/m/Y');
                        } catch (\Exception $e) {
                            // Optionally log or skip invalid date
                            $user_answer = null;
                        }
                    }

                    if ($question->question_type === 'Free Text' && !empty($user_answer)) {
                        $user_answer = $value;
                    }

                }

//            dd($checkIfExists);
                if ($question->question_type == 'Subjective') {
//                dd('dfhfgh');
//                echo 'sub';
                    $checkCountOfExistingData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where(function ($query) use ($request) {
                            $query->where('activity_id', $request->activity_id)
                                ->orWhere('activity_group_name_id', $request->group_id);
                        })
                        ->where('question_id', $questionId)
//                    ->where('user_answer', 'LIKE', $user_answer)
//                        ->where('user_id', $userId)
                        ->count();
//                dd($checkCountOfExistingData);

                    if ($checkCountOfExistingData == 1) {

                        $checkSubjectiveExistingData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where(function ($query) use ($request) {
                                $query->where('activity_id', $request->activity_id)
                                    ->orWhere('activity_group_name_id', $request->group_id);
                            })
                            ->where('question_id', $questionId)
//                        ->where('user_answer', 'LIKE', $user_answer)
//                            ->where('user_id', $userId)
                            ->first();

                        $subjectiveAnswer = $checkSubjectiveExistingData->user_answer;

                        $cleanedAnswer = preg_replace('/\s+/', ' ', trim($subjectiveAnswer)); // Clean extra spaces

                        // Count how many () groups are in the answer
                        preg_match_all('/\(([^)]+)\)/', $cleanedAnswer, $matches);

                        $bracketCount = count($matches[0]);

                        if ($bracketCount === 1) {

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

                            $isUrlOrPath = str_contains($user_answer, '/');
                            if ($isUrlOrPath) {
                                $userAnswerExtension = strtolower(pathinfo($user_answer, PATHINFO_EXTENSION));

                                if ($request->hasFile($key) && $stringPart == 'Image' && in_array($userAnswerExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    $file = $request->file($key);
                                    $fileExtension = $file->getClientOriginalExtension();
                                    $extension = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                                    if (in_array($fileExtension, $extension)) {

                                        $imagesToProcess[] = [
//                                            'file_path' => $file_path,
                                            'file_path' => public_path($file_path), // Make absolute path,
                                            'latitude' => $latitude,
                                            'longitude' => $longitude,
                                            'answer' => $answer,
                                            'directory' => $projectMonthYearDirectory,
                                            'user_id' => $userDetails->id,
                                        ];
                                    }

                                }

                            }


                        }

                    }

                    if ($checkCountOfExistingData == 0) {

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

                } else {

                    $checkIfExists = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where(function ($query) use ($request) {
                            $query->where('activity_id', $request->activity_id)
                                ->orWhere('activity_group_name_id', $request->group_id);
                        })
                        ->where('question_id', $questionId)
//                        ->where('user_id', $userId)
                        ->exists();

                    if (!$checkIfExists) {

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

                        $answer = TempUserActivityAnswersData::where('row_id', $request->row_id)
                            ->where(function ($query) use ($request) {
                                $query->where('activity_id', $request->activity_id)
                                    ->orWhere('activity_group_name_id', $request->group_id);
                            })
                            ->where('question_id', $questionId)
//                            ->where('user_id', $userId)
                            ->update([
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                            ]);

                    }
                }

                if ($question->question_type === 'Image' && $file_path) {
                    $imagesToProcess[] = [
//                        'file_path' => $file_path,
                        'file_path' => public_path($file_path), // Make absolute path,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'answer' => $answer,
                        'directory' => $projectMonthYearDirectory,
                        'user_id' => $userDetails->id,
                    ];
                }

            }

            // Dispatch image processing job if needed
//        dd($imagesToProcess);
            if (!empty($imagesToProcess)) {

                dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
//                ConvertAuditorSelfiesToGeoSelfies::dispatch($file_path, $latitude, $longitude, $answer, $projectMonthYearDirectory, $userDetails);
//            dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
//                $job = new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess);
//                $job->handle();

            }

            return response([
                'status' => 200,
                'message' => 'Activity Answers submitted successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }

    }


    public function row_activity_answers_old(Request $request)
    {
//        dd($request->all());
        $userId = $request->user_id;
//        $last_sequence = TempUserActivityAnswersData::max('same_answer_id') + 1;
        $last_sequence = (DB::table('temp_user_activity_answers_data')->max('same_answer_id') ?? 0) + 1;

        $baseDirectory = 'activityAnswerImages/';
        $projectTemp = ProjectTemplateNameValue::where('row_id', $request->row_id)->first();
        $projectName = $projectTemp->getProjectTemplate->getProject->project_name;
        $projectDirectory = $baseDirectory . $projectName . '/';
        $this->checkAndCreateDirectory($projectDirectory);

        $currentMonthYear = Carbon::now()->format('FY');
        $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
        $this->checkAndCreateDirectory($projectMonthYearDirectory);

        $latitude = $request->latitude ?? null;
        $longitude = $request->longitude ?? null;

        $userDetails = User::find($userId);

        // Remove non-question fields
        $excludedKeys = ['user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude'];
        $questionAnswers = collect($request->all())->except($excludedKeys);

        $submittedQuestionIds = array_map('intval', array_keys($questionAnswers->toArray()));

//        dd($submittedQuestionIds);
// Get only **required** questions (answer_type == 1) for the given activity
        $requiredQuestions = Question::where('activity_id', $request->activity_id)
            ->where('answer_type', 1)
            ->get();

// Group required questions by type
        $requiredGroupedByType = $requiredQuestions->groupBy('question_type');

// Track missing question types
        $missingTypes = [];

        foreach ($requiredGroupedByType as $type => $questions) {

            $questionIds = $questions->pluck('id')->toArray();
            $intersection = array_intersect($submittedQuestionIds, $questionIds);

            if (empty($intersection)) {
                // None of the required questions of this type were submitted
                $missingTypes[] = $type;
            }
        }


        $imagesToProcess = [];

//        dd($questionAnswers);

        foreach ($questionAnswers as $key => $value) {
//            if (!is_numeric($key)) {
//                continue; // Ignore invalid question IDs
//            }

            $questionTypeArray = ['Dropdown', 'Image', 'Audio', 'Date', 'Date & Time', 'Dropdown', 'File Upload', 'Free Text', 'Image', 'Location', 'Multi select', 'Video'];

            $questionId = $key; // or "123"

            preg_match('/^(\d+)([a-zA-Z]*)$/', $questionId, $matches);

            $numberPart = $matches[1] ?? null; // "123"
            $stringPart = $matches[2] ?? null; // "Image"

//            echo $stringPart;

            if ($stringPart === '') {
                $questionId = (int)$key;
            } else {
                $questionId = (int)$numberPart;
            }

            $question = Question::find($questionId);

            if (empty($question)) {
                continue; // Skip if question doesn't exist
            }

            $user_answer = null;
            $file_path = null;

            // Check if answer is a file
            if ($request->hasFile($key)) {
                $file = $request->file($key);

                $rules = [];
                $customMessages = [];

                if ($question->question_type == 'Image') {
                    $rules['file_input'] = 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                    $customMessages['file_input.required'] = "Image is required";
                    $customMessages['file_input.image'] = "Only image files (jpeg, png, etc.) are allowed.";
                    $customMessages['file_input.mimes'] = "Invalid image format ";
                } elseif ($question->question_type == 'File Upload') {
                    $rules['file_input'] = 'required|file|mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/jpg,image/png,image/webp|max:51200';
                    $customMessages['file_input.required'] = "Invalid File Type";
                    $customMessages['file_input.mimetypes'] = "Invalid File Type";
                }

                $validator = Validator::make(['file_input' => $file], $rules, $customMessages);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 422,
                        'message' => "Invalid file for question ID: $questionId",
                        'question_type' => $question->question_type,
                        'errors' => $validator->errors()->all(),
                    ], 422);
                }


                // Store file
                $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                $file->move(public_path($projectMonthYearDirectory), $file_name);
                chmod(public_path($full_file_path), 0777);

                $user_answer = $full_file_path;
                $file_path = $full_file_path;

            } else {

//                if (empty($value)) {
//                    return response()->json([
//                        'status' => 422,
//                        'message' => "Please Enter a Valid Answer",
//                    ], 422);
//                }

                // Treat as text answer
                $user_answer = $value;


                // Handle date formatting
                if ($question->question_type === 'Date' && !empty($user_answer)) {
                    try {
                        $user_answer = \Carbon\Carbon::parse($user_answer)->format('d/m/Y');
                    } catch (\Exception $e) {
                        // Optionally log or skip invalid date
                        $user_answer = null;
                    }
                }

                if ($question->question_type === 'Free Text' && !empty($user_answer)) {
                    $user_answer = $value;
                }

            }

//            dd($checkIfExists);
            if ($question->question_type == 'Subjective') {
//                dd('dfhfgh');
//                echo 'sub';
                $checkCountOfExistingData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where(function ($query) use ($request) {
                        $query->where('activity_id', $request->activity_id)
                            ->orWhere('activity_group_name_id', $request->group_id);
                    })
                    ->where('question_id', $questionId)
//                    ->where('user_answer', 'LIKE', $user_answer)
                    ->where('user_id', $userId)
                    ->count();
//                dd($checkCountOfExistingData);
//                echo $checkCountOfExistingData;
                if ($checkCountOfExistingData == 1) {

                    $checkSubjectiveExistingData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where(function ($query) use ($request) {
                            $query->where('activity_id', $request->activity_id)
                                ->orWhere('activity_group_name_id', $request->group_id);
                        })
                        ->where('question_id', $questionId)
//                        ->where('user_answer', 'LIKE', $user_answer)
                        ->where('user_id', $userId)
                        ->first();

                    $subjectiveAnswer = $checkSubjectiveExistingData->user_answer;

                    $cleanedAnswer = preg_replace('/\s+/', ' ', trim($subjectiveAnswer)); // Clean extra spaces

                    // Count how many () groups are in the answer
                    preg_match_all('/\(([^)]+)\)/', $cleanedAnswer, $matches);

                    $bracketCount = count($matches[0]);

//                    if ($bracketCount === 1) {

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

                    $isUrlOrPath = str_contains($user_answer, '/');
                    if ($isUrlOrPath) {
                        $userAnswerExtension = strtolower(pathinfo($user_answer, PATHINFO_EXTENSION));

                        if ($request->hasFile($key) && $stringPart == 'Image' && in_array($userAnswerExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $file = $request->file($key);
                            $fileExtension = $file->getClientOriginalExtension();
                            $extension = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                            if (in_array($fileExtension, $extension)) {

                                $imagesToProcess[] = [
                                    'file_path' => $file_path,
                                    'latitude' => $latitude,
                                    'longitude' => $longitude,
                                    'answer' => $answer,
                                    'directory' => $projectMonthYearDirectory,
                                    'user_id' => $userDetails->id,
                                ];
                            }

                        }

                    }


//                    }

                }

                if ($checkCountOfExistingData == 0) {

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

            } else {

                $checkIfExists = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where(function ($query) use ($request) {
                        $query->where('activity_id', $request->activity_id)
                            ->orWhere('activity_group_name_id', $request->group_id);
                    })
                    ->where('question_id', $questionId)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$checkIfExists) {

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

                    $answer = TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where(function ($query) use ($request) {
                            $query->where('activity_id', $request->activity_id)
                                ->orWhere('activity_group_name_id', $request->group_id);
                        })
                        ->where('question_id', $questionId)
                        ->where('user_id', $userId)
                        ->update([
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);

                }
            }

            if ($question->question_type === 'Image' && $file_path) {
                $imagesToProcess[] = [
                    'file_path' => $file_path,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'answer' => $answer,
                    'directory' => $projectMonthYearDirectory,
                    'user_id' => $userDetails->id,
                ];
            }

        }

        // Dispatch image processing job if needed
//        dd($imagesToProcess);
        if (!empty($imagesToProcess)) {
//                ConvertAuditorSelfiesToGeoSelfies::dispatch($file_path, $latitude, $longitude, $answer, $projectMonthYearDirectory, $userDetails);
//            dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
            $job = new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess);
            $job->handle();

        }

        return response([
            'status' => 200,
            'message' => 'Activity Answers submitted successfully.',
        ], 200);

    }


    public function getSubjectiveDropdown(Request $request)
    {

        try {

//            $data = SubjectDropdown::with('subjectiveQuestionDropdowns')->where('subject_id', $request->id)->get();
//            $data = SubjectDropdown::with('SubjectiveQuestion')->where('subject_id', $request->id)->get();
            $data = SubjectDropdown::with('subjectiveQuestion')
                ->where('subject_id', $request->id)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'subject_id' => $item->subject_id,
                        'option' => $item->option,
                        'answer_type' => $item->subjectiveQuestion->answer_type ?? null,
                        'deleted_at' => $item->deleted_at,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,

                    ];
                });

            return response([
                'status' => 200,
                'message' => 'sucesss',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'status' => 401,
                'message' => $e->getMessage(),
            ], 401);
        }
    }


    // khushboo 05-04-2025
    public function sendActivityOtp(Request $request)
    {

        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'user_id' => 'required|numeric',
                'row_id' => 'required|numeric'
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = User::find($request->user_id);
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
                    'status' => 409,
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
                    'status' => 200,
                    'message' => 'OTP Send Successfully',
                ], 200);

            } else {

                return response([
                    'status' => 401,
                    'message' => "Failed To Send OTP",
                ], 401);
            }
        } catch (\Exception $e) {

            return response([
                'status' => 401,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    // khushboo 05-04-2025

    public function verifyOtp(Request $request)
    {
        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'user_id' => 'required|numeric',
                'row_id' => 'required|numeric',
                'mobile_otp' => 'required|numeric',
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = User::find($request->user_id);
            if (empty($userdata)) {
                return response([
                    'status' => 401,
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

            // dd($lastQuestionAnswered);
            // if(!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)){
            //     return response([
            //         'status' => 409,
            //         'message' => 'OTP has already been generated.',
            //     ], 409);
            // }

            if ($lastQuestionAnswered) {
                TempUserActivityAnswersData::where('id', $lastQuestionAnsweredData->id)->update([
                    'otp_verified_status' => 1
                ]);

                return response([
                    'status' => 200,
                    'message' => 'OTP Verified Successfully',
                ], 200);

            } else {

                return response([
                    'status' => 401,
                    'message' => 'OTP Not Verified',
                ], 401);

            }
        } catch (\Exception $e) {
            return response([
                'status' => 401,
                'message' => 'OTP Not Verified',
            ], 401);
        }
    }


    //khushboo 16-05-2025
    public function getTemplateHeaders($projectId, $templateId)
    {
        $project = Project::find($projectId);
        $template = TemplateName::find($templateId);

        $project_temp_info = ProjectTemplate::where('project_id', $project->id)
            ->where('template_name_id', $template->id)
            ->first();
        // dd($project_temp_info);

        $headeNames = [];
        // if ($project_temp_info->is_master == 1) {
        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        // dd($projectTemplateHeaders);
        foreach ($projectTemplateHeaders as $projectTemplateHeaderInfo) {
            $headeNames[] = [
                'id' => $projectTemplateHeaderInfo->id,
                'name' => $projectTemplateHeaderInfo->template_head_name
            ];
        }

        // dd($headeNames);
        return response()->json([
            'status' => 200,
            'project_id' => $project->id,
            'template_id' => $template->id,
            'project_template_id' => $project_temp_info->id,
            'data' => $headeNames
        ]);
        // }
    }


    public function storeTemplateHeaderValues(Request $request)
    {

        $request->validate(
            [
                'activity_id' => 'nullable|numeric',
                'activity_group_id' => 'nullable|numeric',
                'project_template_id' => 'required|numeric',
                'user_id' => 'required|numeric'
            ]
        );

        // dd(auth()->id());
        try {
            $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
            if (!empty($projectTemplateInfo)) {
                $projectTemplateInfoDetails = $projectTemplateInfo->getTemplate;
                $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;

                $projectRowData = $request->except(['project_template_id', 'activity_group_id', 'activity_id', 'user_id']);
                // dd($projectTemplateHeaders);
                ksort($projectRowData);

                /**
                 * ðŸ”¹ Normalize request keys
                 * Map both original and normalized
                 */
                $normalizedRowData = [];
                foreach ($projectRowData as $key => $val) {
                    $normalizedKey = strtolower(str_replace(' ', '_', trim($key)));
                    $normalizedRowData[$normalizedKey] = $val;
                }

                $rules = [];
                foreach ($normalizedRowData as $key => $value) {
                    $rules[$key] = 'required';
                }

                // Validate
                $validator = Validator::make($normalizedRowData, $rules);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Validation error',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $is_template_master = $projectTemplateInfo->is_master;
                $get_header_id = "";
                $get_header_value = "";
                $templateDataJson = [];

                //for linking outlet with distributor
                $matchedMasterId = null;
                $masterTemplateValuesData = null;
                if ($projectTemplateInfo->is_master == 0) {
                    $masterHeadId = $projectTemplateInfo->master_head_id;
                    $masterData = ProjectTemplate::where('project_id', $projectTemplateInfo->project_id)->where('is_master', 1)->first();
                    $masterTemplateValuesData = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $masterData->id)
                        ->select('id', 'template_data_json')
                        ->get()
                        ->map(function ($row) use ($masterHeadId) {
                            $json = json_decode($row->template_data_json, true);
                            return [
                                'id' => $row->id,  // keep the row id
                                'value' => $json[$masterHeadId] ?? null // keep only matched key value
                            ];
                        });
                }
                //for linking outlet with distributor

                foreach ($projectTemplateHeaders as $headId => $projectData) {
                    $headerName = $projectData->template_head_name;
                    // ðŸ”¹ Normalize header name (same as request key normalization)
                    $headerKey = strtolower(str_replace(' ', '_', trim($headerName)));
                    $value = $normalizedRowData[$headerKey] ?? null;
                    // Store first non-empty header as reference
                    if (empty($get_header_id) && !empty($value)) {
                        $get_header_id = $projectData->id;
                        $get_header_value = $value;
                    }
                    $templateDataJson[$projectData->id] = $value;
                    //link outlet with distributor
                    if ($projectTemplateInfo->is_master == 0) {
                        $found = collect($masterTemplateValuesData)->firstWhere('value', $projectData);
                        if ($found) {
                            $matchedMasterId = $found['id'];
                        }
                    }
                }

                // Insert single row with JSON data
                $projectTemplateNameValues = ProjectTemplateNameValuesNew::create([
                    'project_template_id' => $projectTemplateInfo->id,
                    'template_data_json' => json_encode($templateDataJson),
                    'distributor_id' => $matchedMasterId
                ]);

//                dd($projectTemplateNameValues->id);
                $projectInfo = Project::find($projectTemplateInfo->project_id);
                $templateNameInfo = TemplateName::find($projectTemplateInfo->template_name_id);
                $companyId = $projectInfo->company_id;
                $zoneId = $projectInfo->zone_id;
                $unitId = $projectInfo->unit_id;

                $activityGroupId = $request->activity_group_id;
                $activity_id = $request->activity_id;
                $activityIdsArr = [];

                if ($activityGroupId) {
                    $group_activities = ActivityGroupPivot::where('activity_group_id', $activityGroupId)->get();
                    foreach ($group_activities as $group_activity) {
                        $activityIdsArr[] = $group_activity->activity_id;
                    }
                } else if ($activity_id) {
                    $activityIdsArr[] = $activity_id;
                } else {
//                    return response()->json(['status' => 401, 'message' => 'activity_id or activity_group_id not found']);
                    if ($projectTemplateInfo->activityType == 0) {
                        $activityIdsArr[] = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                    } elseif ($projectTemplateInfo->activityType == 1) {
                        $activityIdsArr = array_merge(
                            $activityIdsArr,
                            DB::table('activity_group_pivots')->where('activity_group_id', $projectTemplateInfo->activity_group_name_id_or_activity_id)
                                ->pluck('activity_id')
                                ->toArray()
                        );
                    }
                }

                $checkOutletAssign = null;
                if ($projectTemplateInfo->is_master == 1) {

                    $activity_group_name_id_or_activity_id = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                    $checkOutletAssign = DataAssign::where('project_id', $projectTemplateInfo->project_id)
                        ->where('template_name_id', $projectTemplateInfo->template_name_id)
                        ->where('template_name_head_id', $projectTemplateInfo->main_header)
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->where('is_outlet_assigned', 1)
                        ->where(function ($query) use ($activity_group_name_id_or_activity_id) {
                            $query->where('activity_id', $activity_group_name_id_or_activity_id)
                                ->orWhere('activity_group_id', $activity_group_name_id_or_activity_id);
                        })
                        ->exists();
                }


                foreach ($activityIdsArr as $activity) {

//                if ($checkOutletAssign) {
                    if ($projectTemplateInfo->with_data == 1) {

                        $dataAssign = DataAssign::create([
                            'company_id' => $companyId,
                            'zone_id' => $zoneId,
                            'unit_id' => $unitId,
                            'project_id' => $projectInfo->id,
                            'activity_id' => $activity,
                            'template_name_id' => $templateNameInfo->id,
                            'project_template_id' => $projectTemplateInfo->id,
                            'activity_group_id' => $activityGroupId,
                            'template_name_head_id' => $get_header_id,
                            'is_outlet_assigned' => 0
                        ]);

                        $rowIds = DB::table('project_template_name_values_new')
                            ->where('project_template_id', $projectTemplateInfo->id)
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$get_header_id\"')) = ?", [trim($get_header_value)])
                            ->pluck('id')
                            ->toArray();

                        $this->assignUserActivityAndAuditRows(
                            $dataAssign->id,
                            $request->user_id,
                            $projectTemplateInfo->id,
                            $activity,
                            $activityGroupId,
                            $rowIds
                        );

                    }
                    else{

                        $getDataAssign = DB::table('data_assigns')->where('project_id', $projectInfo->id)
                            ->where('activity_id', $activity)
                            ->where('template_name_id', $templateNameInfo->id)
                            ->where('project_template_id', $projectTemplateInfo->id)
                            ->where('template_name_head_id', $get_header_id)
                            ->orderBy('id', 'desc')
                            ->first();

                        if (!empty($getDataAssign)) {

                            $getCommonId = DB::table('user_activity_data_assigns')->where('data_assign_id', $getDataAssign->id)
                                ->where('project_template_id', $projectTemplateInfo->id)
                                ->where('user_id', $request->user_id)
                                ->where('activity_id', $activity)
                                ->pluck('common_id')->toArray();

                            // add row ids on user auditor assign table
                            DB::table('user_audit_assigns')->insert([
                                'row_id' => $projectTemplateNameValues->id,
                                'common_id' => $getCommonId[0]
                            ]);

                        }
                        else {

                            //if mapped with is outlet assigned
                            $master_project_template = ProjectTemplate::where('project_id', $projectInfo->id)
                                ->where('is_master', 1)->first();

                            $activityOrGroupMaster = $projectTemplateInfo->activityType;
                            $activity_group_name_id_or_activity_id_master = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                            $activityIdsArrMaster = [];
                            $activityGroupIdMaster = null;
                            if ($activityOrGroupMaster == 1) {
                                $activityGroupId = $activity_group_name_id_or_activity_id_master;
                                $group_activities = ActivityGroupPivot::where('activity_group_id', $activity_group_name_id_or_activity_id_master)->get();
                                foreach ($group_activities as $group_activity) {
                                    $activityIdsArrMaster[] = $group_activity->activity_id;
                                }
                            } else {
                                $activityGroupId = null;
                                $activityIdsArrMaster[] = $activity_group_name_id_or_activity_id_master;
                            }

                            //get master data assign ids
                            $checkOutletAssignData = DB::table('data_assigns')->where('project_id', $projectInfo->id)
                                ->whereIn('activity_id', $activityIdsArrMaster)
                                ->where('template_name_id', $master_project_template->template_name_id)
                                ->where('project_template_id', $master_project_template->id)
//                            ->where('template_name_head_id', $get_header_id)
                                ->where('is_outlet_assigned', 1)
                                ->orderBy('id', 'desc')
                                ->get();

                            if (!$checkOutletAssignData->isEmpty()) {
                                $masterDataAssignIds = $checkOutletAssignData->pluck('id')->toArray();

                                //get child project template common ids linked with master data assign ids
                                $getCommonIds = DB::table('user_activity_data_assigns')
                                    ->whereIn('data_assign_id', $masterDataAssignIds)
                                    ->where('project_template_id', $projectTemplateInfo->id)
                                    ->where('user_id', $request->user_id)
                                    ->where('activity_id', $activity)
                                    ->pluck('common_id')->toArray();

                                // add row ids on user auditor assign table
                                foreach ($getCommonIds as $commonId) {
                                    DB::table('user_audit_assigns')->insert([
                                        'row_id' => $projectTemplateNameValues->id,
                                        'common_id' => $commonId
                                    ]);
                                }
                            }
                        }

                    }

                }
            }

            return response()->json(['status' => 200, 'message' => 'Distributor Added Successfully']);
        } catch (\Exception $e) {
            dd($e->getMessage());
            Log::info('Distributor not added' . $e->getMessage());
            return response()->json(['status' => 401, 'message' => 'Something went wrong']);
        }
    }

    public function storeTemplateHeaderValuesOldOctober(Request $request)
    {

        $request->validate(
            [
                'activity_id' => 'nullable|numeric',
                'activity_group_id' => 'nullable|numeric',
                'project_template_id' => 'required|numeric',
                'user_id' => 'required|numeric'
            ]
        );

        // dd(auth()->id());
        try {
            $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
            if (!empty($projectTemplateInfo)) {
                $projectTemplateInfoDetails = $projectTemplateInfo->getTemplate;
                $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;

                $projectRowData = $request->except(['project_template_id', 'activity_group_id', 'activity_id', 'user_id']);
                // dd($projectTemplateHeaders);
                ksort($projectRowData);

                /**
                 * ðŸ”¹ Normalize request keys
                 * Map both original and normalized
                 */
                $normalizedRowData = [];
                foreach ($projectRowData as $key => $val) {
                    $normalizedKey = strtolower(str_replace(' ', '_', trim($key)));
                    $normalizedRowData[$normalizedKey] = $val;
                }

                $rules = [];
                foreach ($normalizedRowData as $key => $value) {
                    $rules[$key] = 'required';
                }

                // Validate
                $validator = Validator::make($normalizedRowData, $rules);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Validation error',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $is_template_master = $projectTemplateInfo->is_master;
                $get_header_id = "";
                $get_header_value = "";
                $templateDataJson = [];

                foreach ($projectTemplateHeaders as $headId => $projectData) {
                    $headerName = $projectData->template_head_name;

                    // ðŸ”¹ Normalize header name (same as request key normalization)
                    $headerKey = strtolower(str_replace(' ', '_', trim($headerName)));

                    $value = $normalizedRowData[$headerKey] ?? null;

                    // Store first non-empty header as reference
                    if (empty($get_header_id) && !empty($value)) {
                        $get_header_id = $projectData->id;
                        $get_header_value = $value;
                    }

                    $templateDataJson[$projectData->id] = $value;
                }

                // Insert single row with JSON data
                DB::table('project_template_name_values_new')->insert([
                    'project_template_id' => $projectTemplateInfo->id,
                    'template_data_json' => json_encode($templateDataJson),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $projectInfo = Project::find($projectTemplateInfo->project_id);
                $templateNameInfo = TemplateName::find($projectTemplateInfo->template_name_id);
                $companyId = $projectInfo->company_id;
                $zoneId = $projectInfo->zone_id;
                $unitId = $projectInfo->unit_id;

                $activityGroupId = $request->activity_group_id;
                $activity_id = $request->activity_id;
                $activityIdsArr = [];
//            $activityGroupId = null;
                if ($activityGroupId) {
                    $group_activities = ActivityGroupPivot::where('activity_group_id', $activityGroupId)->get();
                    foreach ($group_activities as $group_activity) {
                        $activityIdsArr[] = $group_activity->activity_id;
                    }
                } else if ($activity_id) {
                    $activityIdsArr[] = $activity_id;
                } else {
//                    return response()->json(['status' => 401, 'message' => 'activity_id or activity_group_id not found']);
                    if ($projectTemplateInfo->activityType == 0) {
                        $activityIdsArr[] = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                    } elseif ($projectTemplateInfo->activityType == 1) {
                        $activityIdsArr = array_merge(
                            $activityIdsArr,
                            DB::table('activity_group_pivots')->where('activity_group_id', $projectTemplateInfo->activity_group_name_id_or_activity_id)
                                ->pluck('activity_id')
                                ->toArray()
                        );
                    }
                }

                $checkOutletAssign = null;
                if ($projectTemplateInfo->is_master == 1) {

                    $activity_group_name_id_or_activity_id = $projectTemplateInfo->activity_group_name_id_or_activity_id;

                    $checkOutletAssign = DataAssign::where('project_id', $projectTemplateInfo->project_id)
                        ->where('template_name_id', $projectTemplateInfo->template_name_id)
                        ->where('template_name_head_id', $projectTemplateInfo->main_header)
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->where('is_outlet_assigned', 1)
                        ->where(function ($query) use ($activity_group_name_id_or_activity_id) {
                            $query->where('activity_id', $activity_group_name_id_or_activity_id)
                                ->orWhere('activity_group_id', $activity_group_name_id_or_activity_id);
                        })
                        ->exists();
                }


                foreach ($activityIdsArr as $activity) {

//                if ($checkOutletAssign) {

                    $dataAssign = DataAssign::create([
                        'company_id' => $companyId,
                        'zone_id' => $zoneId,
                        'unit_id' => $unitId,
                        'project_id' => $projectInfo->id,
                        'activity_id' => $activity,
                        'template_name_id' => $templateNameInfo->id,
                        'project_template_id' => $projectTemplateInfo->id,
                        'activity_group_id' => $activityGroupId,
                        'template_name_head_id' => $get_header_id,
                        'is_outlet_assigned' => 0
                    ]);

                    $rowIds = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$get_header_id\"')) = ?", [trim($get_header_value)])
                        ->pluck('id')
                        ->toArray();

                    $this->assignUserActivityAndAuditRows(
                        $dataAssign->id,
                        $request->user_id,
                        $projectTemplateInfo->id,
                        $activity,
                        $activityGroupId,
                        $rowIds
                    );

                }
            }

            return response()->json(['status' => 200, 'message' => 'Distributor Added Successfully']);
        } catch (\Exception $e) {
//            dd($e->getMessage());
            Log::info('Distributor not added' . $e->getMessage());
            return response()->json(['status' => 401, 'message' => 'Something went wrong']);
        }
    }


    //khushboo 16-05-2025

    public function assignUserActivityAndAuditRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds)
    {
        if (empty($rowIds)) return null;

        // 1. Check if common_id already exists for this user+template+activity
        $commonId = UserActivityDataAssign::where([
            'data_assign_id' => $dataAssignId,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'project_template_id' => $templateId
        ])->value('common_id');

        if (!$commonId) {
            // 2. If not exists, create one
            $commonId = DB::table('user_activity_data_assigns')->max('common_id') + 1;

            $data = [
                'data_assign_id' => $dataAssignId,
                'user_id' => $userId,
                'project_template_id' => $templateId,
                'activity_id' => $activityId,
                'common_id' => $commonId
            ];

            if ($activityGroupId) {
                $data['activity_sequence_id'] = ActivityGroupPivot::where('activity_group_id', $activityGroupId)
                    ->where('activity_id', $activityId)
                    ->value('sequence');
            }

            UserActivityDataAssign::create($data);
        }

        // 3. Find already inserted row_ids
        $existingRowIds = UserAuditAssigns::where('common_id', $commonId)
            ->whereIn('row_id', $rowIds)
            ->pluck('row_id')
            ->toArray();

        // 4. Insert only missing rows under the SAME common_id
        $missingRowIds = array_diff($rowIds, $existingRowIds);

        foreach ($missingRowIds as $rowId) {
            UserAuditAssigns::create([
                'row_id' => $rowId,
                'common_id' => $commonId
            ]);
        }

        return $commonId;
    }


    public function getEditTemplateHeadersData($row_id)
    {

        try {

            $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
            $projectTemplateData = ProjectTemplate::find($projectTemplateNameValue->project_template_id);
            $projectTemplateHeads = $projectTemplateData->getTemplate->getTemplateHeads->pluck('id', 'template_head_name');
            $template_json_data = json_decode($projectTemplateNameValue->template_data_json, true);
//            dd($template_json_data, $projectTemplateHeads);
            // now you can safely map because $projectTemplateHeads is still a collection
            $mapped = $projectTemplateHeads->map(function ($id, $name) use ($template_json_data) {
                return [
                    'id' => $id,
                    'name' => $name,
                    'value' => $template_json_data[$id] ?? "",
                ];
            })->values()->toArray();
            $project = Project::find($projectTemplateData->project_id);
            $template = TemplateName::find($projectTemplateData->template_name_id);
            return response()->json([
                'status' => 200,
                'project_id' => $project->id,
                'template_id' => $template->id,
                'project_template_id' => $projectTemplateData->id,
                'data' => $mapped
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 401, 'message' => $e->getMessage()]);
        }
    }

    public function editTemplateHeaderValues(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'row_id'             => 'required',
            'project_template_id'=> 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 401,
                'message' => $validator->errors()->first()
            ], 401);
        }

        try{
            $row_id = $request->row_id;
            $excludedKeys = ['row_id', 'project_template_id', 'user_id'];
            $row_data = collect($request->all())
                ->except($excludedKeys)
                ->map(function ($value) {
                    return $value === null ? "" : $value; // replace null with ""
                });
            $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
            $projectTemplateInfoDetails = $projectTemplateInfo->getTemplate;
            $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;

            $normalizedRowData = [];
//            dd($row_data);
            foreach ($row_data as $key => $val) {
                $normalizedKey = strtolower(str_replace(' ', '_', trim($key)));
                $normalizedRowData[$normalizedKey] = $val;
            }

//            dd($projectTemplateHeaders);
            foreach ($projectTemplateHeaders as $headId => $projectData) {
                $headerName = $projectData->template_head_name;

                // ðŸ”¹ Normalize header name (same as request key normalization)
                $headerKey = strtolower(str_replace(' ', '_', trim($headerName)));

                $value = $normalizedRowData[$headerKey] ?? "";

//                dd($normalizedRowData, $value, $headerKey);
                // Store first non-empty header as reference
                if (empty($get_header_id) && !empty($value)) {
                    $get_header_id = $projectData->id;
                    $get_header_value = $value;
                }

                $templateDataJson[$projectData->id] = $value;
            }

            $templateJsonData = ProjectTemplateNameValuesNew::find($row_id);
//            dd($templateJsonData, $row_data);
            $templateJsonData->update([
                'template_data_json' => json_encode($templateDataJson),
            ]);
            return response()->json(['status' => 200, 'message' => 'Data Updated Successfully']);
        }catch(\Exception $e){
            return response()->json(['status' => 401, 'message' => $e->getMessage()]);
        }
    }

    public function getOutletTemplatesAvailable($row_id, $user_id)
    {
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
        $project = Project::find($projectTemplate->project_id);
        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();
        $checkPrentOutletAssign = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
            ->where('is_outlet_assigned', 1)
            ->exists();
        $getChildTemplateAssignedIds = [];
        if ($checkPrentOutletAssign) {
            $parentDataAssignIds = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();
            $getChildTemplateAssignedIds = DB::table('user_activity_data_assigns')->where('user_id', $user_id)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('project_template_id')
                ->pluck('project_template_id')->toArray();

        }

        $projectTemplateNames = [];
        if (!empty($getChildTemplateAssignedIds)) {
            foreach ($childTemplatesData as $childData) {
                if (($childData->with_data == 0 || $childData->data_add_on == 1) && in_array($childData->id, $getChildTemplateAssignedIds)) {
                    $projectTemplateNames[] = [
                        'template_id' => $childData->getTemplate->id,
                        'project_id' => $childData->project_id,
                        'template_name' => $childData->getTemplate->template_name
                    ];
                }
            }
        }

        return response([
            'status' => 200,
            'message' => 'sucesss',
            'project_id' => $project->id,
            'template_names' => $projectTemplateNames
        ], 200);

    }


}
