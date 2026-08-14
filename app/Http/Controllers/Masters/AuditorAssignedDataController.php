<?php

namespace App\Http\Controllers\Masters;

use App\Exports\DynamicTableExport;
use App\Http\Controllers\Controller;
use App\Models\ActivityInstanceClose;
use App\Models\ActivityRepeatInstance;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use App\Models\Verifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AuditorAssignedDataController extends Controller
{
    public function auditor_assigned_data()
    {
        $currentUser     = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if (in_array($currentUserRole, ['Super Admin', 'Admin'])) {
            // Full visibility
            $data_assigned_values = DataAssign::latest()->paginate(10);

        } elseif ($currentUserRole == 'Agency') {
            $getProjectDataWithAgency = Project::where('is_agency_required', 1)->first();
            if (!empty($getProjectDataWithAgency)) {
                $data_assigned_values = DataAssign::where('project_id', $getProjectDataWithAgency->id)->latest()->paginate(10);
            } else {
                $data_assigned_values = DataAssign::whereRaw('0')->paginate(10); // empty result
            }

        } else {
            // Any other role (Auditor, Verifier, etc.) — show only their own assignments
            $assignedDataIds = \App\Models\UserActivityDataAssign::where('user_id', $currentUser->id)
                ->distinct()
                ->pluck('data_assign_id');

            $data_assigned_values = DataAssign::whereIn('id', $assignedDataIds)->latest()->paginate(10);
        }

        return view('masters.data_templates.data_assigned', compact('data_assigned_values'));
    }

    public function get_assigned_auditors(Request $request)
    {
        if ($request->user_type == "auditors") {
            //            $user_list = AuditorAssignedData::where('data_assign_id', $request->data_assigned_id)->with('get_user_info')->get();

            // $user_list = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)->with('get_user_info')->get();

            // $user_list = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)
            //     ->select('user_id')   // only select user_id
            //     ->distinct()
            //     ->with('get_user_info')
            //     ->get();

            // $assignedValues = [];

            // foreach($user_list as $user){
            //     // dd($user->get_user_info->name);
            //     $common_Ids = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)->where('user_id', $user->get_user_info->id)->distinct()->pluck('common_id')->toArray();
            //     $getAssignedData = DataAssign::find($request->data_assigned_id);
            //     $getRowIds = UserAuditAssigns::whereIn('common_id', $common_Ids)->distinct()->pluck('row_id')->toArray();
            //     $distinctValuesAssign = DB::table('project_template_name_values_new')
            //         ->whereIn('id', $getRowIds)
            //         ->select(
            //             'id as row_id',
            //             DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$getAssignedData->template_name_head_id\"')) as head_value")
            //         )
            //         ->get();

            //     $assignedValues[$user->get_user_info->name] = $distinctValuesAssign;
            // }

            // dd($assignedValues);
            // $common_Ids = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)->distinct()->pluck('common_id')->toArray();
            // $getAssignedData = DataAssign::find($request->data_assigned_id);
            // $getRowIds = UserAuditAssigns::whereIn('common_id', $common_Ids)->distinct()->pluck('row_id')->toArray();
            // $distinctValuesAssign = DB::table('project_template_name_values_new')
            //     ->whereIn('id', $getRowIds)
            //     ->select(
            //         'id as row_id',
            //         DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$getAssignedData->template_name_head_id\"')) as head_value")
            //     )
            //     ->get();

            /* Find ALL data_assign IDs for this activity + project_template combination.
               This handles duplicate DataAssign records where users may be stored
               under a different data_assign_id than the one that was clicked. */
            if ($request->filled('activity_id') && $request->filled('project_template_id')) {
                $siblingIds = DataAssign::where('activity_id', $request->activity_id)
                    ->where('project_template_id', $request->project_template_id)
                    ->pluck('id')
                    ->toArray();
            } else {
                $siblingIds = [$request->data_assigned_id];
            }

            /* Get all assigned records across all matching DataAssigns */
            $user_list = UserActivityDataAssign::whereIn('data_assign_id', $siblingIds)
                ->select('user_id', 'common_id')
                ->with('get_user_info')
                ->get()
                ->groupBy('user_id');

            $assignedValues = [];

            foreach ($user_list as $userId => $records) {

                $commonIds = $records->pluck('common_id')->unique()->toArray();
                $getAssignedData = DataAssign::find($request->data_assigned_id);
                $rowIds = UserAuditAssigns::whereIn('common_id', $commonIds)
                    ->distinct()
                    ->pluck('row_id')
                    ->toArray();

                $distinctValuesAssign = DB::table('project_template_name_values_new')
                    ->whereIn('id', $rowIds)
                    ->select(
                        'id as row_id',
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$getAssignedData->template_name_head_id}\"')) as head_value")
                    )
                    ->get();

                $userName = optional($records->first()->get_user_info)->name;

                // $assignedValues[$userName] = $distinctValuesAssign;
                $assignedValues[] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'audits' => $distinctValuesAssign
                ];
            }

            // Fetch verifier names assigned to this project template
            $verifierNames = DB::table('verifiers')
                ->join('users', 'verifiers.user_id', '=', 'users.id')
                ->where('verifiers.project_template_name_id', $request->project_template_id)
                ->pluck('users.name')
                ->unique()
                ->values()
                ->toArray();

        }
        return response()->json([
            'message'        => "Success",
            'user_list'      => $user_list,
            'assignedValues' => $assignedValues,
            'verifierNames'  => $verifierNames ?? [],
        ]);
    }


    public function assignedDestroy(Request $request)
    {
        $request->validate([
            'activity_id' => 'required|integer',
            'project_template_id' => 'required|integer',
        ]);

        return DB::transaction(function () use ($request) {

            // Fetch matching DataAssign rows once, grab both id and project_id from the same query
            $dataAssigns = DataAssign::where('activity_id', $request->activity_id)
                ->where('project_template_id', $request->project_template_id)
                ->get(['id', 'project_id']);

            $dataAssignIds = $dataAssigns->pluck('id')->toArray();

            if (empty($dataAssignIds)) {
                return response()->json(['message' => 'No matching data assignments found'], 404);
            }

            $commonIds = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)
                ->distinct()->pluck('common_id')->toArray();

            $userIds = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)
                ->distinct()->pluck('user_id')->toArray();

            UserAuditAssigns::whereIn('common_id', $commonIds)->delete();
            UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)->delete();

            Verifier::whereIn('user_id', $userIds)
                ->where('project_template_name_id', $request->project_template_id)
                ->delete();

            DataAssign::whereIn('id', $dataAssignIds)->delete();

            // Delete rows and activity answer data for THIS template only
            // (previously this incorrectly pulled ALL templates for the project)
            $rowIds = ProjectTemplateNameValuesNew::where('project_template_id', $request->project_template_id)
                ->pluck('id');

            if ($rowIds->isNotEmpty()) {
                ActivityInstanceClose::whereIn('row_id', $rowIds)
                    ->where('activity_id', $request->activity_id)->delete();

                ActivityRepeatInstance::whereIn('row_id', $rowIds)
                    ->where('activity_id', $request->activity_id)->delete();

                TempUserActivityAnswersData::whereIn('row_id', $rowIds)->delete();
            }

            return response()->json(['message' => 'Success']);
        });
    }


    //khushboo 13-05-25
    public function auditorAssignDataExport()
    {
        $assignedData = DataAssign::with([
            'getAuditorIds',
            'getProjectTemplate.getProject.getCompanyInfo',
            'getProjectTemplate.getProject.getZone',
            'getProjectTemplate.getProject.getUnit',
            'getProjectTemplate.getProject',
            'templateName',
            'TemplateHeadName',
            'activityName'
        ])->get();

        $data = $assignedData->map(function ($assign_data) {

            $dataassignActivity = !empty($assign_data->activityName) ? $assign_data->activityName->activity_name : '';
            $auditorIds = $assign_data->getAuditorIds->pluck('user_id')->unique();
            $auditorNames = User::whereIn('id', $auditorIds)->pluck('name')->unique()->implode(', ');
            // $auditorNames = $assign_data->getAuditorIds->pluck('name')->unique()->implode(', ');

            return [
                $assign_data->getProjectTemplate->getProject->getCompanyInfo->company_name,
                $assign_data->getProjectTemplate->getProject->getZone->zone_name,
                $assign_data->getProjectTemplate->getProject->getUnit->unit_name,
                $assign_data->getProjectTemplate->getProject->project_name,
                $dataassignActivity,
                $assign_data->templateName->template_name,
                $assign_data->TemplateHeadName->template_head_name,
                $auditorNames
            ];
        });

        $headings = ['Company', 'Zone', 'Unit', 'Project', 'Activity Name', 'Template Name', 'Template Head', 'Auditors'];

        return Excel::download(new DynamicTableExport($data, $headings), 'auditor_assign_data.xlsx');
    }
    //khushboo 13-05-25

    //khushboo 5-03-25
    public function destroy_assignment(Request $request)
    {
        try {
            // dd($request->all());

            $rowIds = $request->row_ids;
            $userId = $request->user_id;

            // Get common_ids for that user
            $commonIds = UserActivityDataAssign::where('user_id', $userId)
                ->distinct()
                ->pluck('common_id')
                ->toArray();

            $userauditassign = UserAuditAssigns::whereIn('common_id', $commonIds)
                ->whereIn('row_id', $rowIds)->get();

            // dd($userauditassign);
            // Delete selected rows
            UserAuditAssigns::whereIn('common_id', $commonIds)
                ->whereIn('row_id', $rowIds)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignments deleted successfully'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    //khushboo 5-03-25


}
