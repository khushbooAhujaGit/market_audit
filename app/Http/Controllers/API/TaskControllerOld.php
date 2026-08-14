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

    public function getAllActivity($id, $user_id)
    {

        try {

            $user = $user_id;
            $userAssignedActivities = [];
            $project_master_template = ProjectTemplate::where('project_id', $id)
                ->where('is_master', 1)
                ->first();

            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')->where('project_id', $id)
                ->where('id', '!=', $project_master_template->id)
                ->get();
//            $distinct_data_assignIds = AuditorAssignedData::where('project_id', $id)
//                ->distinct('data_assign_id')
//                ->where('user_id', $user)
//                ->pluck('data_assign_id');

            //khushboo 07-07-2025
            $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user)
                ->distinct('data_assign_id')
                ->pluck('data_assign_id');
            //khushboo 07-07-2025

            $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
                ->where('project_id', $id)
                ->whereIn("id", $distinct_data_assignIds)
                ->get();

            foreach ($data_assign_info as $assigned_data) {
                $sequence = null;
                if (isset($assigned_data->activity_group_id)) {
                    $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                        ->where('activity_group_id', $assigned_data->activity_group_id)
                        ->first();
                    $sequence = $group_activity_info->sequence;
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
                if ($assigned_data->is_outlet_assigned) {
                    foreach ($project_other_templates as $other_project_template_info) {
                        if ($other_project_template_info->activityType) {
                            $group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->find($other_project_template_info->activity_group_name_id_or_activity_id);
                            foreach ($group_info->get_group_activities as $group_activity_info) {
                                $current_template_name = $other_project_template_info->getTemplate->template_name;
                                $current_activity_name = $group_activity_info->getActivityInfo->activity_name;
                                $exists = false;
                                foreach ($userAssignedActivities as $activity) {
                                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                                        $exists = true;
                                        break; // Exit loop if a match is found
                                    }
                                }

                                if (!$exists) {
                                    $userAssignedActivities[] = [
                                        'template_name_id' => $other_project_template_info->getTemplate->id,
                                        'template_name' => $other_project_template_info->getTemplate->template_name,
                                        'is_master' => 0,
                                        'activity_id' => $group_activity_info->activity_id,
                                        'activity_name' => $group_activity_info->getActivityInfo->activity_name,
                                        'group_id' => $group_info->id, // group id
                                        'group_name' => $group_info->activity_group_name,
                                        'sequence' => $group_activity_info->sequence
                                    ];
                                }
                            }
                        } else {
                            $current_template_name = $other_project_template_info->getTemplate->template_name;
                            $current_activity_name = $other_project_template_info->activity->activity_name;
                            $exists = false;
                            foreach ($userAssignedActivities as $activity) {
                                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                                    $exists = true;
                                    break; // Exit loop if a match is found
                                }
                            }

                            if (!$exists) {
                                $userAssignedActivities[] = [
                                    'template_name_id' => $other_project_template_info->getTemplate->id,
                                    'template_name' => $other_project_template_info->getTemplate->template_name,
                                    'is_master' => 0,
                                    'activity_id' => $other_project_template_info->activity->id,
                                    'activity_name' => $other_project_template_info->activity->activity_name,
                                    'group_id' => null,
                                    'group_name' => null,
                                    'sequence' => null
                                ];
                            }
                        }
                    }
                }
            }
            $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();

            return response([
                'status' => 200,
                'message' => 'success',
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
        $group_session_id = null;
        if (!isset($request->group_info_id)) {
            $group_info = null;
        } else {
            $group_session_id = $request->group_info_id;
        }

        $row_renderred_arr = [];
        $user = $request->user_id;
        $project_data_arr = [];

        $project_data_completed_arr = [];
        $project_temp_info = DB::table('project_templates')->where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_id)
            ->first();

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

            if (!empty($otpRequiredIds) && in_array($request->activity_id, $otpRequiredIds)) {
                $otpRequiredStatus = 1;
            }
        }
        //khushboo 07-04-2025


        // dd($request->project_id, $request->template_id);
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;
        if ($project_temp_info->is_master == 1) {

            $getDataAssignIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->where('activity_id', $request->activity_id)
                ->when($group_session_id, function ($query) use ($group_session_id) {
                    $query->where('activity_group_id', $group_session_id);
                })
                ->distinct('id')
                ->pluck('id');

            $getDataAssignTemplateHeadIds = DB::table('data_assigns')->where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->where('activity_id', $request->activity_id)
                ->when($group_session_id, function ($query) use ($group_session_id) {
                    $query->where('activity_group_id', $group_session_id);
                })
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

            //khushboo 05-07-25

//             dd($checkingIfProjectAssigned);
            if ($checkingIfProjectAssigned) {
                $projectTemplateHeadsAssigned = [];

                //khushboo 05-07-25
                if (!empty($getDataAssignTemplateHeadIds)) {
                    foreach ($getDataAssignTemplateHeadIds as $headId) {

                        $getHeadValues = DB::table('project_template_name_values_new')
                            ->whereIn('id', $getRowIds)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                            )
                            ->get();

                        $project_templates_data = ProjectTemplate::where('project_id', $request->project_id)
                            ->where('template_name_id', $request->template_id)->first();

                        if (count($getHeadValues) > 0) {
                            foreach ($getHeadValues as $headValue) {
//                                dd($headValue->head_value);
                                $projectTemplateHeadsAssigned[] = [
                                    'project_template_id' => $project_templates_data->id,
                                    'head_id' => $headId,
                                    'head_value' => $headValue->head_value, // Add more fields if needed
                                ];
                            }
                        }

                    }
                }
                //khushboo 05-07-25

                $projectTemplateIDs = array_column($projectTemplateHeadsAssigned, 'project_template_id');
//                dd($projectTemplateIDs);

                $allTemplates = DB::table('project_template_name_values_new')
                    ->select('id as row_id', 'project_template_id', 'template_data_json')
                    ->whereIn('project_template_id', $projectTemplateIDs)
                    ->get();

                $templateHeads = DB::table('template_name_heads')
                    ->select('id', 'template_name_id', 'template_head_name', 'created_at', 'updated_at', 'deleted_at')
                    ->get()
                    ->keyBy('id');

                $allTemplateValues = [];

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

// Group by row_id from all project templates
                $allTemplateValuesFlat = collect($allTemplateValues)->flatMap(function ($group) {
                    return $group;
                });

                $rowGroupedValues = $allTemplateValuesFlat->groupBy('row_id');

                $totalcompletedproject=0;
                $totalproject=0;

                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
                    $template_id = $assigned_value_info['project_template_id'];
                    $templateValues = collect($allTemplateValues[$template_id] ?? []);
//                    dd($templateValues);

                    if (isset($request->group_info_id)) {
                        $activity_sequence = DB::table('activity_group_pivots')->where('activity_id', $request->activity_id)
                            ->where('activity_group_id', $request->group_info_id)
                            ->first();

                        if ($activity_sequence) {
                            $cur_sequence = $activity_sequence->sequence;
                            // this is to handle if the activity if the first activity of the group or having the first sequence
                            if ($cur_sequence == 1) {
//                                $projectDataArr = $templateValues->where('template_name_head_id', $assigned_value_info['head_id'])
//                                    ->where('value', $assigned_value_info['head_value']);
//                                $projectDataArr = $templateValues->
//                                dd($projectDataArr);
                                $projectDataArr = collect($templateValues)
                                    ->where('template_name_head_id', $assigned_value_info['head_id'])
                                    ->where('value', $assigned_value_info['head_value']);

                            } else {
                                // this is to handle if the activity sequence if greater then 1
                                $previous_sequence = $cur_sequence - 1;
                                $get_previous_sequence_activities = DB::table('activity_group_pivots')->where('activity_group_id', $request->group_info_id)
                                    ->where('sequence', $previous_sequence)
                                    ->get();
                                $previous_sequence_answered_rows = [];
                                foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
//                                    dd($templateValues);
                                    $rows_in_templates = $templateValues->pluck('row_id')->unique()->toArray();

//                                    dd($rows_in_templates);
                                    $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')->where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                                        //                                    ->where('activity_sequence', $previous_sequence_activity->sequence)
                                        ->where('activity_id', $previous_sequence_activity->activity_id)
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

//                                dd($projectDataArr->IsEmpty());
                                if ($projectDataArr->IsEmpty()) {
                                    $projectDataArr = collect($templateValues)
                                        ->where('template_name_head_id', $assigned_value_info['head_id'])
                                        ->where('value', $assigned_value_info['head_value']);
                                }
                            }
                        } else {
                            abort(404); // is to render if the activity if not present in the group
                        }
                    } else {
                        $projectDataArr = $templateValues
                            ->where('template_name_head_id', $assigned_value_info['head_id'])
                            ->where('value', $assigned_value_info['head_value']);
                    }
//                    dd($projectDataArr);
                    $getAllQuestionIds = DB::table('questions')
                        ->where('activity_id', $request->activity_id)
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
                            //khushboo 02-05-2025
//                            $getAllQuestionIds = DB::table('questions')->where('activity_id', $request->activity_id)
//                                ->pluck('id')
//                                ->toArray();

                            $totalQuestions = count($getAllQuestionIds);

                            $filteredAnswers = $allTemplateActivityAnswers[$projectData->row_id] ?? collect();

                            $answeredCount = $filteredAnswers
                                ->pluck('question_id')
                                ->unique()
                                ->count();

                            $allAnswered = $answeredCount === $totalQuestions;
                            $otpVerificationDone = false;

                            if ($allAnswered) {
                                $lastQuestionAnswered = $filteredAnswers
                                    ->sortByDesc('id')
                                    ->first();

                                if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                                    $otpVerificationDone = true;
                                }
                            }

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
                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "Awaiting OTP Verify";
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
                            if($projectStatus == 'completed'){
                                $totalcompletedproject++;
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
                            ];

