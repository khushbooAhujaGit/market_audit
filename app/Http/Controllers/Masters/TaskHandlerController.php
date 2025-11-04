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
            ->where('is_master', 1)
            ->first();
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id = $project_temp_info->sub_header;

        $project_template_details = DB::table('project_templates')->where('project_id', $project->id)
            ->get();
        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        $add_project_data_title = "Add Distributor";

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
                        } else {
                            $getHeadValues = DB::table('project_template_name_values_new')
                                ->whereIn('id', $getRowIds)
                                ->select(
                                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                                )
                                ->distinct('head_value')
                                ->get();
                        }
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
                    foreach ($projectDataArr as $projectData) {
//                        dd($projectData);
                        if (!in_array($projectData['row_id'], $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData['row_id'];
                            $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                ->whereIn('activity_id', $activities)
                                ->exists();

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
                            $project_data_arr[] = [
                                'data_item' => $projectData,
                                'status' => $check_if_answered ? 'completed' : 'pending',
                                'main_header' => $main_header,
                                'sub_header' => $sub_header,
                                'can_edit_data' => $project_temp_info->can_edit_data
                            ];
                        }
                    }
                }
            }
        }

        Session::put('project_dist_activity', ['project' => $project->id]);
        if (Session::has('project_outlet_activity')) {
            Session::forget('project_outlet_activity');
        }
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        //                \Log::info('Time taken for userProjectData: ' . $executionTime . ' seconds.');
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();

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

    public function userProjectActivities($row_id, $status)
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
//            dd($data_assign_info_ids);
            $checkoutletAssign = UserActivityDataAssign::where('user_id', $user->id)
                ->whereIn('project_template_id', $childTemplateDataIds)
                ->whereIn('data_assign_id', $data_assign_info_ids)
                ->exists();
//            dd($checkoutletAssign);
            if ($checkoutletAssign) {
                $emptyDataTemplateEixsts = ProjectTemplate::where('project_id', $projectTemplateData->project_id)
                    ->where('is_master', 0)
                    ->where('with_data', 0)
                    ->exists();
//                dd($emptyDataTemplateEixsts);
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

        return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'row_id', 'status', 'distributor_value', 'with_data_check'));
    }


    // this is to render the questions of the activity and take the answers from the user against the row ID
    public function row_data_activity($row_id, Activity $activity, ActivityGroup $group_info)
    {
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


    public function userProjectActivityDistributorOutletData($row_id, $distributor_value)
    {
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);

        $add_project_data_title = "Add Outlet";
        $project = Project::find($projectTemplate->project_id);
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;
        $row_renderred_arr = [];
        $project_data_arr = [];

        $projectTemplateHeaders = [];

        $project_temp_info = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->first();

        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $childTemplatesIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('id')->toArray();

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

//        dd($checkPrentOutletAssign);
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

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIds)
                ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$distributor_value])
                ->get();

        } else {

            $dataAssignCommonIdsnew = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('activity_id', $activities)
                ->distinct('common_id')
                ->pluck('common_id');

            $getRowIdsnew = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIdsnew)
                ->distinct('row_id')
                ->pluck('row_id');

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIdsnew)
                ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$distributor_value])
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

            $outlet_items = ProjectTemplateNameValuesNew::whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('id', $getRowIds)
                ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$distributor_value])
                ->get();

        }

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;
                $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                    ->whereIn('activity_id', $activities)
                    ->exists();

                $templateNameData = DB::table('project_templates')->where('id', $outlet_item->project_template_id)->first();

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

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $check_if_answered ? 'completed' : 'pending',
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                    'can_edit_data' => $projecttemplatedata->can_edit_data
                ];
            }
        }

        $withoutDataTemplate = $childTemplatesData->where('with_data', 0)->count();
        if (!empty($getAssignedChildTemplateIDs)) {
            foreach ($childTemplatesData as $childData) {
                if (($childData->with_data == 0 || $childData->data_add_on == 1) && in_array($childData->id, $getAssignedChildTemplateIDs)) {
                    $projectTemplateHeaders[] = [
                        'id' => $childData->id,
                        'data' => $childData->getTemplate->getTemplateHeads,
                        'template_name' => $childData->getTemplate->template_name
                    ];
                }
            }
        }
//        dd($projectTemplateHeaders);

//        dd($project_data_arr);
        $isOutletAssigned = 0;
        $isParent = 0;
        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'distributor_value' => $distributor_value,]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getROleNames()->first();

        return view('masters.users.project_data', compact('project_data_arr', 'isParent', 'row_id', 'isOutletAssigned', 'project', 'project_temp_info', 'projectTemplateHeaders', 'add_project_data_title', 'currentUserRole'));
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

}
