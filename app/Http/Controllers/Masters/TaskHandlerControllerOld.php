<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
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

// use http\Client\Curl\User;

class TaskHandlerController extends Controller
{
    // this is to render the projects assigned to the user logged in
    public function userProjects()
    {
        $user = Auth::user();
//        $uniqueUserProjects = AuditorAssignedData::where('user_id', $user->id)
//            ->distinct()
//            ->pluck('project_id');

        //khushboo 05-07-25
        $dataAssignIds = UserActivityDataAssign::where('user_id', $user->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id')->toArray();

        $uniqueUserProjects = DataAssign::whereIn('id', $dataAssignIds)->distinct('project_id')->pluck('project_id')->toArray();
        //khushboo 05-07-25

        $userProjects = Project::whereIn('id', $uniqueUserProjects)->get();

        return view('masters.users.user_projects', compact('userProjects'));
    }

    public function userProjectMasterData(Project $project, TemplateName $template, Activity $activity, ActivityGroup $group_info)
    {

//         dd($project->id, $template->id, $activity->id);
        $startTime = microtime(true);
        $group_session_id = null;
        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            $group_session_id = $group_info->id;
        }
        $row_renderred_arr = [];
        $user = Auth::user();
        $project_data_arr = [];
        $project_data_completed_arr = [];
        $project_temp_info = ProjectTemplate::where('project_id', $project->id)
            ->where('template_name_id', $template->id)
            ->first();
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;

        if ($project_temp_info->is_master == 1) {
            $projectTemplateInfoDetails = $project_temp_info->getTemplate;
            $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
            $add_project_data_title = "Add Distributor";
            // this is to check if the project is assigned to the logged in user or not
//            $checkingIfProjectAssigned = AuditorAssignedData::where('user_id', $user->id)
//                ->where('project_id', $project->id)
//                ->where('template_name_id', $template->id)
//                ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
//                    $query->where('activity_id', $activity->id)
//                        ->where('activity_group_id', $group_session_id);
//                })
//                ->get();

            //khushboo 05-07-25
            $activitySequence = 0;
//            dd($group_info->id);

            $getDataAssignIds = DataAssign::where('project_id', $project->id)
                ->where('template_name_id', $template->id)
                ->where('activity_id', $activity->id)
                ->when($group_session_id, function ($query) use ($group_session_id) {
                    $query->where('activity_group_id', $group_session_id);
                })
                ->distinct('id')
                ->pluck('id');

            $getDataAssignTemplateHeadIds = DataAssign::where('project_id', $project->id)
                ->where('template_name_id', $template->id)
                ->where('activity_id', $activity->id)
                ->when($group_session_id, function ($query) use ($group_session_id) {
                    $query->where('activity_group_id', $group_session_id);
                })
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

            //khushboo 05-07-25

            if ($checkingIfProjectAssigned) {
                $projectTemplateHeadsAssigned = [];
                // this is to get the template heads and values that are assigned to that user
//                foreach ($checkingIfProjectAssigned as $auditor_assiged_data) {
//                    $project_template_id = $auditor_assiged_data->dataAssign->project_template_id;
//                    $headId = $auditor_assiged_data->dataAssign->template_name_head_id;

////                    dd($getHeadValues);
//                    $projectTemplateHeadsAssigned[] = [
//                        'project_template_id' => $project_template_id,
//                        'head_id' => $auditor_assiged_data->dataAssign->template_name_head_id,
//                        'head_value' => $auditor_assiged_data->template_name_head_value, // Add more fields if needed
//                    ];
//                }

                //khushboo 05-07-25
                if (!empty($getDataAssignTemplateHeadIds)) {
                    foreach ($getDataAssignTemplateHeadIds as $headId) {

                        $getHeadValues = DB::table('project_template_name_values_new')
                            ->whereIn('id', $getRowIds)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                            )
                            ->get();

                        $project_templates_data = ProjectTemplate::where('project_id', $project->id)
                            ->where('template_name_id', $template->id)->first();

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

                foreach ($projectTemplateHeadsAssigned as $assigned_value_info) {
                    if (isset($group_info->id)) {
                        $activity_sequence = ActivityGroupPivot::where('activity_id', $activity->id)
                            ->where('activity_group_id', $group_info->id)
                            ->first();
                        if ($activity_sequence) {
                            $cur_sequence = $activity_sequence->sequence;
                            // this is to handle if the activity if the first activity of the group or having the first sequence
                            if ($cur_sequence == 1) {

//                                $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows')->where('project_template_id', $assigned_value_info['project_template_id'])
//                                    ->where('template_name_head_id', $assigned_value_info['head_id'])
//                                    ->where('value', $assigned_value_info['head_value'])->get();

//                                $projectDataArr = DB::table('project_template_name_values_new')
//                                    ->select(
//                                        'id',
//                                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) as head_value")
//                                    )
//                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) = ?", [$assigned_value_info['head_value']])
//                                    ->get();

                                //khushboo 05-07-25
                                $matchedRows = DB::table('project_template_name_values_new')
                                    ->where('project_template_id', $assigned_value_info['project_template_id'])
                                    ->select('id', 'template_data_json')
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) = ?", [$assigned_value_info['head_value']])
                                    ->get();

                                foreach ($matchedRows as $row) {
                                    $jsonData = json_decode($row->template_data_json, true);

                                    foreach ($jsonData as $headKey => $value) {
                                        $headData = TemplateNameHead::find((int)$headKey);

                                        $projectDataArr[] = [
                                            'row_id' => $row->id,
                                            'head_id' => $headKey,
                                            'head_name' => $headData->template_head_name ?? '',
                                            'head_value' => $value,
                                        ];

                                    }
                                }

                                //khushboo 05-07-25

                            } else {
                                // this is to handle if the activity sequence if greater then 1
                                $previous_sequence = $cur_sequence - 1;
                                $get_previous_sequence_activities = ActivityGroupPivot::where('activity_group_id', $group_info->id)
                                    ->where('sequence', $previous_sequence)
                                    ->get();
                                $previous_sequence_answered_rows = [];
                                foreach ($get_previous_sequence_activities as $previous_sequence_activity) {

                                    $rows_in_templates = DB::table('project_template_name_values_new')
                                        ->where('project_template_id', $assigned_value_info['project_template_id'])
                                        ->distinct('id')->pluck('id')->toArray();

//                                    $rows_in_templates = ProjectTemplateNameValue::where('project_template_id', $assigned_value_info['project_template_id'])
//                                        ->distinct('row_id')->pluck('row_id')->toArray();

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
//                                $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows')->where('project_template_id', $assigned_value_info['project_template_id'])
//                                    ->where('template_name_head_id', $assigned_value_info['head_id'])
//                                    ->whereIn('row_id', $previous_sequence_answered_rows)
//                                    ->where('value', $assigned_value_info['head_value'])->get();

                                //khushboo 05-07-25
                                $matchedRows = DB::table('project_template_name_values_new')
                                    ->where('project_template_id', $assigned_value_info['project_template_id'])
                                    ->whereIn('id', $previous_sequence_answered_rows)
                                    ->select('id', 'template_data_json')
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) = ?", [$assigned_value_info['head_value']])
                                    ->get();

                                foreach ($matchedRows as $row) {
                                    $jsonData = json_decode($row->template_data_json, true);

                                    foreach ($jsonData as $headKey => $value) {
                                        $headData = TemplateNameHead::find((int)$headKey);

                                        $projectDataArr[] = [
                                            'row_id' => $row->id,
                                            'head_id' => $headKey,
                                            'head_name' => $headData->template_head_name ?? '',
                                            'head_value' => $value,
                                        ];

                                    }
                                }
                                //khushboo 05-07-25

                            }
                        } else {
                            abort(404); // is to render if the activity if not present in the group
                        }
                    } else {
//                        $projectDataArr = ProjectTemplateNameValue::with('getHeadName', 'getDataOfRows')->where('project_template_id', $assigned_value_info['project_template_id'])
//                            ->where('template_name_head_id', $assigned_value_info['head_id'])
//                            ->where('value', $assigned_value_info['head_value'])->get();

                        //khushboo 05-07-25

//                        dd($assigned_value_info['head_id'], $assigned_value_info['head_value']);

                        $matchedRows = DB::table('project_template_name_values_new')
                            ->where('project_template_id', $assigned_value_info['project_template_id'])
                            ->select('id', 'template_data_json')
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$assigned_value_info['head_id']}\"')) = ?", [$assigned_value_info['head_value']])
                            ->get();

//                        dd($matchedRows);

                        foreach ($matchedRows as $row) {
                            $jsonData = json_decode($row->template_data_json, true);

                            foreach ($jsonData as $headKey => $value) {
                                $headData = TemplateNameHead::find((int)$headKey);

                                $projectDataArr[] = [
                                    'row_id' => $row->id,
                                    'head_id' => $headKey,
                                    'head_name' => $headData->template_head_name ?? '',
                                    'head_value' => $value,
                                ];

                            }
                        }

                        //khushboo 05-07-25

                    }
//                    dd($projectDataArr);
                    foreach ($projectDataArr as $projectData) {
//                        dd($projectData);
                        if (!in_array($projectData['row_id'], $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData['row_id'];
                            $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                ->where('activity_id', $activity->id)->exists();

//                            $get_row_header = ProjectTemplateNameValue::where('row_id', $projectData['row_id'])
//                                ->where('template_name_head_id', $main_header_id)
//                                ->first();

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

//                            $get_row_sub_header = ProjectTemplateNameValue::where('row_id', $projectData['row_id'])
//                                ->where('template_name_head_id', $sub_header_id)
//                                ->first();

                            $sub_header = null;
                            if ($get_row_sub_header_value) {
//                                $sub_header = $get_row_sub_header->value;
                                $sub_header = $get_row_sub_header_value;
                            }
                            $project_data_arr[] = [
                                'data_item' => $projectData,
                                'status' => $check_if_answered ? 'completed' : 'pending',
                                'main_header' => $main_header,
                                'sub_header' => $sub_header
                            ];
                        }
                    }
                }
                // dd($project_data_arr);

                Session::put('project_dist_activity', ['project' => $project->id, 'template' => $template->id, 'activity' => $activity->id, "group_info" => $group_session_id]);
                if (Session::has('project_outlet_activity')) {
                    Session::forget('project_outlet_activity');
                }
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;
                //                \Log::info('Time taken for userProjectData: ' . $executionTime . ' seconds.');
                $currentUser = User::find(Auth::user()->id);
                $currentUserRole = $currentUser->getROleNames()->first();

                return view('masters.users.project_data', compact('project_data_arr', 'project_data_completed_arr', 'project', 'activity', 'group_info', 'project_temp_info', 'projectTemplateInfoDetails', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole'));
                // return view('masters.users.project_data', compact('project_data_arr', 'project_data_completed_arr', 'project', 'activity', 'group_info'));
            } else {
                abort(404);
            }
        }
    }


    public function userProjectChildData1(Project $project, TemplateName $template, Activity $activity, ActivityGroup $group_info)
    {
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
        $distributors_arr = [];
        $outlet_master_info = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first(); // this is to get the info of the master template of the project
        $master_head_id = $project_temp_info->master_head_id;
        $outlet_master_sub_header_id = $outlet_master_info->sub_header;
        $outlet_master_main_header_id = $outlet_master_info->main_header;
        $outlet_master_completion_type = $outlet_master_info->completion_type;
        $outlet_master_min_completion = $outlet_master_info->min_completion;
        $master_head_info = TemplateNameHead::find($master_head_id);
        $master_head_name = $master_head_info->template_head_name;
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
        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {
            $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
                ->where('template_name_id', $project_temp_info->template_name_id)
                ->where('user_id', $user->id)
                ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
                    $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
                })->get();
            foreach ($get_project_template_assigned_data as $get_outlet_assigned) {
                $exists = false;
                $get_rows_of_outlet = ProjectTemplateNameValue::where('project_template_id', $get_outlet_assigned->dataAssign->project_template_id)
                    ->where('template_name_head_id', $get_outlet_assigned->dataAssign->template_name_head_id)
                    ->where('value', $get_outlet_assigned->template_name_head_value)
                    ->distinct()
                    ->get();
                //                    dd($get_outlet_assigned, $get_outlet_assigned->dataAssign->template_name_head_id);
                foreach ($get_rows_of_outlet as $get_row_of_outlet) {
                    $exists = false;
                    // Get the outlet assigned data for the current row
                    $row_data_of_outlet_assigned_data = ProjectTemplateNameValue::where('row_id', $get_row_of_outlet->row_id)
                        ->where('template_name_head_id', $own_head_id)
                        ->first(); // this is giving me the value which is having the reference from the master db
                    if ($row_data_of_outlet_assigned_data) {
                        // Iterate over the distributors array to check if the value already exists
                        foreach ($distributors_arr as $distributor) {
                            if ($distributor['value'] == $row_data_of_outlet_assigned_data->value) {
                                $exists = true;
                                break;
                            }
                        }
                        // If value doesn't exist, process and add it to the distributors array
                        if (!$exists) {
                            $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                                ->where('template_name_head_id', $master_head_id)
                                ->where('value', $row_data_of_outlet_assigned_data->value)
                                ->where('project_template_id', $outlet_master_info->id)
                                ->first();
                            $get_main_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $outlet_master_main_header_id)
                                ->first();
                            $dist_main_header = null;
                            if ($get_main_header_val) {
                                $dist_main_header = $get_main_header_val->value;
                            }
                            $get_sub_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $outlet_master_sub_header_id)
                                ->first();
                            $dist_sub_header = null;
                            if ($get_sub_header_val) {
                                $dist_sub_header = $get_sub_header_val->value;
                            }
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data_of_outlet_assigned_data->value)
                                ->get();
                            $completed_count = 0;
                            // Check if each outlet has been answered
                            foreach ($get_count_of_outlets as $outlet_data) {
                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                    ->where('activity_id', $activity->id)
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
                            // Add the current outlet data to the distributors array
                            $distributors_arr[] = [
                                'value' => $row_data_of_outlet_assigned_data->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $get_master_row->getDataOfRows,
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
            $checkingIfProjectAssigned = AuditorAssignedData::whereHas('dataAssign', function ($query) use ($outlet_master_info) {
                $query->where('is_outlet_assigned', 1);
            })
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->where('template_name_id', $outlet_master_info->template_name_id)
                ->get();
            foreach ($checkingIfProjectAssigned as $distributor_assigned) {
                if ($distributor_assigned->dataAssign->template_name_head_id == $master_head_id) {
                    // means that the distributor heads which is taking reference in the outlet is assigned
                    $rendered_values = array_column($distributors_arr, 'main_header');
                    $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')->where('template_name_head_id', $master_head_id)
                        ->where('value', $distributor_assigned->template_name_head_value)
                        ->where('project_template_id', $outlet_master_info->id)
                        ->first();
                    $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                        ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                        ->get();
                    foreach ($get_headers_of_row as $header_of_row) {
                        if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
                            $dist_sub_header = $header_of_row->value;
                        }
                        if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
                            $dist_main_header = $header_of_row->value;
                        }
                    }
                    if (!(in_array($dist_main_header, $rendered_values))) {
                        $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                            ->where('template_name_head_id', $own_head_id)
                            ->where('value', $get_master_row->value)
                            ->get();
                        $completed_count = 0;
                        if (count($get_count_of_outlets) > 0) {
                            foreach ($get_count_of_outlets as $outlet_data) {
                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                    ->where('activity_id', $activity->id)->exists();
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
                        $distributors_arr[] = [
                            'value' => $distributor_assigned->template_name_head_value,
                            'head_name' => $master_head_name,
                            'getDataOfRows' => $get_master_row->getDataOfRows,
                            'activity_id' => $activity->id,
                            'activity_name' => $activity->activity_name,
                            'template_id' => $template->id,
                            'project_id' => $project->id,
                            'project_template_id' => $project_temp_info->id,
                            'total_outlets' => count($get_count_of_outlets),
                            'total_outlet_answered' => $completed_count,
                            'min_completion' => $min_value,
                            'completion_type' => $completion_type_value,
                            'main_header' => $dist_main_header,
                            'sub_header' => $dist_sub_header,
                        ];
                    }
                } else {
                    // this means that the other head is assigned which is not the head
                    $get_rows_of_master = ProjectTemplateNameValue::where('project_template_id', $distributor_assigned->dataAssign->project_template_id)
                        ->where('template_name_head_id', $distributor_assigned->dataAssign->template_name_head_id)
                        ->where('value', $distributor_assigned->template_name_head_value)
                        ->pluck('row_id');
                    foreach ($get_rows_of_master as $row_of_master) {
                        $master_row_info = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $row_of_master)
                            ->where('template_name_head_id', $master_head_id)
                            ->first();
                        $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $master_row_info->row_id)
                            ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                            ->get();
                        foreach ($get_headers_of_row as $header_of_row) {
                            if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
                                $dist_sub_header = $header_of_row->value;
                            }
                            if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
                                $dist_main_header = $header_of_row->value;
                            }
                        }
                        $rendered_values = array_column($distributors_arr, 'main_header');
                        if (!(in_array($dist_main_header, $rendered_values))) {
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $master_row_info->value)
                                ->get();
                            $completed_count = 0;
                            if (count($get_count_of_outlets) > 0) {
                                foreach ($get_count_of_outlets as $outlet_data) {
                                    $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                        ->where('activity_id', $activity->id)->exists();
                                    if ($check_if_answered) {
                                        $completed_count++;
                                    }
                                }
                            }
                            $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $master_row_info->row_id)
                                ->first();
                            $min_value = $outlet_master_min_completion;
                            $completion_type_value = $outlet_master_completion_type;
                            if ($check_if_distributor_row_setted) {
                                $min_value = $check_if_distributor_row_setted->min_completion;
                                $completion_type_value = $check_if_distributor_row_setted->completion_type;
                            }
                            $distributors_arr[] = [
                                //                                    'value' => $distributor_assigned->template_name_head_value,
                                'value' => $master_row_info->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $master_row_info->getDataOfRows,
                                'activity_id' => $activity->id,
                                'activity_name' => $activity->activity_name,
                                'template_id' => $template->id,
                                'project_id' => $project->id,
                                'project_template_id' => $project_temp_info->id,
                                'total_outlets' => count($get_count_of_outlets),
                                'total_outlet_answered' => $completed_count,
                                'min_completion' => $min_value,
                                'completion_type' => $completion_type_value,
                                'main_header' => $dist_main_header,
                                'sub_header' => $dist_sub_header,
                            ];
                        }
                    }
                }
            }
        } else {
            $previous_sequence = $cur_sequence_helper - 1;
            $rows_in_templates = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
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
            $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
                ->where('template_name_id', $project_temp_info->template_name_id)
                ->where('user_id', $user->id)
                ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
                    $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
                })
                ->get();
            $previously_answered_dist = []; // this will contain all the distributors values which are answered from the previous activity
            foreach ($previous_sequence_answered_rows as $previous_sequence_answered_row) {
                $get_outlet_row = ProjectTemplateNameValue::with('getDataOfRows')
                    ->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                    ->where('template_name_head_id', $master_head_id)
                    ->where('value', $get_outlet_row->value)
                    ->first();
                $outlet_dist_get = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                if (!in_array($get_outlet_row->value, $previously_answered_dist)) {
                    $previously_answered_dist[$get_outlet_row->value] = $get_master_row;
                }
            }
            foreach ($previously_answered_dist as $dist_val => $previously_answered_dist_value) {
                $get_sub_main_header_val = ProjectTemplateNameValue::where('row_id', $previously_answered_dist_value->row_id)
                    ->whereIn('template_name_head_id', [$outlet_master_main_header_id, $outlet_master_sub_header_id])
                    ->get();
                foreach ($get_sub_main_header_val as $headers_info) {
                    if ($headers_info->template_name_head_id == $outlet_master_main_header_id) {
                        $dist_main_header = $headers_info->value;
                    }
                    if ($headers_info->template_name_head_id == $outlet_master_sub_header_id) {
                        $dist_sub_header = $headers_info->value;
                    }
                }
                $outlets_of_distributor = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                    ->where('template_name_head_id', $own_head_id)
                    ->where('value', $dist_val)
                    ->whereIn("row_id", $previous_sequence_answered_rows)
                    ->pluck('row_id');
                $total_outlets = count($outlets_of_distributor); // this will give the count of oulets
                $answered_outlet_counter = 0;
                foreach ($outlets_of_distributor as $outlet_row_id) {
                    $checkIfAnsered = TempUserActivityAnswersData::where('row_id', $outlet_row_id)
                        ->where('activity_group_name_id', $group_info->id)
                        ->where('activity_id', $activity->id)
                        ->exists();
                    if ($checkIfAnsered) {
                        $answered_outlet_counter++;
                    }
                }
                if (!in_array($dist_val, $distributors_arr)) {
                    $distributors_arr[] = [
                        'value' => $dist_val,
                        'head_name' => $master_head_name,
                        'getDataOfRows' => $previously_answered_dist_value->getDataOfRows,
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->activity_name,
                        'template_id' => $template->id,
                        'project_id' => $project->id,
                        'project_template_id' => $project_temp_info->id,
                        'total_outlets' => $total_outlets,
                        'total_outlet_answered' => $answered_outlet_counter,
                        'main_header' => $dist_main_header,
                        'sub_header' => $dist_sub_header,
                        'min_completion' => $outlet_master_min_completion,
                        'completion_type' => $outlet_master_completion_type
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
        return view('masters.users.user_project_outlet_distributors', compact('project', 'template', 'activity', 'paginatedDistributors', 'group_session_id'));
    }

    public function userProjectChildDataOld($type, Project $project, TemplateName $template, Activity $activity, ActivityGroup $group_info)
    {
        // dd($type);
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
        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {

//            dd($type);
            if ($type == 0) {

//                $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
//                    ->where('template_name_id', $project_temp_info->template_name_id)
//                    ->where('user_id', $user->id) //type
//                    ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
//                        $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
//                    })->get();

                //khushboo 05-07-25

                $get_project_assign_data = DataAssign::where('project_id', $project->id)
                    ->where('template_name_id', $project_temp_info->template_name_id)
                    ->where('activity_id', $activity->id)
                    ->when($group_info, function ($query) use ($group_info) {
                        $query->where('activity_group_id', $group_info->id);
                    })->get();


                if ($get_project_assign_data->IsEmpty()) {

                    $getmastertemplate = ProjectTemplate::where('project_id', $project->id)
                        ->where('is_master', 1)->first();

                    $get_project_assign_data = DataAssign::where('project_id', $project->id)
                        ->where('template_name_id', $getmastertemplate->template_name_id)
                        ->where('activity_id', $activity->id)
                        ->where('is_outlet_assigned', 1)
                        ->when($group_info, function ($query) use ($group_info) {
                            $query->where('activity_group_id', $group_info->id);
                        })->get();

                    if (!empty($get_project_assign_data)) {
                        $isOutletChecked = 1;
                    }

                }

                //khushboo 05-07-25

            } else {

//                $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
//                    ->where('template_name_id', $project_temp_info->template_name_id)
//                    ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
//                        $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
//                    })->get();

                $get_project_assign_data = DataAssign::where('project_id', $project->id)
                    ->where('template_name_id', $project_temp_info->template_name_id)
                    ->where('activity_id', $activity->id)
                    ->when($group_info, function ($query) use ($group_info) {
                        $query->where('activity_group_id', $group_info->id);
                    })->get();

                if ($get_project_assign_data->IsEmpty()) {

                    $getmastertemplate = ProjectTemplate::where('project_id', $project->id)
                        ->where('is_master', 1)->first();

                    $get_project_assign_data = DataAssign::where('project_id', $project->id)
                        ->where('template_name_id', $getmastertemplate->template_name_id)
                        ->where('activity_id', $activity->id)
                        ->where('is_outlet_assigned', 1)
                        ->when($group_info, function ($query) use ($group_info) {
                            $query->where('activity_group_id', $group_info->id);
                        })->get();

                    if (!empty($get_project_assign_data)) {
                        $isOutletChecked = 1;
                    }

                }

            }

//            dd($get_project_assign_data);
            $dataAssignedIds = $get_project_assign_data->pluck('id');
            if ($isOutletChecked == 1) {

                $getmastertemplate = ProjectTemplate::where('project_id', $project->id)
                    ->where('is_master', 1)->first();

                $get_project_template_assigned_data = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignedIds)
                    ->where('template_name_id', $getmastertemplate->template_name_id)
                    ->where('activity_id', $activity->id)
                    ->where('is_outlet_assigned', 1)
                    ->when($group_info, function ($query) use ($group_info) {
                        $query->where('activity_group_id', $group_info->id);
                    })->get();


            } else {

                $get_project_template_assigned_data = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignedIds)
                    ->where('template_name_id', $project_temp_info->template_name_id)
                    ->where('activity_id', $activity->id)
                    ->where('is_outlet_assigned', 1)
                    ->when($group_info, function ($query) use ($group_info) {
                        $query->where('activity_group_id', $group_info->id);
                    })->get();

            }

            // dd($get_project_template_assigned_data);

            foreach ($get_project_template_assigned_data as $get_outlet_assigned) {

                //khushboo 17-04-25
                if ($get_outlet_assigned->audit_closed && $type == 0) {
                    $isAuditClosed = true;
                }
                //khushboo 17-04-25

                $exists = false;
                $get_rows_of_outlet = ProjectTemplateNameValue::where('project_template_id', $get_outlet_assigned->dataAssign->project_template_id)
                    ->where('template_name_head_id', $get_outlet_assigned->dataAssign->template_name_head_id)
                    ->where('value', $get_outlet_assigned->template_name_head_value)
                    ->distinct()
                    ->get();

                foreach ($get_rows_of_outlet as $get_row_of_outlet) {
                    $exists = false;
                    // Get the outlet assigned data for the current row
                    $row_data_of_outlet_assigned_data = ProjectTemplateNameValue::where('row_id', $get_row_of_outlet->row_id)
                        ->where('template_name_head_id', $own_head_id)
                        ->first(); // this is giving me the value which is having the reference from the master db
                    if ($row_data_of_outlet_assigned_data) {

                        $rendered_values = array_column($distributors_arr, 'value');
                        if ((in_array($row_data_of_outlet_assigned_data->value, $rendered_values))) {
                            $exists = true;
                        }
                        if (!$exists) {
                            // $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                            //     ->where('template_name_head_id', $master_head_id)
                            //     ->where('value', $row_data_of_outlet_assigned_data->value)
                            //     ->where('project_template_id', $outlet_master_info->id)
                            //     ->first();

                            $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                                ->where('template_name_head_id', $own_head_id) //$master_head_id
                                ->where('value', $row_data_of_outlet_assigned_data->value)
                                ->where('project_template_id', $project_temp_info->id)  //$outlet_master_info
                                ->first();

                            $get_main_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $outlet_master_main_header_id)
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
                                    'row_id' => $get_master_row->row_id
                                ];
                                continue;
                            }
                            $get_sub_header_val = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $outlet_master_sub_header_id)
                                ->first();
                            $dist_sub_header = null;
                            if ($get_sub_header_val) {
                                $dist_sub_header = $get_sub_header_val->value;
                            }
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data_of_outlet_assigned_data->value)
                                ->get();
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
                            $completed_count = 0;
                            // Check if each outlet has been answered
                            foreach ($get_count_of_outlets as $outlet_data) {
                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                    ->where('activity_id', $activity->id)
                                    ->exists();
                                if ($check_if_answered) {
                                    $completed_count++;
                                }
                            }
                            $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)
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
                                'getDataOfRows' => $get_master_row->getDataOfRows,
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
            if ($type == 0) {
                $checkingIfProjectAssigned = AuditorAssignedData::whereHas('dataAssign', function ($query) use ($outlet_master_info) {
                    $query->where('is_outlet_assigned', 1);
                })
                    ->where('project_id', $project->id)
                    ->where('user_id', $user->id) //type
                    ->where('template_name_id', $outlet_master_info->template_name_id)
                    ->get();
            } else {

                $checkingIfProjectAssigned = AuditorAssignedData::whereHas('dataAssign', function ($query) use ($outlet_master_info) {
                    $query->where('is_outlet_assigned', 1);
                })
                    ->where('project_id', $project->id)
                    ->where('template_name_id', $outlet_master_info->template_name_id)
                    ->get();
            }

            // dd($checkingIfProjectAssigned);
            foreach ($checkingIfProjectAssigned as $distributor_assigned) {
                //khushboo 17-04-25
                if ($distributor_assigned->audit_closed && $type == 0) {
                    $isAuditClosed = true;
                }
                //khushboo 17-04-25
                if ($distributor_assigned->dataAssign->template_name_head_id == $master_head_id) {
                    // means that the distributor heads which is taking reference in the outlet is assigned
                    $rendered_values = array_column($distributors_arr, 'main_header');
                    $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')->where('template_name_head_id', $master_head_id)
                        ->where('value', $distributor_assigned->template_name_head_value)
                        ->where('project_template_id', $outlet_master_info->id)
                        ->first();
                    $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
                        ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                        ->get();
                    foreach ($get_headers_of_row as $header_of_row) {
                        if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
                            $dist_main_header = $header_of_row->value;
                        }
                        if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
                            $dist_sub_header = $header_of_row->value;
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
                        $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                            ->where('template_name_head_id', $own_head_id)
                            ->where('value', $get_master_row->value)
                            ->get();

                        if ($get_count_of_outlets->IsEmpty()) {
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                                ->where('value', $get_master_row->value)
                                ->get();
                        }
                        $completed_count = 0;
                        if (count($get_count_of_outlets) > 0) {
                            foreach ($get_count_of_outlets as $outlet_data) {
                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                    ->where('activity_id', $activity->id)->exists();
                                if ($check_if_answered) {
                                    $completed_count++;
                                }
                            }
                        }
                        $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)
                            ->first();
                        // $min_value = $outlet_master_min_completion;
                        // $completion_type_value = $outlet_master_completion_type;
                        $min_value = 100;
                        $completion_type_value = '';
                        if ($check_if_distributor_row_setted) {
                            $min_value = $check_if_distributor_row_setted->min_completion;
                            $completion_type_value = $check_if_distributor_row_setted->completion_type;
                        }
                        $distributors_arr[] = [
                            'value' => $distributor_assigned->template_name_head_value,
                            'head_name' => $master_head_name,
                            'getDataOfRows' => $get_master_row->getDataOfRows,
                            'activity_id' => $activity->id,
                            'activity_name' => $activity->activity_name,
                            'template_id' => $template->id,
                            'project_id' => $project->id,
                            'project_template_id' => $project_temp_info->id,
                            'total_outlets' => count($get_count_of_outlets),
                            'total_outlet_answered' => $completed_count,
                            'min_completion' => $min_value,
                            'completion_type' => $completion_type_value,
                            'main_header' => $dist_main_header,
                            'sub_header' => $dist_sub_header,
                        ];
                    }
                } else {
                    // this means that the other head is assigned which is not the head
                    $get_rows_of_master = ProjectTemplateNameValue::where('project_template_id', $distributor_assigned->dataAssign->project_template_id)
                        ->where('template_name_head_id', $distributor_assigned->dataAssign->template_name_head_id)
                        ->where('value', $distributor_assigned->template_name_head_value)
                        ->pluck('row_id');
                    foreach ($get_rows_of_master as $row_of_master) {
                        $master_row_info = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $row_of_master)
                            ->where('template_name_head_id', $master_head_id)
                            ->first();
                        $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $master_row_info->row_id)
                            ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
                            ->get();
                        foreach ($get_headers_of_row as $header_of_row) {
                            if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
                                $dist_sub_header = $header_of_row->value;
                            }
                            if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
                                $dist_main_header = $header_of_row->value;
                            }
                        }
                        $rendered_values = array_column($distributors_arr, 'main_header');
                        if (!(in_array($dist_main_header, $rendered_values))) {
                            $dist_info = count($distributors_arr);
                            if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
                                $distributors_arr[] = [
                                    'value' => $master_row_info->value,
                                    'main_header' => $dist_main_header,
                                    'row_id' => $master_row_info->row_id
                                ];
                                continue;
                            }
                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $master_row_info->value)
                                ->get();
                            $completed_count = 0;
                            if ($get_count_of_outlets->IsEmpty()) {
                                $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                                    ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
                                    ->where('value', $master_row_info->value)
                                    ->get();
                            }
                            if (count($get_count_of_outlets) > 0) {
                                foreach ($get_count_of_outlets as $outlet_data) {
                                    $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
                                        ->where('activity_id', $activity->id)->exists();
                                    if ($check_if_answered) {
                                        $completed_count++;
                                    }
                                }
                            }
                            $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $master_row_info->row_id)
                                ->first();
                            // $min_value = $outlet_master_min_completion;
                            // $completion_type_value = $outlet_master_completion_type;
                            $min_value = 100;
                            $completion_type_value = '';
                            if ($check_if_distributor_row_setted) {
                                $min_value = $check_if_distributor_row_setted->min_completion;
                                $completion_type_value = $check_if_distributor_row_setted->completion_type;
                            }
                            $distributors_arr[] = [
                                // 'value' => $distributor_assigned->template_name_head_value,
                                'value' => $master_row_info->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $master_row_info->getDataOfRows,
                                'activity_id' => $activity->id,
                                'activity_name' => $activity->activity_name,
                                'template_id' => $template->id,
                                'project_id' => $project->id,
                                'project_template_id' => $project_temp_info->id,
                                'total_outlets' => count($get_count_of_outlets),
                                'total_outlet_answered' => $completed_count,
                                'min_completion' => $min_value,
                                'completion_type' => $completion_type_value,
                                'main_header' => $dist_main_header,
                                'sub_header' => $dist_sub_header,
                            ];
                        }
                    }
                }
            }
        } else {
            $previous_sequence = $cur_sequence_helper - 1;
            $rows_in_templates = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
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
            if ($type == 0) {
                $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
                    ->where('template_name_id', $project_temp_info->template_name_id)
                    ->where('user_id', $user->id) //type
                    ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
                        $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
                    })
                    ->get();
            } else {
                $get_project_template_assigned_data = AuditorAssignedData::where('project_id', $project->id)
                    ->where('template_name_id', $project_temp_info->template_name_id)
                    ->whereHas('dataAssign', function ($query) use ($project, $activity, $group_session_id) {
                        $query->where('activity_id', $activity->id)->where('activity_group_id', $group_session_id);
                    })
                    ->get();
            }

            // dd($get_project_template_assigned_data);
            //khushboo 17-04-25
            if ($get_project_template_assigned_data[0]->audit_closed && $type == 0) {
                $isAuditClosed = true;
            }
            //khushboo 17-04-25

            $previously_answered_dist = []; // this will contain all the distributors values which are answered from the previous activity
            foreach ($previous_sequence_answered_rows as $previous_sequence_answered_row) {
                $get_outlet_row = ProjectTemplateNameValue::with('getDataOfRows')
                    ->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')
                    ->where('template_name_head_id', $master_head_id)
                    ->where('value', $get_outlet_row->value)
                    ->first();
                $outlet_dist_get = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $previous_sequence_answered_row)
                    ->where('template_name_head_id', $own_head_id)
                    ->first();
                if (!in_array($get_outlet_row->value, $previously_answered_dist)) {
                    $previously_answered_dist[$get_outlet_row->value] = $get_master_row;
                }
            }
            foreach ($previously_answered_dist as $dist_val => $previously_answered_dist_value) {
                $get_sub_main_header_val = ProjectTemplateNameValue::where('row_id', $previously_answered_dist_value->row_id)
                    ->whereIn('template_name_head_id', [$outlet_master_main_header_id, $outlet_master_sub_header_id])
                    ->get();
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
                    $outlets_of_distributor = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
                        ->where('template_name_head_id', $own_head_id)
                        ->where('value', $dist_val)
                        ->whereIn("row_id", $previous_sequence_answered_rows)
                        ->pluck('row_id');
                    $total_outlets = count($outlets_of_distributor); // this will give the count of oulets
                    $answered_outlet_counter = 0;
                    foreach ($outlets_of_distributor as $outlet_row_id) {
                        $checkIfAnsered = TempUserActivityAnswersData::where('row_id', $outlet_row_id)
                            ->where('activity_group_name_id', $group_info->id)
                            ->where('activity_id', $activity->id)
                            ->exists();
                        if ($checkIfAnsered) {
                            $answered_outlet_counter++;
                        }
                    }
                    $distributors_arr[] = [
                        'value' => $dist_val,
                        'head_name' => $master_head_name,
                        'getDataOfRows' => $previously_answered_dist_value->getDataOfRows,
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->activity_name,
                        'template_id' => $template->id,
                        'project_id' => $project->id,
                        'project_template_id' => $project_temp_info->id,
                        'total_outlets' => $total_outlets,
                        'total_outlet_answered' => $answered_outlet_counter,
                        'main_header' => $dist_main_header,
                        'sub_header' => $dist_sub_header,
                        'min_completion' => $outlet_master_min_completion,
                        'completion_type' => $outlet_master_completion_type
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
                        (int)$outlet_master_min_completion
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

//        dd($distributors_arr);
//        if ($type == 0) {
//            $checkingIfProjectAssigned = AuditorAssignedData::whereHas('dataAssign', function ($query) use ($outlet_master_info) {
//                $query->where('is_outlet_assigned', 1);
//            })
//                ->where('project_id', $project->id)
//                ->where('user_id', $user->id) //type
//                ->where('template_name_id', $outlet_master_info->template_name_id)
//                ->get();
//        } else {
//
//            $checkingIfProjectAssigned = AuditorAssignedData::whereHas('dataAssign', function ($query) use ($outlet_master_info) {
//                $query->where('is_outlet_assigned', 1);
//            })
//                ->where('project_id', $project->id)
//                ->where('template_name_id', $outlet_master_info->template_name_id)
//                ->get();
//        }

        // dd($checkingIfProjectAssigned);
//        foreach ($checkingIfProjectAssigned as $distributor_assigned) {
//            //khushboo 17-04-25
//            if ($distributor_assigned->audit_closed && $type == 0) {
//                $isAuditClosed = true;
//            }
//            //khushboo 17-04-25
//            if ($distributor_assigned->dataAssign->template_name_head_id == $master_head_id) {
//                // means that the distributor heads which is taking reference in the outlet is assigned
//                $rendered_values = array_column($distributors_arr, 'main_header');
//                $get_master_row = ProjectTemplateNameValue::with('getDataOfRows')->where('template_name_head_id', $master_head_id)
//                    ->where('value', $distributor_assigned->template_name_head_value)
//                    ->where('project_template_id', $outlet_master_info->id)
//                    ->first();
//                $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $get_master_row->row_id)
//                    ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
//                    ->get();
//                foreach ($get_headers_of_row as $header_of_row) {
//                    if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
//                        $dist_main_header = $header_of_row->value;
//                    }
//                    if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
//                        $dist_sub_header = $header_of_row->value;
//                    }
//                }
//                if (!(in_array($dist_main_header, $rendered_values))) {
//
//                    $dist_info = count($distributors_arr);
//                    if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
//                        $distributors_arr[] = [
//                            'value' => $row_data_of_outlet_assigned_data->value,
//                            'main_header' => $dist_main_header,
//                            'row_id' => $get_master_row->row_id
//                        ];
//                        continue;
//                    }
//                    $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
//                        ->where('template_name_head_id', $own_head_id)
//                        ->where('value', $get_master_row->value)
//                        ->get();
//
//                    if ($get_count_of_outlets->IsEmpty()) {
//                        $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
//                            ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
//                            ->where('value', $get_master_row->value)
//                            ->get();
//                    }
//                    $completed_count = 0;
//                    if (count($get_count_of_outlets) > 0) {
//                        foreach ($get_count_of_outlets as $outlet_data) {
//                            $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
//                                ->where('activity_id', $activity->id)->exists();
//                            if ($check_if_answered) {
//                                $completed_count++;
//                            }
//                        }
//                    }
//                    $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $get_master_row->row_id)
//                        ->first();
//                    // $min_value = $outlet_master_min_completion;
//                    // $completion_type_value = $outlet_master_completion_type;
//                    $min_value = 100;
//                    $completion_type_value = '';
//                    if ($check_if_distributor_row_setted) {
//                        $min_value = $check_if_distributor_row_setted->min_completion;
//                        $completion_type_value = $check_if_distributor_row_setted->completion_type;
//                    }
//                    $distributors_arr[] = [
//                        'value' => $distributor_assigned->template_name_head_value,
//                        'head_name' => $master_head_name,
//                        'getDataOfRows' => $get_master_row->getDataOfRows,
//                        'activity_id' => $activity->id,
//                        'activity_name' => $activity->activity_name,
//                        'template_id' => $template->id,
//                        'project_id' => $project->id,
//                        'project_template_id' => $project_temp_info->id,
//                        'total_outlets' => count($get_count_of_outlets),
//                        'total_outlet_answered' => $completed_count,
//                        'min_completion' => $min_value,
//                        'completion_type' => $completion_type_value,
//                        'main_header' => $dist_main_header,
//                        'sub_header' => $dist_sub_header,
//                    ];
//                }
//            } else {
//                // this means that the other head is assigned which is not the head
//                $get_rows_of_master = ProjectTemplateNameValue::where('project_template_id', $distributor_assigned->dataAssign->project_template_id)
//                    ->where('template_name_head_id', $distributor_assigned->dataAssign->template_name_head_id)
//                    ->where('value', $distributor_assigned->template_name_head_value)
//                    ->pluck('row_id');
//                foreach ($get_rows_of_master as $row_of_master) {
//                    $master_row_info = ProjectTemplateNameValue::with('getDataOfRows')->where('row_id', $row_of_master)
//                        ->where('template_name_head_id', $master_head_id)
//                        ->first();
//                    $get_headers_of_row = ProjectTemplateNameValue::where('row_id', $master_row_info->row_id)
//                        ->whereIn('template_name_head_id', [$outlet_master_sub_header_id, $outlet_master_main_header_id])
//                        ->get();
//                    foreach ($get_headers_of_row as $header_of_row) {
//                        if ($header_of_row->template_name_head_id == $outlet_master_sub_header_id) {
//                            $dist_sub_header = $header_of_row->value;
//                        }
//                        if ($header_of_row->template_name_head_id == $outlet_master_main_header_id) {
//                            $dist_main_header = $header_of_row->value;
//                        }
//                    }
//                    $rendered_values = array_column($distributors_arr, 'main_header');
//                    if (!(in_array($dist_main_header, $rendered_values))) {
//                        $dist_info = count($distributors_arr);
//                        if (!($dist_info >= $startIndex && $dist_info <= $endIndex)) {
//                            $distributors_arr[] = [
//                                'value' => $master_row_info->value,
//                                'main_header' => $dist_main_header,
//                                'row_id' => $master_row_info->row_id
//                            ];
//                            continue;
//                        }
//                        $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
//                            ->where('template_name_head_id', $own_head_id)
//                            ->where('value', $master_row_info->value)
//                            ->get();
//                        $completed_count = 0;
//                        if ($get_count_of_outlets->IsEmpty()) {
//                            $get_count_of_outlets = ProjectTemplateNameValue::where('project_template_id', $project_temp_info->id)
//                                ->where('template_name_head_id', $project_temp_info->sub_header)  //$own_head_id
//                                ->where('value', $master_row_info->value)
//                                ->get();
//                        }
//                        if (count($get_count_of_outlets) > 0) {
//                            foreach ($get_count_of_outlets as $outlet_data) {
//                                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_data->row_id)
//                                    ->where('activity_id', $activity->id)->exists();
//                                if ($check_if_answered) {
//                                    $completed_count++;
//                                }
//                            }
//                        }
//                        $check_if_distributor_row_setted = ProjectMasterTemplates::where('row_id', $master_row_info->row_id)
//                            ->first();
//                        // $min_value = $outlet_master_min_completion;
//                        // $completion_type_value = $outlet_master_completion_type;
//                        $min_value = 100;
//                        $completion_type_value = '';
//                        if ($check_if_distributor_row_setted) {
//                            $min_value = $check_if_distributor_row_setted->min_completion;
//                            $completion_type_value = $check_if_distributor_row_setted->completion_type;
//                        }
//                        $distributors_arr[] = [
//                            // 'value' => $distributor_assigned->template_name_head_value,
//                            'value' => $master_row_info->value,
//                            'head_name' => $master_head_name,
//                            'getDataOfRows' => $master_row_info->getDataOfRows,
//                            'activity_id' => $activity->id,
//                            'activity_name' => $activity->activity_name,
//                            'template_id' => $template->id,
//                            'project_id' => $project->id,
//                            'project_template_id' => $project_temp_info->id,
//                            'total_outlets' => count($get_count_of_outlets),
//                            'total_outlet_answered' => $completed_count,
//                            'min_completion' => $min_value,
//                            'completion_type' => $completion_type_value,
//                            'main_header' => $dist_main_header,
//                            'sub_header' => $dist_sub_header,
//                        ];
//                    }
//                }
//            }
//        }
//        }

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
                        (int)$outlet_master_min_completion
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

    public function userProjectActivities(Project $project, TemplateName $template)
    {
        $user = Auth::user();
        $userAssignedActivities = [];
        $project_master_template = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 1)
            ->first();
        $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')->where('project_id', $project->id)
            ->where('id', '!=', $project_master_template->id)
            ->get();

//        $distinct_data_assignIds = AuditorAssignedData::where('project_id', $project->id)
//            ->distinct('data_assign_id')
//            ->where('user_id', $user->id)
//            ->pluck('data_assign_id');

//        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')->whereIn("id", $distinct_data_assignIds)
//            ->get();

//        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')

        //khushboo 05-07-25
        $distinct_data_assignIds = UserActivityDataAssign::where('user_id', $user->id)
            ->distinct('data_assign_id')
            ->pluck('data_assign_id');

        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
            ->whereIn("id", $distinct_data_assignIds)
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
                                    'group_id' => $group_info->id,
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
//        dd($userAssignedActivities);
        $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();
        return view('masters.users.project_activities', compact('userAssignedActivities', 'project'));
    }

    public function userProjectActivities2(Project $project, TemplateName $template)
    {
        $user = Auth::user();
        $show_outlets = '';
        $check_project_templates = ProjectTemplate::with('getTemplate')->where('project_id', $project->id)->get();
        // this is to check if the project having only one template name (database)
        if (count($check_project_templates) == 1) {
            $projectTemplateInfo = $check_project_templates->first();
            $show_outlets = false;
            // this is to check if the project and template is mapped by a single activity
            if ($projectTemplateInfo->activityType == 0) {
                $is_master_temp = 0;
                if ($projectTemplateInfo->is_master == 1) {
                    $is_master_temp = 1;
                }
                $isAssigned = AuditorAssignedData::where('user_id', $user->id)
                    ->where('project_id', $project->id)
                    ->whereHas('dataAssign', function ($query) use ($projectTemplateInfo) {
                        $query->where('activity_id', $projectTemplateInfo->activity_group_name_id_or_activity_id);
                    })
                    ->exists();
                $activity_info = Activity::find($projectTemplateInfo->activity_group_name_id_or_activity_id);
                $userAssignedActivities[] = [
                    'template_name_id' => $projectTemplateInfo->template_name_id,
                    'template_name' => $projectTemplateInfo->getTemplate->template_name,
                    'is_master' => $is_master_temp,
                    'activity_id' => $activity_info->id,
                    'activity_name' => $activity_info->activity_name,
                    'group_id' => null,
                    'group_name' => null,
                    'sequence' => null
                ];
                if ($isAssigned) {
                    $group_act = "0";
                    return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'group_act', 'show_outlets'));
                    //                    return redirect(route('user.project.data', ['project' => $project->id, 'activity' => $projectTemplateInfo->activity_group_name_id_or_activity_id]));
                } else {
                    abort(404);
                }
            } // this is to check if the project and template is mapped by a group activity
            else {
                $userAssignedActivities = [];
                $group_act = "1";
                $show_outlets = false;
                $is_master_temp = 0;
                if ($projectTemplateInfo->is_master == 1) {
                    $is_master_temp = 1;
                }
                $activity_group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->where('id', $projectTemplateInfo->activity_group_name_id_or_activity_id)->first();
                foreach ($activity_group_info->get_group_activities as $group_activity_info) {
                    $isAssigned = AuditorAssignedData::where('user_id', $user->id)
                        ->where('project_id', $project->id)
                        ->where('template_name_id', $projectTemplateInfo->template_name_id)
                        ->whereHas('dataAssign', function ($query) use ($group_activity_info) {
                            $query->where('activity_id', $group_activity_info->activity_id)
                                ->where('activity_group_id', $group_activity_info->activity_group_id);
                        })->exists();
                    if ($isAssigned) {
                        $userAssignedActivities[] = [
                            'template_name_id' => $projectTemplateInfo->template_name_id,
                            'template_name' => $projectTemplateInfo->getTemplate->template_name,
                            'is_master' => $is_master_temp,
                            'activity_id' => $group_activity_info->activity_id,
                            'activity_name' => $group_activity_info->getActivityInfo->activity_name,
                            'group_id' => $group_activity_info->activity_group_id,
                            'group_name' => $activity_group_info->activity_group_name,
                            'sequence' => $group_activity_info->sequence
                        ];
                    }
                }
                return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'group_act', 'show_outlets'));
            }
        } // this else condition will run if there are more then one template(database) against the project
        else {
            $group_act = 1;
            $project_templates = ProjectTemplate::with('getTemplate')->where('project_id', $project->id)
                //                ->where('is_master', 1)
                ->orderBy('is_master', 'desc')
                ->get();
            foreach ($project_templates as $project_template) {
                $show_outlets = true; // this is to show the view outlet option
                $activity_id = null;
                $is_master_temp = 0;
                if ($project_template->is_master) {
                    $is_master_temp = 1;
                }
                $activity_group_id = null;
                if ($project_template->activityType == 0) {
                    if ($project_template->is_master == 1) {
                        $is_master_temp = 1;
                    }
                    $activity_info = Activity::find($project_template->activity_group_name_id_or_activity_id);
                    // before insert the activity in the user assigned activities check if this
                    // activity is assigned to the logged in user of not
                    $check_assigned = DataAssign::where('project_id', $project->id)
                        ->where('project_template_id', $project_template->id)
                        ->where('template_name_id', $project_template->template_name_id)
                        ->where('activity_id', $activity_info->id)
                        ->where('activity_group_id', $activity_group_id)
                        ->get();
                    if ($check_assigned) {
                        $userAssignedActivities[] = [
                            'template_name_id' => $project_template->template_name_id,
                            'template_name' => $project_template->getTemplate->template_name,
                            'is_master' => $is_master_temp,
                            'activity_id' => $activity_info->id,
                            'activity_name' => $activity_info->activity_name,
                            'group_id' => null,
                            'group_name' => null,
                            'sequence' => null
                        ];
                    }
                    $group_act = 0;
                } else {
                    // this is to get the group activities of the project template
                    $group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->where('id', $project_template->activity_group_name_id_or_activity_id)->first();
                    foreach ($group_info->get_group_activities as $group_activity) {
                        $check_assigned = DataAssign::where('project_id', $project->id)
                            ->where('project_template_id', $project_template->id)
                            ->where('template_name_id', $project_template->template_name_id)
                            ->where('activity_id', $group_activity->activity_id)
                            ->where('activity_group_id', $group_info->id)
                            ->exists();
                        if ($check_assigned) {
                            $userAssignedActivities[] = [
                                'template_name_id' => $project_template->template_name_id,
                                'template_name' => $project_template->getTemplate->template_name,
                                'is_master' => $is_master_temp,
                                'activity_id' => $group_activity->activity_id,
                                'activity_name' => $group_activity->getActivityInfo->activity_name,
                                'group_id' => $group_info->id,
                                'group_name' => $group_info->activity_group_name,
                                'sequence' => $group_activity->sequence
                            ];
                        }
                    }
                }
            }
            return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'group_act', 'show_outlets'));
            return redirect(route('user.project.data', ['project' => $project->id, 'activity' => $activity_id, 'group_info' => $activity_group_id]));
        }
    }

    // this is to render the questions of the activity and take the answers from the user against the row ID
    public function row_data_activity($row_id, Activity $activity, ActivityGroup $group_info)
    {
        $row_data = ProjectTemplateNameValuesNew::find($row_id);
        $getjsondata = json_decode($row_data->template_data_json);
        $template_name_values = [];
        foreach ($getjsondata as $key =>  $data){
            $data = TemplateNameHead::find($key);
            $value = $data;
            $template_name_values[] = [
                'template_head_name' => $data->template_head_name,
                'value' => $data
            ];
        }
//        dd($template_name_values);
        $related_questions = Question::with(['getOptions', 'getSubjects'])->where('activity_id', $activity->id)->orderBy('question_sequence')->get();
        return view('masters.users.activity_questions', compact('row_data', 'related_questions', 'group_info', 'template_name_values'));
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

    public function row_activity_answers(Request $request)
    {
        $userId = Auth::id();
        $last_squence = TempUserActivityAnswersData::max('same_answer_id'); // this is to get the max same answer id
        $last_squence += 1;
        $row_id = $request->row_id;
        $group_id = $request->group_id ? $request->group_id : null;
        $activity_id = $request->activity_id;
        $baseDirectory = 'activityAnswerImages/'; // this is the base directory where all the project directories will be kept
        $projectTemp = ProjectTemplateNameValue::where('row_id', $row_id)->first();
        $projectName = $projectTemp->getProjectTemplate->getProject->project_name; // this will give the name of the project
        $projectDirectory = $baseDirectory . $projectName . '/';
        $this->checkAndCreateDirectory($projectDirectory);
        // Get the current month and year
        $currentMonthYear = Carbon::now()->format('FY');
        // Create directory for the current month and year if it doesn't exist
        $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
        $this->checkAndCreateDirectory($projectMonthYearDirectory);
        $requestData = $request->except(['_token', 'row_id', 'activity_id', 'save', 'group_id']);
        foreach ($requestData as $key => $user_answer) {
            $check_questionType = Question::find($key);
            if (is_array($user_answer)) {
                $user_answer = implode(',', $user_answer);
            }
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                $file->move(public_path($projectMonthYearDirectory), $file_name);
                $user_answer = $full_file_path;
            }
            if (strpos($user_answer, 'data:audio/') === 0) {
                // It's audio data
                $audioData = $user_answer;
                $audioBlob = base64_decode(explode(',', $audioData)[1]);
                $audioFileName = uniqid() . '.wav';
                $audioFilePath = $projectMonthYearDirectory . '/' . $audioFileName;
                // Save the audio file
                file_put_contents(public_path($audioFilePath), $audioBlob);
                // Set user_answer to the file path
                $user_answer = $audioFilePath;
            }
            if ($check_questionType->question_type == "Date") {
                if (isset($user_answer)) {
                    $user_answer = \Carbon\Carbon::parse($user_answer)->format('d/m/Y');
                }
            }
            // if ($check_questionType->question_type == "Video") {
            //     if ($request->hasFile($key)) {
            //         $file = $request->file($key);

            //         // Even if extension is .temp, treat it as a video and rename it with proper video extension
            //         $originalExtension = $file->getClientOriginalExtension();

            //         // If the file has .temp or no extension, assume it's a video and use .mp4
            //         if ($originalExtension === 'temp' || $originalExtension === '') {
            //             $newExtension = 'mp4'; // default to mp4
            //         } else {
            //             $newExtension = $originalExtension;
            //         }
            //         // Generate a new filename
            //         $file_name = uniqid() . '.' . $newExtension;
            //         $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
            //         // Move the uploaded file to the desired location
            //         $file->move(public_path($projectMonthYearDirectory), $file_name);
            //         // Set the path for further processing or saving to DB
            //         $user_answer = $full_file_path;
            //     }
            // }

            TempUserActivityAnswersData::create([
                'user_id' => $userId,
                'row_id' => $row_id,
                'activity_id' => $activity_id,
                'activity_group_name_id' => $group_id,
                'question_id' => $key,
                'user_answer' => $user_answer,
                'same_answer_id' => $last_squence
            ]);
        }
        $message = "Activity Answer submitted successfully";
        if (Session::has('project_outlet_activity')) {
            $params = Session::get('project_outlet_activity');
            return redirect(route('user.project.distributor.outlets', $params))->with('message', $message);
        }
        if (Session::has('project_dist_activity')) {
            $params = Session::get('project_dist_activity');
            return redirect(route('user.project_master.data', $params))->with('message', $message);
        }
    }


    public function userProjectActivityDistributorOutletData(
        ProjectTemplate $projectTemplate,
        Activity        $activity,
                        $distributor_value,
                        $type,
        ActivityGroup   $group_info
    )
    {
        $project_temp_info = $projectTemplate;
        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        $add_project_data_title = "Add Outlet";
        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;
        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;
        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            $group_session_id = $group_info->id;
            $activityGroupInfo = ActivityGroupPivot::where('activity_id', $activity->id)
                ->where('activity_group_id', $group_info->id)
                ->first();
            $cur_sequence_helper = isset($activityGroupInfo->sequence) ? $activityGroupInfo->sequence : 0;
            if ($cur_sequence_helper > 1) {
                $is_to_check_previous_submitted = 1;
                $previous_sequence = $cur_sequence_helper - 1;
                $rows_in_templates = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
                    ->pluck('id')->toArray(); // these are the row_id under this project template
                // this is to get all the activities of the group of the particular group (current sequence - 1)
                $get_previous_sequence_activities = ActivityGroupPivot::where('activity_group_id', $group_info->id)
                    ->where('sequence', $previous_sequence)
                    ->get();
                // the below will return as the row_id which are answered in the previous sequence
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
        if ($is_to_check_previous_submitted) {
            $outlet_items = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
                ->whereIn('id', $previous_sequence_answered_rows)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->own_reference_head_id\"')) = ?", [trim($distributor_value)])
                ->get();
        } else {
            $outlet_items = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->own_reference_head_id\"')) = ?", [trim($distributor_value)])
                ->get();
        }

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;
                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                    ->where('activity_id', $activity->id)->exists();

                $main_header_info = ProjectTemplateNameValuesNew::where('id', $outlet_item->id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$outlet_main_header_id}\"')) as value")
                    )
                    ->first();

                $sub_header_info = ProjectTemplateNameValuesNew::where('id', $outlet_item->id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$outlet_sub_header_id}\"')) as value")
                    )
                    ->first();

                $main_header = $main_header_info->value ?? null;
                $sub_header = $sub_header_info->value ?? null;

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $check_if_answered ? 'completed' : 'pending',
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                ];
            }
        }

        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'activity' => $activity->id, 'distributor_value' => $distributor_value, 'type' => $type, "group_info" => $group_session_id]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();

        return view('masters.users.project_data', compact('project_data_arr', 'project', 'activity', 'group_info', 'project_temp_info', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole'));
        // return view('masters.users.project_data', compact('project_data_arr', 'project', 'activity', 'group_info'));
    }


    public function userProjectActivityDistributorOutletDataOld(
        ProjectTemplate $projectTemplate,
        Activity        $activity,
                        $distributor_value,
                        $type,
        ActivityGroup   $group_info
    )
    {
        $project_temp_info = $projectTemplate;
        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        $add_project_data_title = "Add Outlet";
        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;
        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;
        if (!isset($group_info->id)) {
            $group_info = null;
        } else {
            $group_session_id = $group_info->id;
            $activityGroupInfo = ActivityGroupPivot::where('activity_id', $activity->id)
                ->where('activity_group_id', $group_info->id)
                ->first();
            $cur_sequence_helper = $activityGroupInfo->sequence;
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
        if ($is_to_check_previous_submitted) {
            $outlet_items = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
                ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
                ->where('value', $distributor_value)
                ->whereIn('row_id', $previous_sequence_answered_rows)
                ->get();
        } else {
            $outlet_items = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
                ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
                ->where('value', $distributor_value)
                ->get();
        }
        if ($outlet_items->IsEmpty()) {
            $outlet_items = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
                ->where('template_name_head_id', $projectTemplate->sub_header)
                ->where('value', $distributor_value)
                ->get();
        }

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
                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $check_if_answered ? 'completed' : 'pending',
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                ];
            }
        }
        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'activity' => $activity->id, 'distributor_value' => $distributor_value, 'type' => $type, "group_info" => $group_session_id]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();

        return view('masters.users.project_data', compact('project_data_arr', 'project', 'activity', 'group_info', 'project_temp_info', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole'));
        // return view('masters.users.project_data', compact('project_data_arr', 'project', 'activity', 'group_info'));
    }

}