//                            $project_data_arr[] = [
//                                'data_item' => $projectData,
//                                'status' => $projectStatus,
//                                'main_header' => $main_header,
//                                'sub_header' => $sub_header
//                            ];

                        }
                    }
                }

                if($totalcompletedproject == $totalproject){
                    $add_outlet = false;
                }

                Session::put('project_dist_activity', ['project' => $request->project_id, 'template' => $request->template_id, 'activity' => $request->activity_id, "group_info" => $group_session_id]);
                if (Session::has('project_outlet_activity')) {
                    Session::forget('project_outlet_activity');
                }
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;


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
                    'template_id' => $request->template_id,
                    'activity_id' => $request->activity_id,
                    "group_info" => $group_session_id,
                    'data' => $paginatedDistributors,
                    'otp_required_status' => (string)$otpRequiredStatus,
                    'add_outlet' => $add_outlet,

                ], 200);

            } else {
                return response([
                    'status' => 401,
                    'message' => 'success',
                    'data' => $project_data_arr,
                ], 401);
            }
        }
    }


    public function projectDistributorDataOld(Request $request)
    {

        $startTime = microtime(true);
        $group_session_id = null;
        if (!isset($request->group_info_id)) {
            $group_info = null;
        } else {
            $group_session_id = $request->group_info_id;
        }

        $row_renderred_arr = [];
        $user = $request->user_id;
        $project_data_arr = [];


        $project_data_completed_arr = [];
        $project_temp_info = DB::table('project_templates')->where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_id)
            ->first();


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

            if (!empty($otpRequiredIds) && in_array($request->activity_id, $otpRequiredIds)) {
                $otpRequiredStatus = 1;
            }
        }
        //khushboo 07-04-2025


        // dd($request->project_id, $request->template_id);
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;
        if ($project_temp_info->is_master == 1) {
            // dd('fdgfg');
            // this is to check if the project is assigned to the logged in user or not
            $checkingIfProjectAssigned = AuditorAssignedData::where('user_id', $user)
                ->where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->whereHas('dataAssign', function ($query) use ($request, $group_session_id) {
                    $query->where('activity_id', $request->activity_id)
                        ->where('activity_group_id', $group_session_id);
                })
                ->get();

//             dd($checkingIfProjectAssigned);
            if (count($checkingIfProjectAssigned)) {
                $projectTemplateHeadsAssigned = [];
                // this is to get the template heads and values that are assigned to that user
                foreach ($checkingIfProjectAssigned as $auditor_assiged_data) {
                    $project_template_id = $auditor_assiged_data->dataAssign->project_template_id;
                    $projectTemplateHeadsAssigned[] = [
                        'project_template_id' => $project_template_id,
                        'head_id' => $auditor_assiged_data->dataAssign->template_name_head_id,
                        'head_value' => $auditor_assiged_data->template_name_head_value, // Add more fields if needed
                    ];
                }

                $projectTemplateIDs = array_column($projectTemplateHeadsAssigned, 'project_template_id');

//                $allTemplateValues = DB::table('project_template_name_values')
//                    ->whereIn('project_template_id', $projectTemplateIDs)
//                    ->get()
//                    ->groupBy('project_template_id');

                $allTemplateValues = DB::table('project_template_name_values as ptv')
                    ->leftJoin('template_name_heads as tnh', 'ptv.template_name_head_id', '=', 'tnh.id')
                    ->select(
                        'ptv.*',
                        'tnh.id as head_id',
                        'tnh.template_name_id as head_template_name_id',
                        'tnh.template_head_name',
                        'tnh.created_at as head_created_at',
                        'tnh.updated_at as head_updated_at',
                        'tnh.deleted_at as head_deleted_at'
                    )
                    ->whereIn('ptv.project_template_id', $projectTemplateIDs)
                    ->get()
                    ->groupBy('project_template_id');

                $allTemplateActivityAnswers = DB::table('temp_user_activity_answers_data')->get();

//                $rowGroupedValues = $allTemplateValues->flatMap(fn($group) => $group)->groupBy('row_id');

                $rowGroupedValues = $allTemplateValues->flatMap(function ($group) {
                    return $group->map(function ($item) {
                        // Add get_head_name to each row
                        $item->get_head_name = [
                            'id' => $item->head_id,
                            'template_name_id' => $item->head_template_name_id,
                            'template_head_name' => $item->template_head_name,
                            'deleted_at' => $item->head_deleted_at,
                            'created_at' => $item->head_created_at,
                            'updated_at' => $item->head_updated_at,
                        ];
                        return $item;
                    });
                })->groupBy('row_id');

//                dd($projectTemplateHeadsAssigned);
                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
//                    print_r($assigned_value_info);
//                    echo 'fghgfh';
                    $template_id = $assigned_value_info['project_template_id'];
                    $templateValues = $allTemplateValues[$template_id] ?? collect();
//                    dd($templateValues);

                    if (isset($request->group_info_id)) {
                        $activity_sequence = DB::table('activity_group_pivots')->where('activity_id', $request->activity_id)
                            ->where('activity_group_id', $request->group_info_id)
                            ->first();

                        if ($activity_sequence) {
                            $cur_sequence = $activity_sequence->sequence;

                            // this is to handle if the activity if the first activity of the group or having the first sequence
                            if ($cur_sequence == 1) {
                                $projectDataArr = $templateValues->where('template_name_head_id', $assigned_value_info['head_id'])
                                    ->where('value', $assigned_value_info['head_value']);

//                              dd($projectDataArr);
                            } else {
                                // this is to handle if the activity sequence if greater then 1
                                $previous_sequence = $cur_sequence - 1;
                                $get_previous_sequence_activities = DB::table('activity_group_pivots')->where('activity_group_id', $request->group_info_id)
                                    ->where('sequence', $previous_sequence)
                                    ->get();
                                $previous_sequence_answered_rows = [];
                                foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
//                                    dd('fhgfh');
                                    $rows_in_templates = $templateValues->unique('row_id')->pluck('row_id')->toArray();

                                    $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')->where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                                        //                                    ->where('activity_sequence', $previous_sequence_activity->sequence)
                                        ->where('activity_id', $previous_sequence_activity->activity_id)
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
                            }
                        } else {
                            abort(404); // is to render if the activity if not present in the group
                        }

                    } else {
//                        echo $assigned_value_info['head_id'], $assigned_value_info['head_value'];
                        $projectDataArr = $templateValues
                            ->where('template_name_head_id', $assigned_value_info['head_id'])
                            ->where('value', $assigned_value_info['head_value']);
                    }

//                    dd($projectDataArr);
//                    print_r($projectDataArr);

                    $getAllQuestionIds = DB::table('questions')
                        ->where('activity_id', $request->activity_id)
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

                            //khushboo 02-05-2025
//                            $getAllQuestionIds = DB::table('questions')->where('activity_id', $request->activity_id)
//                                ->pluck('id')
//                                ->toArray();

                            $totalQuestions = count($getAllQuestionIds);

                            $filteredAnswers = $allTemplateActivityAnswers[$projectData->row_id] ?? collect();

                            $completedAnswersCount = $filteredAnswers->where('status', 5)->count();
                            $rejectedAnswersCount = $filteredAnswers->where('status', 4)->count();

                            $answeredCount = $filteredAnswers
                                ->pluck('question_id')
                                ->unique()
                                ->count();

                            $allAnswered = $answeredCount === $totalQuestions;
                            $otpVerificationDone = false;

                            if ($allAnswered) {
                                $lastQuestionAnswered = $filteredAnswers
                                    ->sortByDesc('id')
                                    ->first();

                                if (!empty($lastQuestionAnswered->mobile_otp) && $lastQuestionAnswered->otp_verified_status == 1) {
                                    $otpVerificationDone = true;
                                }
                            }

                            // dd($allAnswered);
                            //khushboo 02-05-2025
                            // $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData->row_id)
                            //     ->where('activity_id', $request->activity_id)->exists();

//                            $get_row_header = $allTemplateValues->where('row_id', $projectData->row_id)
//                                ->where('template_name_head_id', $main_header_id)
//                                ->first();

                            $get_row_header = $rowGroupedValues[$projectData->row_id]
//                                ->firstWhere('template_name_head_id', $assigned_value_info['head_id']);
                                ->firstWhere('template_name_head_id', $main_header_id);

//                            dd($get_row_header);
//                            echo $get_row_header->value, '<pre>', $main_header_id, '<pre>', $projectData->row_id;

                            $main_header = null;
                            if ($get_row_header) {
                                $main_header = $get_row_header->value;
//                                $main_header = $assigned_value_info['head_value'];
                            }
//                            $get_row_sub_header = $allTemplateValues->where('row_id', $projectData->row_id)
//                                ->where('template_name_head_id', $sub_header_id)
//                                ->first();

                            $get_row_sub_header = $rowGroupedValues[$projectData->row_id]
                                ->firstWhere('template_name_head_id', $sub_header_id);

//                            echo $get_row_header->value, '<pre>', $sub_header_id, '<pre>', $projectData->row_id;

                            $sub_header = null;
                            if ($get_row_sub_header) {
                                $sub_header = $get_row_sub_header->value;
                            }

                            //khushboo 02-05-2025
                            $projectStatus = "pending";
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }

                            if ($rejectedAnswersCount > 0) {
                                $projectStatus = "rejected";
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

//                            $projectData->get_data_of_rows = $rowGroupedValues[$projectData->row_id] ?? collect();

                            $project_data_arr[] = [
                                'data_item' => (array)$projectData + [
                                        'get_head_name' => $get_head_name,
                                        'get_data_of_rows' => $rowGroupedValues[$projectData->row_id] ?? collect()
                                    ],
                                'status' => $projectStatus,
                                'main_header' => $main_header,
                                'sub_header' => $sub_header
                            ];

//                            $project_data_arr[] = [
//                                'data_item' => $projectData,
//                                'status' => $projectStatus,
//                                'main_header' => $main_header,
//                                'sub_header' => $sub_header
//                            ];

                        }
                    }
                }
