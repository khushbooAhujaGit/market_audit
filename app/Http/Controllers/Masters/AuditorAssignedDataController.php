<?php

namespace App\Http\Controllers\Masters;

use App\Exports\DynamicTableExport;
use App\Http\Controllers\Controller;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Project;
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
        //khushboo 03-04-2025
        $currentUser = User::find(Auth::user()->id);
        if ($currentUser->getRoleNames()->first() == 'Agency') {
            $getProjectDataWithAgency = Project::where('is_agency_required', 1)->first();
//            dd($getProjectDataWithAgency);
            if (!empty($getProjectDataWithAgency)) {
                $data_assigned_values = DataAssign::where('project_id', $getProjectDataWithAgency->id)->latest()->paginate(10);
            }
            // $getProjectTemplateData = ProjectTemplate::where('project_id', $getProjectDataWithAgency->id)->get();
        } else {
            $data_assigned_values = DataAssign::latest()->paginate(10);
        }
        //khushboo 03-04-2025

//         $data_assigned_values = DataAssign::latest()->paginate(10);
        return view('masters.data_templates.data_assigned', compact('data_assigned_values'));
    }

    public function get_assigned_auditors(Request $request)
    {
        if ($request->user_type == "auditors") {
//            $user_list = AuditorAssignedData::where('data_assign_id', $request->data_assigned_id)->with('get_user_info')->get();

            // $user_list = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)->with('get_user_info')->get();
            $user_list = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)
                ->select('user_id')   // only select user_id
                ->distinct()
                ->with('get_user_info')
                ->get();

            $common_Ids = UserActivityDataAssign::where('data_assign_id', $request->data_assigned_id)->distinct()->pluck('common_id')->toArray();
            $getAssignedData = DataAssign::find($request->data_assigned_id);
            $getRowIds = UserAuditAssigns::whereIn('common_id', $common_Ids)->distinct()->pluck('row_id')->toArray();
            $distinctValuesAssign = DB::table('project_template_name_values_new')
                ->whereIn('id', $getRowIds)
                ->select(
                    'id as row_id',
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$getAssignedData->template_name_head_id\"')) as head_value")
                )
                ->get();
        }
        return response()->json(['message' => "Success", 'user_list' => $user_list, 'distinctValuesAssign' => $distinctValuesAssign]);
    }

    public function assignedDestroy(Request $request)
    {
        $dataAssign = DataAssign::findOrFail($request->id);
        AuditorAssignedData::where('data_assign_id', $dataAssign->id)->delete();
        //        VerifyUserAssignedData::where('data_assign_id', $dataAssign->id)->delete();
        $commonIds = UserActivityDataAssign::where('data_assign_id', $dataAssign->id)->distinct()->pluck('common_id')->toArray();
        $userIds = UserActivityDataAssign::where('data_assign_id', $dataAssign->id)->distinct()->pluck('user_id')->toArray();
        UserAuditAssigns::whereIn('common_id', $commonIds)->delete();
        UserActivityDataAssign::where('data_assign_id', $dataAssign->id)->delete();
        Verifier::whereIn('user_id', $userIds)->where('project_template_name_id', $dataAssign->project_template_id)->delete();

        $dataAssign->delete();
        return "Success";
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

}
