<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use App\Models\Verifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectTemplateController extends Controller
{
    public function projectTemplateInfo(Request $request)
    {
        $projectTemplate = ProjectTemplate::with('getTemplate.getTemplateHeads')->where('project_id', $request->p_id)
            ->where('template_name_id', $request->t_id)->first();
        $project_templates_count = ProjectTemplate::where('project_id', $request->p_id)
            ->count();
//        $project_template_main_headers = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
//            ->where('template_name_head_id', $projectTemplate->main_header)
//            ->orderBy("row_id")
//            ->get();
//        $project_template_sub_headers = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
//            ->where('template_name_head_id', $projectTemplate->sub_header)
//            ->orderBy("row_id")
//            ->get();

        //check if data exists on project_template_name_values_new
        $head_data_exists = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)->exists();
        //check if data exists on project_template_name_values_new

        $project_template_main_headers = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->main_header\"')) as head_value")
            )
            ->orderBy("id")
            ->get();

        $project_template_sub_headers = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->sub_header\"')) as head_value")
            )
            ->orderBy("id")
            ->get();

        //khushboo 03-04-2025
        $verifier_exist = false;
        // $projectTemplate_info = ProjectTemplate::where('project_id', $request->p_id)
        // ->where('template_name_id', $request->t_id)->first();
        $check_if_allocated = Verifier::where('project_template_name_id', $projectTemplate->id)->orderBy('id', 'desc')->get();
        if (!empty($check_if_allocated)) {
            $verifier_exist = true;
        }

        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        $checkDataAssign = [];

        if ($currentUserRole == 'Agency') {
            $project_info = Project::find($request->p_id);

            $selected_activities = null;
            $act_group_id = null;
            if ($projectTemplate->activityType == 0) {
                $selected_activities = $projectTemplate->activity_group_name_id_or_activity_id;
            } else if ($projectTemplate->activityType == 1) {
                $act_group_id = $projectTemplate->activity_group_name_id_or_activity_id;
            }

            $checkDataAssign = DataAssign::where('company_id', $project_info->company_id)
                ->where('zone_id', $project_info->zone_id)
                ->where('unit_id', $project_info->unit_id)
                ->where('project_id', $project_info->id)
                ->where('activity_id', $selected_activities)
                ->where('activity_group_id', $act_group_id)
                ->where('template_name_id', $projectTemplate->template_name_id)
                ->where('project_template_id', $projectTemplate->id)
//                ->where('template_name_head_id', $projectTemplate->template_name_head_id)
                ->first();

//            dd($checkDataAssign);
//            dd($project_info, $selected_activities, $act_group_id, $projectTemplate);
        }
        //khushboo 03-04-2025


        return response()->json(['message' => 'success', 'projectTemplate' => $projectTemplate,
            'project_templates_count' => $project_templates_count,
            'sub_headers' => $project_template_sub_headers,
            'main_headers' => $project_template_main_headers,
            'verifier_exist' => $verifier_exist,
            'checkDataAssign' => $checkDataAssign,
            'head_data_exists' => $head_data_exists
        ]);
    }

    // this function will give us only the rows which are assigned to any of the auditor
    public function projectTemplateInfoProjectReport(Request $request)
    {
        $projectTemplate = ProjectTemplate::with(['getTemplate.getTemplateHeads'])
            ->where('project_id', $request->p_id)
            ->where('template_name_id', $request->t_id)
            ->first();
        if (!$projectTemplate) {
            return response()->json(['message' => 'Project Template not found'], 404);
        }

        $auditorAssignedDataItems = UserActivityDataAssign::with(['dataAssign'])
            ->where('project_template_id', $projectTemplate->id)
            ->get();

        $auditorAssignedCommonIds = UserActivityDataAssign::with(['dataAssign'])
            ->where('project_template_id', $projectTemplate->id)
            ->distinct('common_id')->pluck('common_id')->toArray();

        $getDistinctRowIds = UserAuditAssigns::whereIn('common_id', $auditorAssignedCommonIds)
            ->pluck('row_id')->toArray();

        $templateNameHeadIds = $auditorAssignedDataItems->pluck('dataAssign.template_name_head_id')->filter()->toArray();

        $project_templates_count = ProjectTemplate::where('project_id', $request->p_id)
            ->count();

        $project_template_main_headers = ProjectTemplateNameValuesNew::whereIn('id', $getDistinctRowIds)
            ->where('project_template_id', $projectTemplate->id)
            ->select(
                "id",
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->main_header\"')) as value")
            )
            ->orderBy("id")
            ->get();

        $project_template_sub_headers = ProjectTemplateNameValuesNew::whereIn('id', $getDistinctRowIds)
            ->where('project_template_id', $projectTemplate->id)
            ->select(
                "id",
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$projectTemplate->sub_header\"')) as value")
            )
            ->orderBy("id")
            ->get();

        return response()->json(['message' => 'success', 'projectTemplate' => $projectTemplate, 'project_templates_count' => $project_templates_count, 'sub_headers' => $project_template_sub_headers, 'main_headers' => $project_template_main_headers]);

    }


    public function projectTemplateHeadValues(Request $request)
    {
        $projectTemplate = ProjectTemplate::where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)->first();
//        $projectTemplateHeadValues = DB::table('project_template_name_values')->where('project_template_id', $projectTemplate->id)
//            ->where('template_name_head_id', $request->id)->distinct('value')->pluck('value');

        $headIdToExtract = $request->id;

        $projectTemplateHeadValues = DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplate->id)
            ->whereRaw("JSON_EXTRACT(template_data_json, '$.\"$headIdToExtract\"') IS NOT NULL")
            ->select(DB::raw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$headIdToExtract\"')) as extracted_value"))
            ->pluck('extracted_value');

//        dd($projectTemplateHeadValues);
        return response()->json(['message' => "Success", 'related_head_values' => $projectTemplateHeadValues]);
    }
}