//                dd('hgkjh');
//                dd($project_data_arr);

                Session::put('project_dist_activity', ['project' => $request->project_id, 'template' => $request->template_id, 'activity' => $request->activity_id, "group_info" => $group_session_id]);
                if (Session::has('project_outlet_activity')) {
                    Session::forget('project_outlet_activity');
                }
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;


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

//                dd($paginatedDistributors);

                return response([
                    'status' => 200,
                    'message' => 'success',
                    'project_id' => $request->project_id,
                    'template_id' => $request->template_id,
                    'activity_id' => $request->activity_id,
                    "group_info" => $group_session_id,
                    'data' => $paginatedDistributors,
                    'otp_required_status' => (string)$otpRequiredStatus,
                    'add_outlet' => $add_outlet,

                ], 200);

            } else {
                return response([
                    'status' => 401,
                    'message' => 'success',
                    'data' => $project_data_arr,
                ], 401);
            }
        }
    }


    public function projectDistributorDataNew(Request $request)
    {
        $startTime = microtime(true);
        $group_session_id = null;
        if (!isset($request->group_info_id)) {
            $group_info = null;
        } else {
            $group_session_id = $request->group_info_id;
        }

        $row_renderred_arr = [];
        $user = $request->user_id;
        $project_data_arr = [];


        $project_data_completed_arr = [];
        $project_temp_info = ProjectTemplate::where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_id)
            ->first();


        //khushboo 17-05-25
        $add_outlet = false;
        if ($project_temp_info->data_add_on == 1) {
            $add_outlet = true;
        }
        $completedOutlet = 0;
        $projectOutletCount = 0;
        //khushboo 17-05-25

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


        // dd($request->project_id, $request->template_id);
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;
        if ($project_temp_info->is_master == 1) {
            // dd('fdgfg');
            // this is to check if the project is assigned to the logged in user or not
            $checkingIfProjectAssigned = AuditorAssignedData::where('user_id', $user)
                ->where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->whereHas('dataAssign', function ($query) use ($request, $group_session_id) {
                    $query->where('activity_id', $request->activity_id)
                        ->where('activity_group_id', $group_session_id);
                })
                ->get();
            // dd($checkingIfProjectAssigned);
            if (count($checkingIfProjectAssigned)) {
                $projectTemplateHeadsAssigned = [];
                // this is to get the template heads and values that are assigned to that user
                foreach ($checkingIfProjectAssigned as $auditor_assiged_data) {
                    $project_template_id = $auditor_assiged_data->dataAssign->project_template_id;
                    $projectTemplateHeadsAssigned[] = [
                        'project_template_id' => $project_template_id,
                        'head_id' => $auditor_assiged_data->dataAssign->template_name_head_id,
                        'head_value' => $auditor_assiged_data->template_name_head_value, // Add more fields if needed
                    ];
                }

                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
                    if (isset($request->group_info_id)) {
                        $activity_sequence = ActivityGroupPivot::where('activity_id', $request->activity_id)
                            ->where('activity_group_id', $request->group_info_id)
                            ->first();
                        if ($activity_sequence) {
                            $cur_sequence = $activity_sequence->sequence;
                            // this is to handle if the activity if the first activity of the group or having the first sequence
                            if ($cur_sequence == 1) {
                                $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows.getHeadName')->where('project_template_id', $assigned_value_info['project_template_id'])
                                    ->where('template_name_head_id', $assigned_value_info['head_id'])
                                    ->where('value', $assigned_value_info['head_value'])->get();
                            } else {
                                // this is to handle if the activity sequence if greater then 1
                                $previous_sequence = $cur_sequence - 1;
                                $get_previous_sequence_activities = ActivityGroupPivot::where('activity_group_id', $request->group_info_id)
                                    ->where('sequence', $previous_sequence)
                                    ->get();
                                $previous_sequence_answered_rows = [];
                                foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
                                    $rows_in_templates = ProjectTemplateNameValue::where('project_template_id', $assigned_value_info['project_template_id'])
                                        ->distinct('row_id')->pluck('row_id')->toArray();
                                    $get_previous_sequence_answered = TempUserActivityAnswersData::where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                                        //                                    ->where('activity_sequence', $previous_sequence_activity->sequence)
                                        ->where('activity_id', $previous_sequence_activity->activity_id)
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
                                $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows.getHeadName')->where('project_template_id', $assigned_value_info['project_template_id'])
                                    ->where('template_name_head_id', $assigned_value_info['head_id'])
                                    ->whereIn('row_id', $previous_sequence_answered_rows)
                                    ->where('value', $assigned_value_info['head_value'])->get();
                            }
                        } else {
                            abort(404); // is to render if the activity if not present in the group
                        }
                    } else {
                        $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows.getHeadName')->where('project_template_id', $assigned_value_info['project_template_id'])
                            ->where('template_name_head_id', $assigned_value_info['head_id'])
                            ->where('value', $assigned_value_info['head_value'])->get();
                    }
                    foreach ($projectDataArr as $projectData) {
                        if (!in_array($projectData->row_id, $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData->row_id;
                            //khushboo 02-05-2025
                            $getAllQuestionIds = Question::where('activity_id', $request->activity_id)
                                ->pluck('id')
                                ->toArray();

                            $totalQuestions = count($getAllQuestionIds);

                            $answeredCount = TempUserActivityAnswersData::where('row_id', $projectData->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->distinct('question_id') // To avoid duplicates
                                ->count('question_id');

                            $allAnswered = $answeredCount === $totalQuestions;
                            // $allAnswered = true;
                            $otpVerificationDone = false;
                            if ($allAnswered) {
                                $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $projectData->row_id)
                                    ->where('activity_id', $request->activity_id)
                                    ->whereIn('question_id', $getAllQuestionIds)
                                    ->orderBy('id', 'DESC')->first();
                                if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                                    $otpVerificationDone = true;
                                }
                            }
                            // dd($allAnswered);
                            //khushboo 02-05-2025
                            // $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData->row_id)
                            //     ->where('activity_id', $request->activity_id)->exists();
                            $get_row_header = ProjectTemplateNameValue::where('row_id', $projectData->row_id)
                                ->where('template_name_head_id', $main_header_id)
                                ->first();
                            $main_header = null;
                            if ($get_row_header) {
                                $main_header = $get_row_header->value;
                            }
                            $get_row_sub_header = ProjectTemplateNameValue::where('row_id', $projectData->row_id)
                                ->where('template_name_head_id', $sub_header_id)
                                ->first();
                            $sub_header = null;
                            if ($get_row_sub_header) {
                                $sub_header = $get_row_sub_header->value;
                            }

                            //khushboo 02-05-2025
                            $projectStatus = "pending";
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }

                            if ($projectStatus == "completed") {
                                $completedOutlet++;
                            }
                            $projectOutletCount++;
                            //khushboo 02-05-2025

                            $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $projectData->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->where('status', 4)
                                ->count();

                            if ($rejectedAnswersCount > 0) {
                                $projectStatus = "rejected";
                            }

                            $project_data_arr[] = [
                                'data_item' => $projectData,
                                'status' => $projectStatus,
                                'main_header' => $main_header,
                                'sub_header' => $sub_header
                            ];
                        }
                    }
                }
                Session::put('project_dist_activity', ['project' => $request->project_id, 'template' => $request->template_id, 'activity' => $request->activity_id, "group_info" => $group_session_id]);
                if (Session::has('project_outlet_activity')) {
                    Session::forget('project_outlet_activity');
                }
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;


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

                if ($completedOutlet > 0 && $projectOutletCount > 0 && $completedOutlet == $projectOutletCount) {
                    $add_outlet = false;
                }

                return response([
                    'status' => 200,
                    'message' => 'success',
                    'project_id' => $request->project_id,
                    'template_id' => $request->template_id,
                    'activity_id' => $request->activity_id,
                    "group_info" => $group_session_id,
                    'data' => $paginatedDistributors,
                    'otp_required_status' => (string)$otpRequiredStatus,
                    'add_outlet' => $add_outlet,

                ], 200);

            } else {
                return response([
                    'status' => 401,
                    'message' => 'success',
                    'data' => $project_data_arr,
                ], 401);
            }
        }
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


    public function projectOutletDataOld(Request $request)
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

        $allTemplateNamesValues = ProjectTemplateNameValue::with('getHeadName')
            ->where('project_template_id', $project_temp_info->id)
            ->get();

//        $allTemplateNamesValues = DB::table('project_template_name_values as ptv')
//            ->where('ptv.project_template_id', $project_temp_info->id)
//            ->leftJoin('template_name_heads as tnh', 'ptv.template_name_head_id', '=', 'tnh.id')
//            ->select(
//                'ptv.*',
//                    'tnh.*'
//            )
//            ->get();

//        dd($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

