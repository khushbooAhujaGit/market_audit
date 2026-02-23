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
use Illuminate\Support\Facades\Validator;
use App\Jobs\ConvertAuditorSelfiesToGeoSelfies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

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

//        if ($project_temp_info->is_master == 1) {

        $projectTemplateInfoDetails = $project_temp_info->getTemplate;
        $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;
        // dd($projectTemplateInfoDetails, $projectTemplateHeaders);
        $add_project_data_title = "Add Distributor";
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
//                            dd($getHeadValues);
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
                            $headData = TemplateNameHead::find((int)$headKey);

                            $projectDataArr[] = [
                                'row_id' => $row->id,
                                'head_id' => $headKey,
                                'head_name' => $headData->template_head_name ?? '',
                                'head_value' => $value,
                            ];
                        }
                    }

//                    dd('ghgj');
//                    dd($projectDataArr);
                    foreach ($projectDataArr as $projectData) {
//                        dd($projectData);
                        if (!in_array($projectData['row_id'], $row_renderred_arr)) {
                            $row_renderred_arr[] = $projectData['row_id'];
                            // $check_if_answered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                            //     ->whereIn('activity_id', $activities)
                            //     ->exists();
                            // Get all required question IDs
                            $activityRequiredQuestions = Question::whereIn('activity_id', $activities)
                                ->where('answer_type', 1)
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
                                $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $projectData['row_id'])
                                    ->whereIn('activity_id', $activities)
                                    ->whereIn('question_id', $activityRequiredQuestions)
                                    ->where('user_id', Auth::user()->id)
                                    ->orderBy('id', 'desc')
                                    ->first();

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

                            $project_data_arr[] = [
                                'data_item' => $projectData,
                                'status' => $check_if_answered ? 'completed' : 'pending',
                                'main_header' => $main_header,
                                'sub_header' => $sub_header,
                                'can_edit_data' => $project_temp_info->can_edit_data,
                                'otpVerificationDone' => $otpVerificationDone,
                                'header_data' => $result
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

            $activityRequiredQuestions = Question::where('activity_id', $assigned_data->activity_id)
                ->where('answer_type', 1)
                ->pluck('id')
                ->toArray();

            // Count how many required questions were submitted
            $submittedcount = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $assigned_data->activity_id)
                ->whereIn('question_id', $activityRequiredQuestions)
                ->where('user_id', Auth::user()->id)
                ->distinct('question_id')
                ->count('question_id');

            // Check if any answer exists at all
            $is_answered = $submittedcount > 0;

            // Check if subjective question exists
            $subjectiveExists = Question::where('activity_id', $assigned_data->activity_id)
                ->where('answer_type', 1)
                ->where('question_type', 'Subjective')
                ->exists();

            // Total required questions count
            $requiredCount = count($activityRequiredQuestions);

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

            $otpVerificationDone = false;
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
                    'otpVerificationDone' => $otpVerificationDone
                ];
            }
        }
        $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();
        // dd($userAssignedActivities, Auth::user()->id);

        return view('masters.users.project_activities', compact('userAssignedActivities', 'project', 'row_id', 'status', 'distributor_value', 'with_data_check'));
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


    public function row_activity_answers_old(Request $request)
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

    public function row_activity_answerssss(Request $request)
    {
        try {
            // basic context
            // dd($request->all());
            if (empty($request->latitude) && empty($request->longitude)) {
                return redirect()->back()->with('error', "Location is required for answer submission.");
            }
            $userId = Auth::id();
            $last_sequence = (int)TempUserActivityAnswersData::max('same_answer_id') + 1;

            $baseDirectory = 'activityAnswerImages/';
            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                return redirect()->back()->with('error', "Project Template Data Not Found");
//                return response()->json(['status' => 422, 'message' => 'Invalid row_id'], 422);
            }
            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $otp_required = false;
            if ($projectTemp->getProjectTemplateData->getProject->is_otp_required == 1) {
                $otp_required = true;
            }
            $projectDirectory = $baseDirectory . $projectName . '/';
            $this->checkAndCreateDirectory($projectDirectory);

            $currentMonthYear = Carbon::now()->format('FY'); // e.g. Nov2025
            $projectMonthYearDirectory = $projectDirectory . $currentMonthYear;
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            $latitude = $request->latitude ?? null;
            $longitude = $request->longitude ?? null;

            $userDetails = User::find($userId);

            // keys to ignore from request
            $excludedKeys = ['_token', 'user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude', 'save'];
            $questionAnswers = collect($request->all())->except($excludedKeys);

            // list of allowed bind attributes for subjective questions (normalized)
            $allowedBindAttributes = [
                'video', 'audio', 'dropdown', 'multiselect', 'image',
                'date', 'datetime', 'yesno', 'fileupload', 'freetext', 'location'
            ];

            // Build list of distinct numeric question ids submitted
            $submittedQuestionIds = $questionAnswers->keys()
                ->map(function ($k) {
                    preg_match('/^(\d+)/', (string)$k, $m);
                    return isset($m[1]) ? (int)$m[1] : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // validate required questions presence (answer_type == 1)
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
//                return response()->json([
//                    'status' => 422,
//                    'message' => 'Some required question types are missing from the submission.',
//                    'missing_question_types' => $missingTypes
//                ], 422);
            }

            $imagesToProcess = [];

            // Use DB transaction to keep consistency
            DB::beginTransaction();

            foreach ($questionAnswers as $rawKey => $rawValue) {
                // parse numeric id and optional suffix
                preg_match('/^(\d+)(.*)$/', (string)$rawKey, $parts);
                if (empty($parts[1])) {
                    // not a question key, skip
                    continue;
                }

                $questionId = (int)$parts[1];
                $suffixRaw = trim((string)($parts[2] ?? ''));

                // normalize attribute: remove non-alphanumeric and lowercase
                $attributeNormalized = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $suffixRaw));

                // Load question once
                $question = Question::find($questionId);
                if (!$question) {
                    continue;
                }

                // If question is Subjective -> allow binded attribute handling
                if ($question->question_type === 'Subjective') {
                    // If there is a suffix and it's in allowed list -> treat as binded attribute
                    if (!empty($attributeNormalized) && in_array($attributeNormalized, $allowedBindAttributes)) {
                        // Handle binded attribute for subjective question
                        // Determine if this key is file
                        $user_answer = null;
                        $file_path = null;

                        if ($request->hasFile($rawKey)) {
                            $file = $request->file($rawKey);
                            // determine validation by attribute
                            $rules = [];
                            if ($attributeNormalized === 'image') {
                                $rules['file_input'] = 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                            } elseif ($attributeNormalized === 'fileupload') {
                                $rules['file_input'] = 'required|file|mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/jpg,image/png,image/webp|max:51200';
                            } elseif ($attributeNormalized === 'audio') {
                                $rules['file_input'] = 'required|mimes:mp3,wav,ogg|max:51200';
                            } elseif ($attributeNormalized === 'video') {
                                $rules['file_input'] = 'required|mimes:mp4,mov,avi,webm|max:512000';
                            } else {
                                $rules['file_input'] = 'required|file|max:512000';
                            }

                            $validator = Validator::make(['file_input' => $file], $rules);
                            if ($validator->fails()) {
                                DB::rollBack();
                                return redirect()->back()->with('error', "Invalid file for question : {$question->question} (key: {$rawKey})");
//                                return response()->json([
//                                    'status' => 422,
//                                    'message' => "Invalid file for question ID: {$questionId} (key: {$rawKey})",
//                                    'errors' => $validator->errors()->all()
//                                ], 422);
                            }

                            $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                            $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                            $file->move(public_path($projectMonthYearDirectory), $file_name);
                            @chmod(public_path($full_file_path), 0777);

                            $user_answer = $full_file_path;
                            $file_path = $full_file_path;
                        } else {
                            // not a file: handle text/array answers
                            if (is_array($rawValue)) {
                                // multi-select likely
                                $user_answer = implode(',', $rawValue);
                            } else {
                                $user_answer = trim((string)$rawValue);
                            }

                            // format date/datetime if attribute says so
                            if ($attributeNormalized === 'date' && !empty($user_answer)) {
                                try {
                                    $user_answer = Carbon::parse($user_answer)->format('d/m/Y');
                                } catch (\Exception $e) {
                                    $user_answer = null;
                                }
                            } elseif ($attributeNormalized === 'datetime' && !empty($user_answer)) {
                                try {
                                    $user_answer = Carbon::parse($user_answer)->format('d/m/Y H:i');
                                } catch (\Exception $e) {
                                    $user_answer = null;
                                }
                            } elseif ($attributeNormalized === 'yesno' && !is_null($user_answer)) {
                                $norm = strtolower($user_answer);
                                if (in_array($norm, ['yes', '1', 'true'])) $user_answer = 'Yes';
                                elseif (in_array($norm, ['no', '0', 'false'])) $user_answer = 'No';
                            }
                        }

                        // Create separate row for this subjective binded attribute
                        $answer = TempUserActivityAnswersData::create([
                            'user_id' => $userId,
                            'row_id' => $request->row_id,
                            'activity_id' => $request->activity_id,
                            'activity_group_name_id' => $request->group_id ?? 0,
                            'question_id' => $questionId,
                            // store attribute so you can identify bind type later (add column attribute in table if not exist)
                            'attribute' => $attributeNormalized,
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);

                        // queue images (or other file types if you want)
                        if (($attributeNormalized === 'image') && $file_path) {
                            $imagesToProcess[] = [
                                'file_path' => public_path($file_path),
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'answer' => $answer,
                                'directory' => $projectMonthYearDirectory,
                                'user_id' => $userDetails->id,
                            ];
                        }

                        // move to next request key
                        continue;
                    }

                    // If no valid bind attribute present -> treat as a main subjective answer (single row handling below)
                }

                // NON-SUBJECTIVE or main-key-for-subjective handling:
                // For non-subjective: only main numeric key should be present/processed (ignore any suffixes).
                // If rawKey had a suffix, we treat it as main value for the numeric question only when attributeNormalized is empty.
                // So map to main question value:
                $isMainKey = (string)$rawKey === (string)$questionId || $attributeNormalized === '';

                if (!$isMainKey) {
                    // skip: non-subjective question should not accept binded keys
                    continue;
                }

                // prepare user_answer/file_path similar to earlier
                $user_answer = null;
                $file_path = null;

                if ($request->hasFile($rawKey)) {
                    $file = $request->file($rawKey);
                    $rules = [];
                    if ($question->question_type === 'Image') {
                        $rules['file_input'] = 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                    } elseif ($question->question_type === 'File Upload') {
                        $rules['file_input'] = 'required|file|mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/jpg,image/png,image/webp|max:51200';
                    } else {
                        $rules['file_input'] = 'required|file|max:512000';
                    }

                    $validator = Validator::make(['file_input' => $file], $rules);
                    if ($validator->fails()) {
                        DB::rollBack();
                        return redirect()->back()->with('error', "Invalid file for question : {$question->question} (key: {$rawKey})");
//                        return response()->json([
//                            'status' => 422,
//                            'message' => "Invalid file for question ID: {$questionId} (key: {$rawKey})",
//                            'errors' => $validator->errors()->all()
//                        ], 422);
                    }

                    $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                    $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                    $file->move(public_path($projectMonthYearDirectory), $file_name);
                    @chmod(public_path($full_file_path), 0777);

                    $user_answer = $full_file_path;
                    $file_path = $full_file_path;
                } else {
                    // text/array value
                    if (is_array($rawValue)) {
                        $user_answer = implode(',', $rawValue);
                    } else {
                        $user_answer = trim((string)$rawValue);
                    }

                    // format based on question_type
                    if ($question->question_type === 'Date' && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif (($question->question_type === 'Date & Time' || $question->question_type === 'Date&Time') && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y H:i');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif ($question->question_type === 'Yes / No' || $question->question_type === 'Yes/No') {
                        $norm = strtolower($user_answer);
                        if (in_array($norm, ['yes', '1', 'true'])) $user_answer = 'Yes';
                        elseif (in_array($norm, ['no', '0', 'false'])) $user_answer = 'No';
                    }
                }

                // Insert or update for non-subjective
                $query = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where(function ($q) use ($request) {
                        $q->where('activity_id', $request->activity_id)
                            ->orWhere('activity_group_name_id', $request->group_id);
                    })
                    ->where('question_id', $questionId);

                if ($question->question_type === 'Subjective') {
                    // Subjective main-key (not binded) - support multiple rows as before
                    $count = $query->count();
                    if ($count === 0) {
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
                        // check the existing subject answer and handle bracket logic (same as your earlier logic)
                        $existing = $query->first();
                        $subjectiveAnswer = $existing->user_answer ?? '';
                        // remove spaces while checking
                        $cleanedExistingNoSpaces = preg_replace('/\s+/', '', trim($subjectiveAnswer));
                        // count groups with parenthesis
                        preg_match_all('/\(([^)]+)\)/', $cleanedExistingNoSpaces, $matches);
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
                        } else {
                            // update the existing subjective answer if bracket count not 1
                            $query->update([
                                'user_answer' => $user_answer,
                                'same_answer_id' => $last_sequence,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                            ]);
                            $answer = $query->first();
                        }
                    }
                } else {
                    // Non-subjective: insert-or-update single record
                    if (!$query->exists()) {
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
                        $query->update([
                            'user_answer' => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ]);
                        $answer = $query->first();
                    }
                }

                // queue image processing if needed (image saved as file_path)
                if (($question->question_type === 'Image' || $attributeNormalized === 'image') && !empty($file_path)) {
                    $imagesToProcess[] = [
                        'file_path' => public_path($file_path),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'answer' => $answer ?? null,
                        'directory' => $projectMonthYearDirectory,
                        'user_id' => $userDetails->id,
                    ];
                }
            } // end foreach

            DB::commit();

            if (!empty($imagesToProcess)) {
                dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
            }

            $message = "Activity Answer submitted successfully";
            if (Session::has('project_outlet_activity')) {
                $params = Session::get('project_outlet_activity');
                if ($otp_required) {
                    $message = $message . " Please verify otp";
                    return redirect()->route('otp_verification_page', [
                        'row_id' => $request->row_id,
                        'activity' => $request->activity_id,
                    ])->with('message', $message);
                }
                return redirect(route('user.project.distributor.outlets', $params))->with('message', $message);
            }
            if (Session::has('project_dist_activity')) {
                $params = Session::get('project_dist_activity');
                if ($otp_required) {
                    $message = $message . " Please verify otp";
                    return redirect()->route('otp_verification_page', [
                        'row_id' => $request->row_id,
                        'activity' => $request->activity_id,
                    ])->with('message', $message);
                }
                return redirect(route('user.project_master.data', $params))->with('message', $message);
            }
        } catch (\Exception $e) {
            // dd($e->getMessage());
            DB::rollBack();
            Log::error('Activity submit error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'request_keys' => array_keys($request->all())]);
            return redirect()->back()->with('error', $e->getMessage());
//            return response()->json(['status' => 500, 'message' => 'Internal Server Error'], 500);
        }
    }

    public function row_activity_answers(Request $request)
    {
        DB::beginTransaction();
        try {
            // Check location
            if (empty($request->latitude) && empty($request->longitude)) {
                return redirect()->back()->with('error', "Location is required for answer submission.");
            }

            $userId = Auth::id();
            $last_sequence = (int)TempUserActivityAnswersData::max('same_answer_id') + 1;

            // Get project details
            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                return redirect()->back()->with('error', "Project Template Data Not Found");
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
                'video', 'audio', 'dropdown', 'multiselect', 'image',
                'date', 'datetime', 'yesno', 'fileupload', 'freetext', 'location'
            ];

            // Get submitted question IDs
            $submittedQuestionIds = $questionAnswers->keys()
                ->map(function ($key) {
                    preg_match('/^(\d+)/', (string)$key, $matches);
                    return isset($matches[1]) ? (int)$matches[1] : null;
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
                preg_match('/^(\d+)(.*)$/', (string)$rawKey, $parts);
                if (empty($parts[1])) {
                    continue;
                }

                $questionId = (int)$parts[1];
                $suffix = trim((string)($parts[2] ?? ''));
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
                        return redirect()->back()->with('error',
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
                        $user_answer = trim((string)$rawValue);
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
                    }
                    // Handle audio/webm base64 data
//                    elseif (($question->question_type === 'Audio' || $attribute === 'audio') &&
//                        (str_starts_with($user_answer, 'data:video/webm') ||
//                            str_starts_with($user_answer, 'data:audio/webm') ||
//                            str_starts_with($user_answer, 'data:audio/ogg'))) {
//
//                        $isFile = true;
//
//                        // Determine the input format
//                        if (str_starts_with($user_answer, 'data:video/webm')) {
//                            $inputFormat = 'webm';
//                            $mimeType = 'video/webm';
//                        } elseif (str_starts_with($user_answer, 'data:audio/webm')) {
//                            $inputFormat = 'webm';
//                            $mimeType = 'audio/webm';
//                        } elseif (str_starts_with($user_answer, 'data:audio/ogg')) {
//                            $inputFormat = 'ogg';
//                            $mimeType = 'audio/ogg';
//                        } else {
//                            $inputFormat = 'webm';
//                            $mimeType = 'audio/webm';
//                        }
//
//                        // Extract base64 data
//                        $base64Data = explode(',', $user_answer)[1] ?? '';
//                        if (empty($base64Data)) {
//                            DB::rollBack();
//                            return redirect()->back()->with('error',
//                                "Invalid audio data for question: {$question->question}"
//                            );
//                        }
//
//                        // Decode base64
//                        $audioData = base64_decode($base64Data);
//                        if ($audioData === false) {
//                            DB::rollBack();
//                            return redirect()->back()->with('error',
//                                "Failed to decode audio data for question: {$question->question}"
//                            );
//                        }
//
//                        // Save temporary WebM/OGG file
//                        $tempInputName = uniqid() . '_temp.' . $inputFormat;
//                        $tempInputPath = storage_path('app/temp/' . $tempInputName);
//
//                        // Ensure temp directory exists
//                        if (!file_exists(dirname($tempInputPath))) {
//                            mkdir(dirname($tempInputPath), 0777, true);
//                        }
//
//                        file_put_contents($tempInputPath, $audioData);
//
//                        // Convert to MP3
//                        $mp3FileName = uniqid() . '_' . time() . '.mp3';
//                        $full_file_path = $projectMonthYearDirectory . '/' . $mp3FileName;
//                        $absoluteMp3Path = public_path($full_file_path);
//
//                        try {
//                            // Convert using FFmpeg
//                            FFMpeg::fromDisk('local')
//                                ->open('temp/' . $tempInputName)
//                                ->export()
//                                ->toDisk('public')
//                                ->inFormat(new \FFMpeg\Format\Audio\Mp3())
//                                ->save($full_file_path);
//
//                            // Clean up temp file
//                            unlink($tempInputPath);
//
//                            // Set permissions
//                            @chmod($absoluteMp3Path, 0777);
//
//                            $user_answer = $full_file_path;
//                            $file_path = $full_file_path;
//
//                        } catch (\Exception $e) {
//                            // If conversion fails, fallback to original format
//                            Log::warning('Audio conversion failed: ' . $e->getMessage());
//
//                            // Save original WebM file as fallback
//                            $fallbackFileName = uniqid() . '_' . time() . '.' . $inputFormat;
//                            $fallbackPath = $projectMonthYearDirectory . '/' . $fallbackFileName;
//                            $absoluteFallbackPath = public_path($fallbackPath);
//
//                            // Move the temp file to final location
//                            rename($tempInputPath, $absoluteFallbackPath);
//                            @chmod($absoluteFallbackPath, 0777);
//
//                            $user_answer = $fallbackPath;
//                            $file_path = $fallbackPath;
//                        }
//
//                    }
                    // Handle video/webm base64 data
                    elseif (($question->question_type === 'Video' || $attribute === 'video') &&
                        str_starts_with($user_answer, 'data:video/webm')) {

                        $isFile = true;

                        // Extract base64 data
                        $base64Data = explode(',', $user_answer)[1] ?? '';
                        if (empty($base64Data)) {
                            DB::rollBack();
                            return redirect()->back()->with('error',
                                "Invalid video data for question: {$question->question}"
                            );
                        }

                        // Decode base64
                        $videoData = base64_decode($base64Data);
                        if ($videoData === false) {
                            DB::rollBack();
                            return redirect()->back()->with('error',
                                "Failed to decode video data for question: {$question->question}"
                            );
                        }

                        // Save temporary WebM file
                        $tempInputName = uniqid() . '_temp.webm';
                        $tempInputPath = storage_path('app/temp/' . $tempInputName);

                        // Ensure temp directory exists
                        if (!file_exists(dirname($tempInputPath))) {
                            mkdir(dirname($tempInputPath), 0777, true);
                        }

                        file_put_contents($tempInputPath, $videoData);

                        // Convert to MP4
                        $mp4FileName = uniqid() . '_' . time() . '.mp4';
                        $full_file_path = $projectMonthYearDirectory . '/' . $mp4FileName;
                        $absoluteMp4Path = public_path($full_file_path);

                        try {
                            // Convert using FFmpeg
                            FFMpeg::fromDisk('local')
                                ->open('temp/' . $tempInputName)
                                ->export()
                                ->toDisk('public')
                                ->inFormat(new \FFMpeg\Format\Video\X264('libmp3lame', 'libx264'))
                                ->save($full_file_path);

                            // Clean up temp file
                            unlink($tempInputPath);

                            // Set permissions
                            @chmod($absoluteMp4Path, 0777);

                            $user_answer = $full_file_path;
                            $file_path = $full_file_path;

                        }
                        catch (\Exception $e) {
                            // If conversion fails, fallback to original WebM
                            Log::warning('Video conversion failed: ' . $e->getMessage());

                            // Save original WebM file as fallback
                            $fallbackFileName = uniqid() . '_' . time() . '.webm';
                            $fallbackPath = $projectMonthYearDirectory . '/' . $fallbackFileName;
                            $absoluteFallbackPath = public_path($fallbackPath);

                            // Move the temp file to final location
                            rename($tempInputPath, $absoluteFallbackPath);
                            @chmod($absoluteFallbackPath, 0777);

                            $user_answer = $fallbackPath;
                            $file_path = $fallbackPath;
                        }
                    }
                    elseif (($question->question_type === 'Audio') && (str_starts_with($user_answer, 'data:audio/'))) {

                        $isFile = true;

                        // Determine file type and extension
                        if (str_starts_with($user_answer, 'data:video/')) {
                            $fileType = 'video';
                            $mimeType = explode(';', explode(':', $user_answer)[1])[0];
                            $extension = $this->getExtensionFromMime($mimeType);
                            if (!$extension) {
                                $extension = 'webm'; // default for webm videos
                            }

                            // Validate video size (approx. 50MB)
                            $base64Content = explode(',', $user_answer)[1] ?? '';
                            $size = (strlen($base64Content) * 3) / 4; // Approximate size
                            if ($size > 52428800) { // 50MB in bytes
                                DB::rollBack();
                                return redirect()->back()->with('error',
                                    "Video file is too large. Maximum size is 50MB."
                                );
                            }

                        }
                        elseif (str_starts_with($user_answer, 'data:audio/')) {
                            $fileType = 'audio';
                            $mimeType = explode(';', explode(':', $rawValue)[1])[0];
                            $extension = $this->getExtensionFromMime($mimeType);
                            if (!$extension) {
                                $extension = 'mp3'; // default for audio
                            }

                            // Validate audio size (approx. 5MB)
                            $base64Content = explode(',', $user_answer)[1] ?? '';
                            $size = (strlen($base64Content) * 3) / 4;
                            if ($size > 5242880) { // 5MB in bytes
                                DB::rollBack();
                                return redirect()->back()->with('error',
                                    "Audio file is too large. Maximum size is 5MB."
                                );
                            }
                        }

                        // Decode and save the base64 file
                        $base64Data = explode(',', $user_answer)[1] ?? '';
                        if (empty($base64Data)) {
                            DB::rollBack();
                            return redirect()->back()->with('error',
                                "Invalid {$fileType} data for question: {$question->question}"
                            );
                        }

                        // Decode base64
                        $fileData = base64_decode($base64Data);
                        if ($fileData === false) {
                            DB::rollBack();
                            return redirect()->back()->with('error',
                                "Failed to decode {$fileType} data for question: {$question->question}"
                            );
                        }

                        // Generate unique filename
                        $fileName = uniqid() . '_' . time() . '.' . $extension;
                        $full_file_path = $projectMonthYearDirectory . '/' . $fileName;
                        $absolutePath = public_path($full_file_path);

                        // Save the file
                        $bytesWritten = file_put_contents($absolutePath, $fileData);
                        if ($bytesWritten === false) {
                            DB::rollBack();
                            return redirect()->back()->with('error',
                                "Failed to save {$fileType} file for question: {$question->question}"
                            );
                        }

                        @chmod($absolutePath, 0777);

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
     * Get file extension from MIME type
     */
    private function getExtensionFromMime($mimeType)
    {
        $mimeToExt = [
            // Video MIME types
            'video/webm' => 'webm',
            'video/mp4' => 'mp4',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/3gpp' => '3gp',

            // Audio MIME types
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/webm' => 'weba',
        ];

        return $mimeToExt[$mimeType] ?? null;
    }


    public function userProjectActivityDistributorOutletData($row_id, $distributor_value)
    {
        // dd($distributor_value, $row_id);
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);

        $add_project_data_title = "Add Outlet";
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
                    $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                        ->whereIn('activity_id', $activities)
                        ->whereIn('question_id', $activityRequiredQuestions)
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
                    'header_data' => $result
                ];
            }
        }

        $withoutDataTemplate = $childTemplatesData->where('with_data', 0)->count();
        if (empty($projectTemplateHeaders) && !empty($getAssignedChildTemplateIDs)) {
            foreach ($childTemplatesData as $childData) {
                // dd($childData->data_add_on, $childData->with_data, in_array($childData->id, $getAssignedChildTemplateIDs), $getAssignedChildTemplateIDs, $childData->id, $parentDataAssignIds);
                if (($childData->with_data == 0 || $childData->data_add_on == 1) && in_array($childData->id, $getAssignedChildTemplateIDs)) {
                    $projectTemplateHeaders[] = [
                        'id' => $childData->id,
                        'data' => $childData->getTemplate->getTemplateHeads,
                        'template_name' => $childData->getTemplate->template_name
                    ];
                }
            }
        }
        // dd($projectTemplateHeaders);

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


    public function userProjectActivityDistributorOutletDataOLDOct($row_id, $distributor_value)
    {
        $projectTemplateNameValue = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplateId = $projectTemplateNameValue->project_template_id;
        $projectTemplate = ProjectTemplate::find($projectTemplateId);
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

        $project_data_completed_arr = [];


        $childTemplatesData = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $childTemplatesIds = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('id')->toArray();

//        $outlet_items = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
//            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->own_reference_head_id\"')) = ?", [trim($distributor_value)])
//            ->get();

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

        if ($checkPrentOutletAssign) {

            $parentDataAssignIds = DataAssign::where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

            $dataAssignCommonIds = UserActivityDataAssign::where('user_id', auth()->user()->id)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id');

            $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
                ->distinct('row_id')
                ->pluck('row_id');

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
                // $check_if_answered = TempUserActivityAnswersData::where('row_id', $outlet_item->id)
                //     ->whereIn('activity_id', $activities)
                //     ->exists();

                $activityRequiredQuestions = Question::whereIn('activity_id', $activities)
                    ->where('answer_type', 1)
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

                $project_data_arr[] = [
                    'data_item' => $outlet_item,
                    'status' => $check_if_answered ? 'completed' : 'pending',
                    'main_header' => $main_header,
                    'sub_header' => $sub_header,
                ];
            }
        }

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

    public function otp_verification_page($row_id, $activity_id)
    {
        return view('masters.users.otp_verification', compact('row_id', 'activity_id'));
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