//           dd($cur_sequence_helper);
        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {

            $get_project_template_assigned_data = DB::table('auditor_assigned_data')
                ->join('data_assigns', 'auditor_assigned_data.data_assign_id', '=', 'data_assigns.id')
                ->select(
                    'auditor_assigned_data.*',
                    'data_assigns.*'
                )
                ->where('auditor_assigned_data.project_id', $request->project_id)
                ->where('auditor_assigned_data.user_id', $user->id)
                ->where('auditor_assigned_data.template_name_id', $project_temp_info->template_name_id)
                ->where('data_assigns.activity_id', $request->activity_id)
                ->where('data_assigns.activity_group_id', $request->group_info_id)
                ->get();

//            dd($get_project_template_assigned_data);

            $existingRowIds = [];

            foreach ($get_project_template_assigned_data as $get_outlet_assigned) {

                //khushboo 17-04-25
                // echo $get_outlet_assigned->audit_closed;
                // dd($get_outlet_assigned);
                if ($get_outlet_assigned->audit_closed == 1) {
                    $isAuditClosed = true;
                }
                //khushboo 17-04-25
                // dd($get_outlet_assigned);

                $exists = false;
//                $get_rows_of_outlet = $allTemplateNamesValues->where('project_template_id', $get_outlet_assigned->dataAssign->project_template_id)
//                    ->where('template_name_head_id', $get_outlet_assigned->dataAssign->template_name_head_id)
//                    ->where('value', $get_outlet_assigned->template_name_head_value)
//                    ->unique();

                $get_rows_of_outlet = $allTemplateNamesValues
//                    ->where('project_template_id', $get_outlet_assigned->dataAssign->project_template_id)
                    ->where('template_name_head_id', $get_outlet_assigned->template_name_head_id)
                    ->where('value', $get_outlet_assigned->template_name_head_value)
                    ->unique('row_id'); // Assuming you want unique rows by `row_id`

//                 dd($get_rows_of_outlet);

                foreach ($get_rows_of_outlet as $get_row_of_outlet) {
                    $exists = false;

                    $row_id = $get_row_of_outlet->row_id;
                    $row_data_group = $allTemplateGroupedByRow[$row_id] ?? collect();

                    // // Get the outlet assigned data for the current row

                    //khushboo 17-05-25
//                    if ($cur_sequence_helper == 1) {
//                        $row_data_of_outlet_assigned_data = $allTemplateNamesValues->where('row_id', $get_row_of_outlet->row_id)
//                            ->where('template_name_head_id', $own_head_id)
//                            ->first(); // this is giving me the value which is having the reference from the master db
//                    } else {
//                        $row_data_of_outlet_assigned_data = $allTemplateNamesValues->where('row_id', $get_row_of_outlet->row_id)
//                            ->where('template_name_head_id', $master_head_id)
//                            ->first();
//                    }
                    if ($cur_sequence_helper == 1) {
                        $row_data_of_outlet_assigned_data = $row_data_group
                            ->firstWhere('template_name_head_id', $own_head_id);
                    } else {
                        $row_data_of_outlet_assigned_data = $row_data_group
                            ->firstWhere('template_name_head_id', $master_head_id);
                    }

                    //khushboo 17-05-25


//                    // dd($row_data_of_outlet_assigned_data);
//                    if (empty($row_data_of_outlet_assigned_data)) {
//                        $row_data_of_outlet_assigned_data = $allTemplateNamesValues->where('row_id', $get_row_of_outlet->row_id)
//                            ->where('template_name_head_id', $own_head_id)
//                            ->first();
//                    }
                    // fallback if not found in the above condition
                    if (empty($row_data_of_outlet_assigned_data)) {
                        $row_data_of_outlet_assigned_data = $row_data_group
                            ->firstWhere('template_name_head_id', $own_head_id);
                    }

                    // echo $row_data_of_outlet_assigned_data->value;
                    if ($row_data_of_outlet_assigned_data) {

                        $rendered_values = array_column($distributors_arr, 'value');
                        if ((in_array($row_data_of_outlet_assigned_data->value, $rendered_values))) {
                            $exists = true;
                        }

                        // dd($row_data_of_outlet_assigned_data->value);
                        if (!$exists) {
                            // $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                            //     ->where('template_name_head_id', $master_head_id)
                            //     ->where('value', $row_data_of_outlet_assigned_data->value)
                            //     ->where('project_template_id', $outlet_master_info->id)
                            //     ->first();
                            // dd($master_head_id, $row_data_of_outlet_assigned_data->value, $outlet_master_info->id);

                            // $get_main_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                            //     ->where('template_name_head_id', $outlet_master_main_header_id)
                            //     ->first();

                            //khushboo 15-05-25
//                            dd($cur_sequence_helper);
                            if ($cur_sequence_helper == 1) {
                                if ($own_head_id) {
                                    $get_master_row = $allTemplateNamesValues
//                                        ::with('getDataOfRows')
                                        ->where('template_name_head_id', $own_head_id)
                                        ->where('value', $row_data_of_outlet_assigned_data->value)
                                        ->where('project_template_id', $project_temp_info->id)
                                        ->first();
                                } else {
                                    $get_master_row = $allTemplateNamesValues
//                                        ::with('getDataOfRows')
                                        ->where('template_name_head_id', $outlet_master_info->main_header)
                                        ->where('value', $row_data_of_outlet_assigned_data->value)
                                        ->where('project_template_id', $project_temp_info->id)
                                        ->first();
                                }
                            } else {
                                $get_master_row = $allTemplateNamesValues
//                                    ::with('getDataOfRows')
                                    ->where('template_name_head_id', $master_head_id) //$master_head_id
                                    ->where('value', $row_data_of_outlet_assigned_data->value)
                                    ->where('project_template_id', $outlet_master_info->id)  //$outlet_master_info
                                    ->first();
                            }
//                             dd($get_master_row);

                            if (empty($get_master_row)) {
                                $get_master_row = $allTemplateNamesValues
//                                    ::with('getDataOfRows')
                                    ->where('template_name_head_id', $own_head_id)
                                    ->where('value', $row_data_of_outlet_assigned_data->value)
                                    ->where('project_template_id', $project_temp_info->id)
                                    ->first();
                            }

                            $get_main_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $own_head_id)
                                ->first();
                            if (empty($get_main_header_val)) {
                                $get_main_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                    ->where('template_name_head_id', $outlet_master_main_header_id)
                                    ->first();
                            }


                            // dd($own_head_id, $get_master_row->row_id);
                            //khushboo 15-05-25

                            // dd($outlet_master_main_header_id, $get_master_row->row_id);
                            $dist_main_header = null;
                            if ($get_main_header_val) {
                                $dist_main_header = $get_main_header_val->value;
                            }

                            // this will give the dist head and $row_data_of_outlet_assigned_data->value value
                            // check if index between starting and ending index

                            $dist_info = count($distributors_arr);
                            if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                                $distributors_arr[] = [
                                    'value' => $row_data_of_outlet_assigned_data->value,
                                    'main_header' => $dist_main_header,
                                    'row_id' => $get_master_row->row_id
                                ];
                                continue;
                            }

                            // $get_sub_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                            //     ->where('template_name_head_id', $outlet_master_sub_header_id)
                            //     ->first();

                            //khushboo 16-05-25
                            $get_sub_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $project_temp_info->sub_header)
                                ->first();
                            if (empty($get_sub_header_val)) {
                                $get_sub_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                    ->where('template_name_head_id', $project_temp_info->sub_header)
                                    ->first();
                            }
                            //khushboo 16-05-25

                            $dist_sub_header = null;
                            if ($get_sub_header_val) {
                                $dist_sub_header = $get_sub_header_val->value;
                            }

                            //khushboo 19-05-25
                            // if($cur_sequence_helper == 1){
                            //     if($own_head_id){
                            //         $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                            //             ->where('template_name_head_id', $own_head_id)
                            //             ->where('value', $row_data_of_outlet_assigned_data->value)
                            //             ->get();
                            //     }else{
                            //         $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                            //             ->where('template_name_head_id', $outlet_master_info->sub_header)
                            //             ->where('value', $row_data_of_outlet_assigned_data->value)
                            //             ->get();
                            //     }
                            // }else{
                            //     $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                            //             ->where('template_name_head_id', $master_head_id)
                            //             ->where('value', $row_data_of_outlet_assigned_data->value)
                            //             ->get();
                            // }
                            //khushboo 19-05-25
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data_of_outlet_assigned_data->value)
                                ->get();
                            // dd($get_count_of_outlets);
                            if ($get_count_of_outlets->IsEmpty()) {
                                $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                    ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                                    ->where('value', $row_data_of_outlet_assigned_data->value)
                                    ->get();
                            }
                            if ($get_count_of_outlets->IsEmpty()) {
                                $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                    ->where('template_name_head_id', $outlet_master_info->main_header)  //$own_head_id
                                    ->where('value', $row_data_of_outlet_assigned_data->value)
                                    ->get();
                            }


                            //  dd($own_head_id, $row_data_of_outlet_assigned_data->value, $project_temp_info->id);

                            $completed_count = 0;
                            // Check if each outlet has been answered
                            foreach ($get_count_of_outlets as $outlet_data) {
                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                    ->where('activity_id', $request->activity_id)
                                    ->exists();
                                if ($check_if_answered) {
                                    $completed_count++;
                                }
                            }
                            $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)
                                ->first();
                            $min_value = $outlet_master_min_completion;
                            $completion_type_value = $outlet_master_completion_type;
                            if ($check_if_distributor_row_setted) {
                                $min_value = $check_if_distributor_row_setted->min_completion;
                                $completion_type_value = $check_if_distributor_row_setted->completion_type;
                            }

                            //khushboo 16-05-25
                            $getAllQuestionIds = Question::where('activity_id', $activity->id)
                                ->pluck('id')
                                ->toArray();

                            $totalQuestions = count($getAllQuestionIds);

                            $answeredCount = TempUserActivityAnswersData::where('row_id', $get_master_row->row_id)
                                ->where('activity_id', $activity->id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->distinct('question_id') // To avoid duplicates
                                ->count('question_id');

                            $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $get_master_row->row_id)
                                ->where('activity_id', $activity->id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->where('status', 4)
                                ->count();


                            // $allAnswered = $answeredCount === $totalQuestions;
                            $allAnswered = false;
                            if ($answeredCount === $totalQuestions) {
                                $allAnswered = true;
                            }

                            $otpVerificationDone = false;
                            if ($allAnswered) {
                                $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $get_master_row->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->whereIn('question_id', $getAllQuestionIds)
                                    ->whereNotNull('mobile_otp')
                                    ->orderBy('id', 'DESC')->first();
                                if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                                    $otpVerificationDone = true;
                                }
                            }

                            $projectStatus = "pending";
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }

                            //khushboo 16-05-25
                            if ($projectStatus == "completed") {
                                $projectCompletedCount++;
                            }

                            if ($rejectedAnswersCount > 0) {
                                $projectStatus = "rejected";
                            }

                            $totalNumberOfOutlets++;

                            array_push($existingRowIds, $get_master_row->row_id);

                            // Add the current outlet data to the distributors array
                            $distributors_arr[] = [
                                'value' => $row_data_of_outlet_assigned_data->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $get_master_row->getDataOfRows,
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
            //  die('gjhgj');

            //check if both outlet assign and outlet not assigned exist
            $checkForBothData = false;
            if (!empty($distributors_arr)) {
                $checkForBothData = DB::table('data_assigns')
                    ->where('project_id', $request->project_id)
                    ->whereIn('is_outlet_assigned', [0, 1])
                    ->exists();
            }

//            echo !empty($distributors_arr), $checkForBothData;

            $checkingIfProjectAssigned = DB::table('auditor_assigned_data')
                ->join('data_assigns', 'auditor_assigned_data.data_assign_id', '=', 'data_assigns.id')
                ->select(
                    'auditor_assigned_data.*',
                    'data_assigns.*'
                )
                ->where('data_assigns.is_outlet_assigned', 1)
                ->where('auditor_assigned_data.project_id', $request->project_id)
                ->where('auditor_assigned_data.user_id', $user->id)
                ->where('auditor_assigned_data.template_name_id', $outlet_master_info->template_name_id)
                ->get();


//            dd($checkingIfProjectAssigned);
            if (!empty($checkingIfProjectAssigned)) {

                foreach ($checkingIfProjectAssigned as $distributor_assigned) {
//                 dd('gfhjg');

//                dd($distributor_assigned);

                    // Get the outlet assigned data for the current row

                    if ($distributor_assigned->template_name_head_id == $master_head_id) {
//                    dd('ergr');
//                        echo 'fdhgfhfg';
                        // means that the distributor heads which is taking reference in the outlet is assigned
                        $rendered_values = array_column($distributors_arr, 'main_header');
//

                        // First get the master row
                        $get_master_row = DB::table('project_template_name_values')
                            ->where('template_name_head_id', $master_head_id)
                            ->where('value', $distributor_assigned->template_name_head_value)
                            ->where('project_template_id', $project_temp_info->id)
                            ->first();

                        if (empty($get_master_row)) {
                            $get_master_row = DB::table('project_template_name_values')
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $distributor_assigned->template_name_head_value)
                                ->where('project_template_id', $project_temp_info->id)
                                ->first();
                        }

//                        echo $own_head_id, $distributor_assigned->template_name_head_value,  $project_temp_info->id;
//                        echo $get_master_row->row_id;
                        if (empty($get_master_row)) {
//                            $get_master_row = DB::table('project_template_name_values')->with('getDataOfRows')->where('template_name_head_id', $master_head_id)
//                                ->where('value', $distributor_assigned->template_name_head_value)
//                                ->where('project_template_id', $outlet_master_info->id)
//                                ->first();
                            $get_master_row = DB::table('project_template_name_values')
                                ->where('template_name_head_id', $master_head_id)
                                ->where('value', $distributor_assigned->template_name_head_value)
                                ->where('project_template_id', $outlet_master_info->id)
                                ->first();
                        }

//                        dd($get_master_row);
// Then manually load the relationship if needed
                        if ($get_master_row) {
                            $get_master_row->getDataOfRows = DB::table('project_template_name_values')
                                ->where('row_id', $get_master_row->row_id)->get();

                        }
//                        dd($get_master_row);

                        $get_headers_of_row = $allTemplateNamesValues->where('row_id', $get_master_row->row_id)
                            ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                            ->all();

//                        dd($get_headers_of_row, $outlet_master_sub_header_id, $outlet_master_main_header_id);

                        $dist_main_header = null;
                        $dist_sub_header = null;

//                        foreach ($get_headers_of_row as $header_of_row) {
//                            if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
//                                $dist_main_header = $header_of_row->value;
//                            }
//                            if ($header_of_row->template_name_head_id == $project_temp_info->sub_header) {
//                                $dist_sub_header = $header_of_row->value;
//                            }
//                        }

                        $getMainHeaderData = $allTemplateNamesValues->where('row_id', $get_master_row->row_id)
                            ->where('template_name_head_id', $outlet_master_main_header_id)
                            ->first();

                        $getSubHeaderData = $allTemplateNamesValues->where('row_id', $get_master_row->row_id)
                            ->where('template_name_head_id', $outlet_master_sub_header_id)
                            ->first();

//                        dd($getMainHeaderData);
//                        echo empty($getMainHeaderData);
//                        print_r($getSubHeaderData);
//                        echo $get_master_row->row_id;
                        $dist_main_header = !empty($getMainHeaderData) ? $getMainHeaderData->value : null;
                        $dist_sub_header = !empty($getSubHeaderData) ? $getSubHeaderData->value : null;

                        if ($dist_main_header == null) {
                            $get_main_header_val = $allTemplateNamesValues
                                ->where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $project_temp_info->main_header)
                                ->first();
                            if (!empty($get_main_header_val)) {
                                $dist_main_header = $get_main_header_val->value;
                            } else {
                                $get_main_header_val = $allTemplateNamesValues
                                    ->where('template_name_head_id', $project_temp_info->main_header)
                                    ->first();
                                $dist_main_header = $get_main_header_val->value;
                            }
                        }

                        if ($dist_sub_header == null) {
                            $get_sub_header_val = $allTemplateNamesValues
                                ->where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $project_temp_info->sub_header)
                                ->first();
                            if (!empty($get_main_header_val)) {
                                $dist_sub_header = $get_sub_header_val->value;
                            } else {
                                $get_sub_header_val = $allTemplateNamesValues
                                    ->where('template_name_head_id', $project_temp_info->sub_header)
                                    ->first();
                                $dist_sub_header = $get_sub_header_val->value;
                            }
                        }

                        if (!(in_array($dist_main_header, $rendered_values))) {

                            $dist_info = count($distributors_arr);
                            if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                                $distributors_arr[] = [
                                    'value' => $row_data_of_outlet_assigned_data->value,
                                    'main_header' => $dist_main_header,
                                    'row_id' => $get_master_row->row_id
                                ];
                                continue;
                            }
                            $get_count_of_outlets = $allTemplateNamesValues
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $get_master_row->value)
                                ->all();
                            $completed_count = 0;
//                         dd($get_count_of_outlets);
                            if (empty($get_count_of_outlets)) {
                                $get_count_of_outlets = $allTemplateNamesValues
                                    ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                                    ->where('value', $get_master_row->value)
                                    ->all();
                            }
                            // dd($get_count_of_outlets);
                            if (count($get_count_of_outlets) > 0) {
                                foreach ($get_count_of_outlets as $outlet_data) {
                                    $check_if_answered = DB::table('temp_user_activity_answers_data')->where('row_id', $outlet_data->row_id)
                                        ->where('activity_id', $request->activity_id)->exists();
                                    if ($check_if_answered) {
                                        $completed_count++;
                                    }
                                }
                            }

                            $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)
                                ->first();
                            $min_value = $outlet_master_min_completion;
                            $completion_type_value = $outlet_master_completion_type;
                            if ($check_if_distributor_row_setted) {
                                $min_value = $check_if_distributor_row_setted->min_completion;
                                $completion_type_value = $check_if_distributor_row_setted->completion_type;
                            }

                            //khushboo 16-05-25
                            $getAllQuestionIds = DB::table('questions')->where('activity_id', $activity->id)
                                ->pluck('id')
                                ->toArray();

                            $totalQuestions = count($getAllQuestionIds);
//                            echo $get_master_row->row_id;

                            $answeredCount = DB::table('temp_user_activity_answers_data')->where('row_id', $get_master_row->row_id)
                                ->where('activity_id', $activity->id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->distinct('question_id') // To avoid duplicates
                                ->count('question_id');

                            // $allAnswered = $answeredCount === $totalQuestions;
//                            echo $answeredCount,$totalQuestions;
                            $allAnswered = false;
                            if ($answeredCount === $totalQuestions) {
                                $allAnswered = true;
                            }

                            $otpVerificationDone = false;
                            if ($allAnswered) {
                                $lastQuestionAnswered = DB::table('temp_user_activity_answers_data')->where('row_id', $get_master_row->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->whereIn('question_id', $getAllQuestionIds)
                                    ->whereNotNull('mobile_otp')
                                    ->orderBy('id', 'DESC')->first();
                                if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                                    $otpVerificationDone = true;
                                }
                            }

                            $projectStatus = "pending";
//                             echo $allAnswered,$otpVerificationDone;
                            if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "completed";
                            } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                $projectStatus = "Awaiting OTP Verify";
                            } else if ($allAnswered) {
                                $projectStatus = "completed";
                            }

                            //khushboo 16-05-25
                            if ($projectStatus == "completed") {
                                $projectCompletedCount++;
                            }
//                            echo $projectStatus;
                            $totalNumberOfOutlets++;

                            $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $get_master_row->row_id)
                                ->where('activity_id', $request->activity_id)
                                ->whereIn('question_id', $getAllQuestionIds)
                                ->where('status', 4)
                                ->count();

                            if ($rejectedAnswersCount > 0) {
                                $projectStatus = "rejected";
                            }

//                            echo $distributor_assigned->template_name_head_value;

                            $distributors_arr[] = [
                                'value' => $distributor_assigned->template_name_head_value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $get_master_row->getDataOfRows,
                                'activity_id' => $request->activity_id,
                                'activity_name' => $activity->activity_name,
                                'template_id' => $request->template_id,
                                'project_id' => $request->project_id,
                                'project_template_id' => $project_temp_info->id,
                                'total_outlets' => count($get_count_of_outlets),
                                'total_outlet_answered' => $completed_count,
                                'min_completion' => $min_value,
                                'completion_type' => $completion_type_value,
                                'main_header' => $distributor_assigned->template_name_head_value,
                                'sub_header' => $dist_sub_header,
                                'status' => $projectStatus
                            ];
                        }
                    } else {
                        // dd('fhfgh');
                        // this means that the other head is assigned which is not the head
                        $get_rows_of_master = $allTemplateNamesValues
//                        where('project_template_id', $distributor_assigned->dataAssign->project_template_id)
                            ->where('template_name_head_id', $distributor_assigned->template_name_head_id)
                            ->where('value', $distributor_assigned->template_name_head_value)
                            ->where('project_template_id', $project_temp_info->id)
                            ->pluck('row_id');

                        // dd($distributor_assigned->dataAssign->project_template_id, $distributor_assigned->dataAssign->template_name_head_id, $distributor_assigned->template_name_head_value);

                        foreach ($get_rows_of_master as $row_of_master) {

                            // $master_row_info = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $row_of_master)
                            //     ->where('template_name_head_id', $master_head_id)
                            //     ->first();
                            // dd($cur_sequence_helper);


                            //khushboo 15-05-25
                            // dd($row_of_master, $own_head_id, $project_temp_info);
                            if ($cur_sequence_helper == 1) {
                                $master_row_info = $allTemplateNamesValues
//                                with('getDataOfRows')
                                    ->where('row_id', $row_of_master)
                                    ->where('template_name_head_id', $master_head_id) //$master_head_id //$outlet_master_info
                                    ->first();
                            } else {
                                if ($own_head_id) {
                                    $master_row_info = $allTemplateNamesValues
//                                    with('getDataOfRows')
                                        ->where('row_id', $row_of_master)
                                        ->where('template_name_head_id', $own_head_id) //$master_head_id //$outlet_master_info
                                        ->first();
                                } else {
                                    $master_row_info = $allTemplateNamesValues
//                                    with('getDataOfRows')
                                        ->where('row_id', $row_of_master)
                                        ->where('template_name_head_id', $outlet_master_info->main_header) //$master_head_id //$outlet_master_info
                                        ->first();
                                }

                            }
                            if (empty($master_row_info)) {
                                $master_row_info = $allTemplateNamesValues
//                                with('getDataOfRows')
                                    ->where('row_id', $row_of_master)
                                    ->where('template_name_head_id', $master_head_id) //$master_head_id //$outlet_master_info
                                    ->first();
                            }
                            if (empty($master_row_info)) {
                                $master_row_info = $allTemplateNamesValues
//                                with('getDataOfRows')
                                    ->where('row_id', $row_of_master)
                                    ->where('template_name_head_id', $outlet_master_info->main_header) //$master_head_id //$outlet_master_info
                                    ->first();
                            }
                            if ($master_row_info) {
                                $master_row_info->getDataOfRows = DB::table('project_template_name_values')
                                    ->where('row_id', $master_row_info->row_id)->get();
                            }

                            // dd($row_of_master, $own_head_id, $outlet_master_info);
                            $get_headers_of_row = $allTemplateNamesValues->where('row_id', $master_row_info->row_id)
                                ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                                ->all();
                            //khushboo 15-05-25
                            // dd($get_headers_of_row);

                            $dist_sub_header = null;
                            $dist_main_header = null;

//                            foreach ($get_headers_of_row as $header_of_row) {
//
//                                if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
//                                    $dist_sub_header = $header_of_row->value;
//                                }
//                                if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
//                                    $dist_main_header = $header_of_row->value;
//                                }
//                            }

                            $getMainHeaderData = $allTemplateNamesValues->where('row_id', $master_row_info->row_id)
                                ->where('template_name_head_id', $outlet_master_main_header_id)
                                ->first();

                            $getSubHeaderData = $allTemplateNamesValues->where('row_id', $master_row_info->row_id)
                                ->where('template_name_head_id', $outlet_master_sub_header_id)
                                ->first();

//                        dd($getMainHeaderData);
//                        echo empty($getMainHeaderData);
//                        print_r($getSubHeaderData);
//                        echo $get_master_row->row_id;
                            $dist_main_header = !empty($getMainHeaderData) ? $getMainHeaderData->value : null;
                            $dist_sub_header = !empty($getSubHeaderData) ? $getSubHeaderData->value : null;

                            if ($dist_main_header == null) {
                                $get_main_header_val = $allTemplateNamesValues
                                    ->where('row_id', $master_row_info->row_id)
                                    ->where('template_name_head_id', $project_temp_info->main_header)
                                    ->first();
                                if (!empty($get_main_header_val)) {
                                    $dist_main_header = $get_main_header_val->value;
                                } else {
                                    $get_main_header_val = $allTemplateNamesValues
                                        ->where('template_name_head_id', $project_temp_info->main_header)
                                        ->first();
                                    $dist_main_header = $get_main_header_val->value;
                                }
                            }

                            if ($dist_sub_header == null) {
                                $get_sub_header_val = $allTemplateNamesValues
                                    ->where('row_id', $master_row_info->row_id)
                                    ->where('template_name_head_id', $project_temp_info->sub_header)
                                    ->first();
                                if (!empty($get_main_header_val)) {
                                    $dist_sub_header = $get_sub_header_val->value;
                                } else {
                                    $get_sub_header_val = $allTemplateNamesValues
                                        ->where('template_name_head_id', $project_temp_info->sub_header)
                                        ->first();
                                    $dist_sub_header = $get_sub_header_val->value;
                                }
                            }

                            $rendered_values = array_column($distributors_arr, 'main_header');
                            // dd($get_headers_of_row);
                            // dd($rendered_values);
                            if (!(in_array($dist_main_header, $rendered_values))) {
                                //   dd($master_row_info);
                                $dist_info = count($distributors_arr);
                                if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                                    $distributors_arr[] = [
                                        'value' => $master_row_info->value,
                                        'main_header' => $dist_main_header,
                                        'row_id' => $master_row_info->row_id
                                    ];
                                    continue;
                                }
                                $get_count_of_outlets = $allTemplateNamesValues
//                                where('project_template_id', $project_temp_info->id)
                                    ->where('template_name_head_id', $own_head_id)
                                    ->where('value', $master_row_info->value)
                                    ->all();
                                // dd($get_count_of_outlets->IsEmpty());
                                $completed_count = 0;
                                if (empty($get_count_of_outlets)) {
                                    $get_count_of_outlets = $allTemplateNamesValues
//                                    where('project_template_id', $project_temp_info->id)
                                        ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                                        ->where('value', $master_row_info->value)
                                        ->all();
                                }
                                // dd($get_count_of_outlets, $outlet_master_info->main_header, $master_row_info->value, $project_temp_info->id);

                                if (count($get_count_of_outlets) > 0) {
                                    // dd($get_count_of_outlets);
                                    foreach ($get_count_of_outlets as $outlet_data) {

                                        $check_if_answered = DB::table('temp_user_activity_answers_data')->where('row_id', $outlet_data->row_id)
                                            ->where('activity_id', $request->activity_id)->exists();
                                        // dd($check_if_answered);
                                        if ($check_if_answered) {
                                            $completed_count++;
                                        }
                                    }
                                }
                                // dd(count($get_count_of_outlets));
                                $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $master_row_info->row_id)
                                    ->first();
                                $min_value = $outlet_master_min_completion;
                                $completion_type_value = $outlet_master_completion_type;
                                if ($check_if_distributor_row_setted) {
                                    $min_value = $check_if_distributor_row_setted->min_completion;
                                    $completion_type_value = $check_if_distributor_row_setted->completion_type;
                                }

                                //khushboo 16-05-25
                                $getAllQuestionIds = Question::where('activity_id', $activity->id)
                                    ->pluck('id')
                                    ->toArray();

                                $totalQuestions = count($getAllQuestionIds);

                                $answeredCount = TempUserActivityAnswersData::where('row_id', $master_row_info->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->whereIn('question_id', $getAllQuestionIds)
                                    ->distinct('question_id') // To avoid duplicates
                                    ->count('question_id');

                                // $allAnswered = $answeredCount === $totalQuestions;
                                $allAnswered = false;
                                if ($answeredCount === $totalQuestions) {
                                    $allAnswered = true;
                                }

                                $otpVerificationDone = false;
                                if ($allAnswered) {
                                    $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $master_row_info->row_id)
                                        ->where('activity_id', $activity->id)
                                        ->whereIn('question_id', $getAllQuestionIds)
                                        ->whereNotNull('mobile_otp')
                                        ->orderBy('id', 'DESC')->first();
                                    if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                                        $otpVerificationDone = true;
                                    }
                                }

                                $projectStatus = "pending";
                                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                                    $projectStatus = "completed";
                                } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                                    $projectStatus = "Awaiting OTP Verify";
                                } else if ($allAnswered) {
                                    $projectStatus = "completed";
                                }

                                //khushboo 16-05-25
                                if ($projectStatus == "completed") {
                                    $projectCompletedCount++;
                                }

                                $totalNumberOfOutlets++;

                                $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $master_row_info->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->whereIn('question_id', $getAllQuestionIds)
                                    ->where('status', 4)
                                    ->count();

                                if ($rejectedAnswersCount > 0) {
                                    $projectStatus = "rejected";
                                }

                                $distributors_arr[] = [
                                    // 'value' => $distributor_assigned->template_name_head_value,
                                    'value' => $master_row_info->value,
                                    'head_name' => $master_head_name,
                                    'getDataOfRows' => $master_row_info->getDataOfRows,
                                    'activity_id' => $request->activity_id,
                                    'activity_name' => $activity->activity_name,
                                    'template_id' => $request->template_id,
                                    'project_id' => $request->project_id,
                                    'project_template_id' => $project_temp_info->id,
                                    'total_outlets' => count($get_count_of_outlets),
                                    'total_outlet_answered' => $completed_count,
                                    'min_completion' => $min_value,
                                    'completion_type' => $completion_type_value,
                                    'main_header' => $dist_main_header,
                                    'sub_header' => $dist_sub_header,
                                    'status' => $projectStatus
                                ];
                            }
                        }
                    }
                }

            }
            // dd($distributors_arr);
        } else {
            //   dd('hkho');
            $previous_sequence = $cur_sequence_helper - 1;
            $rows_in_templates = $allTemplateNamesValues
//            where('project_template_id', $project_temp_info->id)
                ->distinct('row_id')->pluck('row_id')->toArray(); // these are the row_id under this project template
            // this is to get all the activities of the group of the particular group (current sequence - 1)
            $get_previous_sequence_activities = DB::table('activity_group_pivots')->where('activity_group_id', $request->group_info_id)
                ->where('sequence', $previous_sequence)
                ->get();
            // the below will return as the row_id which are answered in the previous sequece
            foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
                $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')->where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                    ->whereIn("row_id", $rows_in_templates)
                    ->where('activity_id', $previous_sequence_activity->activity_id)
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
            $get_project_template_assigned_data = DB::table('auditor_assigned_data')->where('project_id', $request->project_id)
                ->where('template_name_id', $project_temp_info->template_name_id)
                ->where('user_id', $user->id)
                ->whereHas('dataAssign', function ($query) use ($request) {
                    $query->where('activity_id', $request->activity_id)->where('activity_group_id', $request->group_info_id);
                })
                ->get();
            $previously_answered_dist = []; // this will contain all the distributors values which are answered from the previous activity
            foreach ($previous_sequence_answered_rows as $previous_sequence_answered_row) {
                $get_outlet_row = $allTemplateNamesValues
//                    ::with('getDataOfRows')
                    ->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                $get_master_row = $allTemplateNamesValues
//                    ::with('getDataOfRows')
                    ->where('template_name_head_id', $master_head_id)
                    ->where('value', $get_outlet_row->value)
                    ->first();
                $outlet_dist_get = $allTemplateNamesValues
//                    ::with('getDataOfRows')
                    ->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                if (!in_array($get_outlet_row->value, $previously_answered_dist)) {
                    $previously_answered_dist[$get_outlet_row->value] = $get_master_row;
                }
            }
            foreach ($previously_answered_dist as $dist_val => $previously_answered_dist_value) {
                $get_sub_main_header_val = $allTemplateNamesValues->where('row_id', $previously_answered_dist_value->row_id)
                    ->whereIn('template_name_head_id', [$outlet_master_main_header_id, $outlet_master_sub_header_id])
                    ->all();
                foreach ($get_sub_main_header_val as $headers_info) {
                    if ($headers_info->template_name_head_id == $outlet_master_main_header_id) {
                        $dist_main_header = $headers_info->value;
                    }
                    if ($headers_info->template_name_head_id == $outlet_master_sub_header_id) {
                        $dist_sub_header = $headers_info->value;
                    }
                }
                if (!in_array($dist_val, $distributors_arr)) {

                    $dist_info = count($distributors_arr);
                    if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                        $distributors_arr[] = [
                            'value' => $dist_val,
                            'main_header' => $dist_main_header,
                            'row_id' => $get_master_row->row_id
                        ];
                        continue;
                    }
                    $outlets_of_distributor = $allTemplateNamesValues
//                        ::where('project_template_id', $project_temp_info->id)
                        ->where('template_name_head_id', $own_head_id)
                        ->where('value', $dist_val)
                        ->whereIn("row_id", $previous_sequence_answered_rows)
                        ->pluck('row_id');

                    // dd($outlets_of_distributor);
                    if (empty($outlets_of_distributor)) {
                        $outlets_of_distributor = $allTemplateNamesValues
//                        where('project_template_id', $project_temp_info->id)
                            ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                            ->where('value', $dist_val)
                            ->whereIn("row_id", $previous_sequence_answered_rows)
                            ->pluck('row_id');
                    }
                    $total_outlets = count($outlets_of_distributor); // this will give the count of oulets
                    $answered_outlet_counter = 0;

                    foreach ($outlets_of_distributor as $outlet_row_id) {
                        $checkIfAnsered = DB::table('temp_user_activity_answers_data')->where('row_id', $outlet_row_id)
                            ->where('activity_group_name_id', $request->group_info_id)
                            ->where('activity_id', $request->activity_id)
                            ->exists();
                        if ($checkIfAnsered) {
                            $answered_outlet_counter++;
                        }
                    }

                    //khushboo 16-05-25
                    $getAllQuestionIds = DB::table('questions')->where('activity_id', $activity->id)
                        ->pluck('id')
                        ->toArray();

                    $totalQuestions = count($getAllQuestionIds);

                    $answeredCount = DB::table('temp_user_activity_answers_data')->where('row_id', $outlet_row_id)
                        ->where('activity_id', $activity->id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->distinct('question_id') // To avoid duplicates
                        ->count('question_id');

                    // $allAnswered = $answeredCount === $totalQuestions;
                    $allAnswered = false;
                    if ($answeredCount === $totalQuestions) {
                        $allAnswered = true;
                    }

                    $otpVerificationDone = false;
                    if ($allAnswered) {
                        $lastQuestionAnswered = DB::table('temp_user_activity_answers_data')->where('row_id', $outlet_row_id)
                            ->where('activity_id', $activity->id)
                            ->whereIn('question_id', $getAllQuestionIds)
                            ->whereNotNull('mobile_otp')
                            ->orderBy('id', 'DESC')->first();
                        // dd($lastQuestionAnswered);
                        if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                            $otpVerificationDone = true;
                        }
                    }

                    $projectStatus = "pending";
                    if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                        $projectStatus = "completed";
                    } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                        $projectStatus = "Awaiting OTP Verify";
                    } else if ($allAnswered) {
                        $projectStatus = "completed";
                    }

                    //khushboo 16-05-25
                    if ($projectStatus == "completed") {
                        $projectCompletedCount++;
                    }

                    $totalNumberOfOutlets++;


                    $distributors_arr[] = [
                        'value' => $dist_val,
                        'head_name' => $master_head_name,
                        'getDataOfRows' => $previously_answered_dist_value->getDataOfRows,
                        'activity_id' => $request->activity_id,
                        'activity_name' => $activity->activity_name,
                        'template_id' => $request->template_id,
                        'project_id' => $request->project_id,
                        'project_template_id' => $project_temp_info->id,
                        'total_outlets' => $total_outlets,
                        'total_outlet_answered' => $answered_outlet_counter,
                        'main_header' => $dist_main_header,
                        'sub_header' => $dist_sub_header,
                        'min_completion' => $outlet_master_min_completion,
                        'completion_type' => $outlet_master_completion_type,
                        'status' => $projectStatus
                    ];
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
                                'audit_closed_status' => 1,
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
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

    public function myProjectsDistributorOutletsData(Request $request, $projectTemplateId, $activityId, $distributor_value, $userId, $group_infoId = null)
    {
//         dd($user);
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
        $activity = Activity::find($activityId);
//        dd($activity);
        $user = User::find($userId);
        $group_info = ActivityGroup::find($group_infoId);
//        $projectTemplate = ProjectTemplate::find($projectTemplate);

        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;

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

        $rows_in_templates = DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplate->id)
            ->pluck('id')
            ->toArray();

        $allTemplates = DB::table('project_template_name_values_new')
            ->select('id as row_id', 'project_template_id', 'template_data_json')
            ->where('project_template_id', $projectTemplate->id)
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

        $allTemplateGroupedByRowAndHead = $allTemplateNamesValues->groupBy(function ($item) {
            return $item->row_id . '_' . $item->template_name_head_id;
        });

        $get_project_template_assigned_data = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $projectTemplate->id)
            ->where('activity_id', $activity->id)
            ->get();

        $dataAssignCommonIds = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $projectTemplate->id)
            ->where('activity_id', $activity->id)
            ->distinct('common_id')
            ->pluck('common_id');

        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

        $getDataAssignTemplateHeadIds = DataAssign::whereIn('id', $get_distinct_data_assigned_ids)
            ->pluck('template_name_head_id')
            ->unique();

        $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
            ->pluck('row_id');

        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            // dd($group_info);
            $group_session_id = $group_info->id;
            $activityGroupInfo = ActivityGroupPivot::where('activity_id', $activity->id)
                ->where('activity_group_id', $group_info->id)
                ->first();
            // dd($activityGroupInfo);
            $cur_sequence_helper = $activityGroupInfo->sequence;

            $is_to_check_previous_submitted = 1;
            $previous_sequence = $cur_sequence_helper - 1;

            // this is to get all the activities of the group of the particular group (current sequence - 1)
            $get_previous_sequence_activities = ActivityGroupPivot::where('activity_group_id', $group_info->id)
                ->where('sequence', $previous_sequence)
                ->get();
            // the below will return as the row_id which are answered in the previous sequece
            foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
                $get_previous_sequence_answered = DB::table('temp_user_activity_answers_data')
                    ->where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                    ->whereIn("row_id", $rows_in_templates)
                    ->where('activity_id', $previous_sequence_activity->activity_id)
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
        }

        $project_data_completed_arr = [];
        // dd($is_to_check_previous_submitted);

        $outlet_items = $allTemplateNamesValues
            ->where('project_template_id', $projectTemplate->id)
            ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
            ->where('value', $distributor_value);

        if ($is_to_check_previous_submitted) {
            $outlet_items = $outlet_items->whereIn('row_id', $previous_sequence_answered_rows);
        }

        $outlet_items = $outlet_items->values(); // Re-index the collection

//        dd($outlet_items);

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
                    ->where('activity_id', $activity->id)
                    ->isNotEmpty();

                $row_group = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();

                $main_header = optional($row_group->firstWhere('template_name_head_id', $outlet_main_header_id))->value;
                $sub_header = optional($row_group->firstWhere('template_name_head_id', $outlet_sub_header_id))->value;

                //khushboo 02-05-2025
                $getAllQuestionIds = DB::table('questions')->where('activity_id', $activity->id)
                    ->pluck('id')
                    ->toArray();

                $totalQuestions = count($getAllQuestionIds);
//                 dd($totalQuestions);

                $answeredCount = $allAnswers->where('row_id', $outlet_item->row_id)
                    ->where('activity_id', $activity->id)
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
                if ($allAnswered) {
                    $lastQuestionAnswered = $allAnswers->where('row_id', $outlet_item->row_id)
                        ->where('activity_id', $activity->id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->whereNotNull('mobile_otp')
                        ->sortByDesc('id')
                        ->first();
//                     dd($lastQuestionAnswered);
                    if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                        $otpVerificationDone = true;
                    }
                }

//                 dd($otpVerificationDone);
                $projectStatus = "pending";
                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "completed";
                } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "Awaiting OTP Verify";
                } else if ($allAnswered) {
                    $projectStatus = "completed";
                }
                //khushboo 02-05-2025

                if ($projectStatus == "completed") {
                    $projectCompletedStatus++;
                }

                $checkIfExists = ClosedAudits::where('project_template_id', $projectTemplate->id)
                    ->where('main_header', $main_header)
                    ->where('sub_header', $sub_header)
                    ->where('activity_id', $activity->id)
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
                    'status' => $projectStatus,
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                ];
            }
        }
        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'activity' => $activity->id, 'distributor_value' => $distributor_value, "group_info" => $group_session_id]);
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


        return response([
            'status' => 200,
            'message' => 'sucesss',
            'project_id' => $projectTemplate->id,
            'activity_id' => $activity->id,
            'distributor_value' => $distributor_value,
            'group_info' => $group_session_id,
            'otp_required_status' => $otpRequiredStatus,
            'add_distributor' => $add_outlet,
            'show_audit_close_button' => $showCLoseAuditOption,
            // 'is_audit_closed' => $IsAuditClosed,
            'data' => $paginatedDistributors,

        ], 200);
    }


    public function myProjectsDistributorOutletsDataOld(Request $request, $projectTemplateId, $activityId, $distributor_value, $userId, $group_infoId = null)
    {
//         dd($user);
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
        $activity = Activity::find($activityId);
//        dd($activity);
        $user = User::find($userId);
        $group_info = ActivityGroup::find($group_infoId);
//        $projectTemplate = ProjectTemplate::find($projectTemplate);

        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;

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
        // if (
        //     $project->is_otp_required == 1 &&
        //     !empty($projectTemplate->activity_otp_required_ids)
        //     ) {
        //         $otpRequiredIds = json_decode($projectTemplate->activity_otp_required_ids, true);

        //         if (!empty($otpRequiredIds) && in_array($request->activity_id, $otpRequiredIds)) {
        //             $otpRequiredStatus = 1;
        //         }
        // }
        // dd($otpRequiredStatus);
        //khushboo 07-04-2025

        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;
        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            // dd($group_info);
            $group_session_id = $group_info->id;
            $activityGroupInfo = ActivityGroupPivot::where('activity_id', $activity->id)
                ->where('activity_group_id', $group_info->id)
                ->first();
            // dd($activityGroupInfo);
            $cur_sequence_helper = $activityGroupInfo->sequence;
            // dd($cur_sequence_helper);
            if ($activityGroupInfo->sequence > 1) {
                $is_to_check_previous_submitted = 1;
                $previous_sequence = $cur_sequence_helper - 1;
                $rows_in_templates = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
                    ->distinct('row_id')->pluck('row_id')->toArray(); // these are the row_id under this project template
                // this is to get all the activities of the group of the particular group (current sequence - 1)
                $get_previous_sequence_activities = ActivityGroupPivot::where('activity_group_id', $group_info->id)
                    ->where('sequence', $previous_sequence)
                    ->get();
                // the below will return as the row_id which are answered in the previous sequece
                foreach ($get_previous_sequence_activities as $previous_sequence_activity) {
                    $get_previous_sequence_answered = TempUserActivityAnswersData::where('activity_group_name_id', $previous_sequence_activity->activity_group_id)
                        ->whereIn("row_id", $rows_in_templates)
                        ->where('activity_id', $previous_sequence_activity->activity_id)
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
            }
        }

        $project_data_completed_arr = [];
        // dd($is_to_check_previous_submitted);
        if ($is_to_check_previous_submitted) {
            $outlet_items = ProjectTemplateNameValue::with(['getHeadName', 'getDataOfRows.getHeadName'])->where('project_template_id', $projectTemplate->id)
                ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
                ->where('value', $distributor_value)
                ->whereIn('row_id', $previous_sequence_answered_rows)
                ->get();
        } else {

            //khushboo 15-05-25
            if ($projectTemplate->is_master == 1) {
                $outlet_items = ProjectTemplateNameValue::with(['getHeadName', 'getDataOfRows.getHeadName'])->where('project_template_id', $projectTemplate->id)
                    ->where('template_name_head_id', $projectTemplate->main_header)
                    ->where('value', $distributor_value)
                    ->get();
            } else {
                // dd($projectTemplate->main_header, $distributor_value);
                $outlet_items = ProjectTemplateNameValue::with(['getHeadName', 'getDataOfRows.getHeadName'])->where('project_template_id', $projectTemplate->id)
                    ->where('template_name_head_id', $projectTemplate->main_header)
                    ->where('value', $distributor_value)
                    ->get();
                if ($outlet_items->IsEmpty()) {
                    $outlet_items = ProjectTemplateNameValue::with(['getHeadName', 'getDataOfRows.getHeadName'])->where('project_template_id', $projectTemplate->id)
                        ->where('template_name_head_id', $projectTemplate->sub_header)
                        ->where('value', $distributor_value)
                        ->get();
                }
                if ($outlet_items->IsEmpty()) {
                    $outlet_items = ProjectTemplateNameValue::with(['getHeadName', 'getDataOfRows.getHeadName'])->where('project_template_id', $projectTemplate->id)
                        ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
                        ->where('value', $distributor_value)
                        ->get();
                }
            }
            //khushboo 15-05-25
        }

        // dd($outlet_items);

        $projectCompletedStatus = 0;
        $auditCompleteCounts = 0;

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->row_id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;
                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_item->row_id)
                    ->where('activity_id', $activity->id)->exists();
                $headers_info = ProjectTemplateNameValue::where('row_id', $outlet_item->row_id)
                    ->whereIn('template_name_head_id', [$outlet_main_header_id, $outlet_sub_header_id])
                    ->get();
                $main_header = null;
                $sub_header = null;
                foreach ($headers_info as $headerInfo) {
                    if ($headerInfo->template_name_head_id == $outlet_main_header_id) {
                        $main_header = $headerInfo->value;
                    }
                    if ($headerInfo->template_name_head_id == $outlet_sub_header_id) {
                        $sub_header = $headerInfo->value;
                    }
                }

                //khushboo 02-05-2025
                $getAllQuestionIds = Question::where('activity_id', $activity->id)
                    ->pluck('id')
                    ->toArray();

                $totalQuestions = count($getAllQuestionIds);
//                 dd($totalQuestions);

                $answeredCount = TempUserActivityAnswersData::where('row_id', $outlet_item->row_id)
                    ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->distinct('question_id') // To avoid duplicates
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
                if ($allAnswered) {
                    $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $outlet_item->row_id)
                        ->where('activity_id', $activity->id)
                        ->whereIn('question_id', $getAllQuestionIds)
                        ->whereNotNull('mobile_otp')
                        ->orderBy('id', 'DESC')->first();
//                     dd($lastQuestionAnswered);
                    if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                        $otpVerificationDone = true;
                    }
                }

//                 dd($otpVerificationDone);
                $projectStatus = "pending";
                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "completed";
                } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "Awaiting OTP Verify";
                } else if ($allAnswered) {
                    $projectStatus = "completed";
                }
                //khushboo 02-05-2025

                if ($projectStatus == "completed") {
                    $projectCompletedStatus++;
                }


                $checkIfExists = ClosedAudits::where('project_template_id', $projectTemplate->id)
                    ->where('main_header', $main_header)
                    ->where('sub_header', $sub_header)
                    ->where('activity_id', $activity->id)
                    ->where('row_id', $outlet_item->row_id)
                    ->where('user_id', $user->id)
                    ->where('distributor_value', $distributor_value)
                    ->exists();

                if ($checkIfExists) {
                    $projectStatus = "completed";
                    $auditCompleteCounts++;
                }

                $rejectedAnswersCount = TempUserActivityAnswersData::where('row_id', $outlet_item->row_id)
                    ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->where('status', 4)
                    ->count();

                if ($rejectedAnswersCount > 0) {
                    $projectStatus = "rejected";
                }


                // dd($projectStatus);
                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $projectStatus,
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                ];
            }
        }
        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'activity' => $activity->id, 'distributor_value' => $distributor_value, "group_info" => $group_session_id]);
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


        return response([
            'status' => 200,
            'message' => 'sucesss',
            'project_id' => $projectTemplate->id,
            'activity_id' => $activity->id,
            'distributor_value' => $distributor_value,
            'group_info' => $group_session_id,
            'otp_required_status' => $otpRequiredStatus,
            'add_distributor' => $add_outlet,
            'show_audit_close_button' => $showCLoseAuditOption,
            // 'is_audit_closed' => $IsAuditClosed,
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

//        dd($missingTypes);

//        if (!empty($missingTypes)) {
//            return response()->json([
//                'status' => 422,
//                'message' => 'Some required question types are missing from the submission.',
//                'missing_question_types' => $missingTypes
//            ], 422);
//        }
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

                // Create dynamic rules: each key must be required and not empty
                $rules = [];
                foreach ($projectRowData as $key => $value) {
                    $rules[$key] = 'required';
                }
// Validate
                $validator = Validator::make($projectRowData, $rules);

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
                // $headerDetails = [];

                $templateDataJson = [];

                foreach ($projectTemplateHeaders as $headId => $projectData) {
                    $headerName = $projectData->template_head_name;

                    if (array_key_exists($headerName, $projectRowData)) {
                        $value = $projectRowData[$headerName];
                    } else {
                        $value = null;
                    }

                    // Store the first non-empty header as reference
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
                    return response()->json(['status' => 401, 'message' => 'activity_id or activity_group_id not found']);
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
            // dd($e->getMessage());
            Log::info('Distributor not added' . $e->getMessage());
            return response()->json(['status' => 401, 'message' => 'Something went wrong']);
        }
    }

    //khushboo 16-05-2025

    private function assignUserActivityAndAuditRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds)
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


}
