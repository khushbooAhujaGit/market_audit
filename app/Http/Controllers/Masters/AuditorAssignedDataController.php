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

        $scope = DataAssign::query();
        if (in_array($currentUserRole, ['Super Admin', 'Admin'])) {
            // Full visibility
        } elseif ($currentUserRole == 'Agency') {
            $getProjectDataWithAgency = Project::where('is_agency_required', 1)->first();
            if (!empty($getProjectDataWithAgency)) {
                $scope->where('project_id', $getProjectDataWithAgency->id);
            } else {
                $scope->whereRaw('0'); // empty result
            }
        } else {
            // Any other role (Auditor, Verifier, etc.) — show only their own assignments
            $assignedDataIds = UserActivityDataAssign::where('user_id', $currentUser->id)
                ->distinct()
                ->pluck('data_assign_id');
            $scope->whereIn('id', $assignedDataIds);
        }

        // One list row per assigned project template, combining all of its activities.
        // The earliest assignment supplies the displayed details; newest activity first.
        $data_assigned_values = (clone $scope)
            ->select('project_template_id', DB::raw('MIN(id) as first_id'), DB::raw('MAX(id) as latest_id'))
            ->groupBy('project_template_id')
            ->orderByDesc('latest_id')
            ->paginate(10);

        $templateIds = $data_assigned_values->pluck('project_template_id');

        $firstAssigns = DataAssign::with(['getProjectTemplate.getProject.getUnit.getZone.getCompany', 'templateName', 'TemplateHeadName'])
            ->whereIn('id', $data_assigned_values->pluck('first_id'))
            ->get()
            ->keyBy('id');

        $assignsByTemplate = (clone $scope)
            ->whereIn('project_template_id', $templateIds)
            ->with('activityName')
            ->orderBy('id')
            ->get(['id', 'project_template_id', 'activity_id'])
            ->groupBy('project_template_id');

        $auditorCounts = DB::table('user_activity_data_assigns as u')
            ->join('data_assigns as d', 'd.id', '=', 'u.data_assign_id')
            ->whereIn('d.project_template_id', $templateIds)
            ->groupBy('d.project_template_id')
            ->selectRaw('d.project_template_id, COUNT(DISTINCT u.user_id) as cnt')
            ->pluck('cnt', 'project_template_id');

        $data_assigned_values->getCollection()->transform(function ($group) use ($firstAssigns, $assignsByTemplate, $auditorCounts) {
            $group->setRelation('assign', $firstAssigns->get($group->first_id));
            $group->activities = ($assignsByTemplate->get($group->project_template_id) ?? collect())
                ->unique('activity_id')
                ->map(fn($a) => ['id' => $a->activity_id, 'name' => optional($a->activityName)->activity_name ?? 'Activity #' . $a->activity_id])
                ->values();
            $group->auditor_count = $auditorCounts->get($group->project_template_id, 0);
            return $group;
        });

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
            } elseif ($request->filled('project_template_id')) {
                // Combined list row: every activity assigned on this template
                $siblingIds = DataAssign::where('project_template_id', $request->project_template_id)
                    ->pluck('id')
                    ->toArray();
            } else {
                $siblingIds = [$request->data_assigned_id];
            }

            /* Get all assigned records across all matching DataAssigns */
            $user_list = UserActivityDataAssign::whereIn('data_assign_id', $siblingIds)
                ->select('user_id', 'common_id', 'activity_id')
                ->with('get_user_info')
                ->get()
                ->groupBy('user_id');

            $assignedValues = [];
            $getAssignedData = DataAssign::find($request->data_assigned_id);
            $headId = $getAssignedData->template_name_head_id;

            $templateId = (int) $request->project_template_id;

            $activityNames = \App\Models\Activity::whereIn('id', $user_list->flatten()->pluck('activity_id')->unique())
                ->pluck('activity_name', 'id');

            // Verifiers on this template from row-wise assignment (no legacy cutoff).
            $rowWiseVerifierIds = Verifier::where('project_template_name_id', $templateId)
                ->whereNull('legacy_max_row_id')
                ->pluck('user_id')
                ->all();

            // Old template-wise verifiers: each still covers every row of this template up
            // to its cutoff (for every activity), so they're shown together on one line.
            $legacyByCutoff = DB::table('verifiers as v')
                ->join('users', 'users.id', '=', 'v.user_id')
                ->where('v.project_template_name_id', $templateId)
                ->whereNotNull('v.legacy_max_row_id')
                ->get(['v.user_id', 'users.name', 'v.legacy_max_row_id'])
                ->unique('user_id')
                ->groupBy('legacy_max_row_id');

            // Lines are built per auditor + activity (one per row-wise verifier, one for the
            // template-wise verifiers, one for rows with no verifier), then lines identical
            // across activities are merged into one line listing all of those activities.
            foreach ($user_list as $userId => $records) {
                $userName = optional($records->first()->get_user_info)->name;

                $allRowIds = UserAuditAssigns::whereIn('common_id', $records->pluck('common_id')->unique())
                    ->distinct()
                    ->pluck('row_id')
                    ->all();

                $rows = DB::table('project_template_name_values_new')
                    ->whereIn('id', $allRowIds)
                    ->select(
                        'id as row_id',
                        'project_template_id',
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                    )
                    ->get()
                    ->keyBy('row_id');

                $merged = [];
                foreach ($records->groupBy('activity_id') as $activityId => $activityRecords) {
                    $rowIds = UserAuditAssigns::whereIn('common_id', $activityRecords->pluck('common_id')->unique())
                        ->distinct()
                        ->pluck('row_id')
                        ->all();

                    $rowsByVerifier = DB::table('verifier_row_assigns as v')
                        ->join('users', 'users.id', '=', 'v.user_id')
                        ->where('v.activity_id', $activityId)
                        ->whereIn('v.row_id', $rowIds)
                        ->select('v.user_id', 'users.name', 'v.row_id')
                        ->distinct()
                        ->get()
                        ->groupBy('user_id');

                    // Verifiers linked to this auditor (template without data) before any rows exist.
                    $linkedVerifiers = DB::table('verifier_auditor_links as l')
                        ->join('users', 'users.id', '=', 'l.verifier_id')
                        ->where('l.auditor_id', $userId)
                        ->where('l.project_template_id', $templateId)
                        ->where('l.activity_id', $activityId)
                        ->whereIn('l.verifier_id', $rowWiseVerifierIds)
                        ->pluck('users.name', 'l.verifier_id');

                    $lines = [];
                    $coveredRowIds = [];
                    foreach ($rowsByVerifier as $verifierId => $verifierRows) {
                        $verifierRowIds = $verifierRows->pluck('row_id')->unique()->all();
                        $coveredRowIds  = array_merge($coveredRowIds, $verifierRowIds);
                        $lines[] = ['verifier_id' => $verifierId, 'verifier_name' => $verifierRows->first()->name, 'template_wise' => false, 'row_ids' => $verifierRowIds];
                    }
                    foreach ($linkedVerifiers as $verifierId => $verifierName) {
                        if ($rowsByVerifier->has($verifierId)) continue;
                        $lines[] = ['verifier_id' => $verifierId, 'verifier_name' => $verifierName, 'template_wise' => false, 'row_ids' => []];
                    }
                    foreach ($legacyByCutoff as $cutoff => $legacyVerifiers) {
                        $legacyRowIds = collect($rowIds)
                            ->filter(fn($id) => $rows->has($id)
                                && (int) $rows[$id]->project_template_id === $templateId
                                && (int) $id <= (int) $cutoff)
                            ->values()
                            ->all();
                        if (empty($legacyRowIds)) continue;
                        $coveredRowIds = array_merge($coveredRowIds, $legacyRowIds);
                        $lines[] = [
                            'verifier_id'   => null,
                            'verifier_name' => $legacyVerifiers->pluck('name')->sort(SORT_NATURAL | SORT_FLAG_CASE)->implode(', '),
                            'template_wise' => true,
                            'row_ids'       => $legacyRowIds,
                        ];
                    }
                    $unverifiedRowIds = array_values(array_diff($rowIds, $coveredRowIds));
                    if (!empty($unverifiedRowIds) || empty($lines)) {
                        $lines[] = ['verifier_id' => null, 'verifier_name' => null, 'template_wise' => false, 'row_ids' => $unverifiedRowIds];
                    }

                    foreach ($lines as $line) {
                        $rowKey = $line['row_ids'];
                        sort($rowKey);
                        $key = implode('|', [$line['verifier_id'] ?? '', (int) $line['template_wise'], $line['verifier_name'] ?? '', implode(',', $rowKey)]);
                        $merged[$key] ??= $line + ['activity_ids' => []];
                        $merged[$key]['activity_ids'][] = $activityId;
                    }
                }

                $lines = array_map(fn($l) => [
                    'user_id'        => $userId,
                    'user_name'      => $userName,
                    'verifier_id'    => $l['verifier_id'],
                    'verifier_name'  => $l['verifier_name'],
                    'template_wise'  => $l['template_wise'],
                    'activity_names' => collect($l['activity_ids'])->map(fn($id) => $activityNames[$id] ?? 'Activity #' . $id)->unique()->values(),
                    'audits'         => $rows->only($l['row_ids'])->values(),
                ], array_values($merged));

                // Row-wise verifiers first, then template-wise, then rows with no verifier
                $rank = fn($l) => $l['template_wise'] ? 1 : ($l['verifier_name'] === null ? 2 : 0);
                usort($lines, fn($a, $b) => [$rank($a), strtolower((string) $a['verifier_name'])] <=> [$rank($b), strtolower((string) $b['verifier_name'])]);
                array_push($assignedValues, ...$lines);
            }

            usort($assignedValues, fn($a, $b) => strcasecmp((string) $a['user_name'], (string) $b['user_name']));
        }
        return response()->json([
            'message'        => "Success",
            'user_list'      => $user_list,
            'assignedValues' => $assignedValues,
        ]);
    }


    public function assignedDestroy(Request $request)
    {
        // activity_ids: every activity of the template (list row delete);
        // activity_id: a single activity (original request format).
        $request->validate([
            'project_template_id' => 'required|integer',
            'activity_id'         => 'required_without:activity_ids|integer',
            'activity_ids'        => 'required_without:activity_id|array',
            'activity_ids.*'      => 'integer',
        ]);

        $templateId  = (int) $request->project_template_id;
        $activityIds = $request->filled('activity_ids')
            ? array_map('intval', $request->activity_ids)
            : [(int) $request->activity_id];

        return DB::transaction(function () use ($templateId, $activityIds) {
            $deleted = 0;
            foreach (array_unique($activityIds) as $activityId) {
                if ($this->deleteActivityAssignment($templateId, $activityId)) $deleted++;
            }

            if ($deleted === 0) {
                return response()->json(['message' => 'No matching data assignments found'], 404);
            }
            return response()->json(['message' => 'Success']);
        });
    }

    private function deleteActivityAssignment(int $templateId, int $activityId): bool
    {
        // Fetch matching DataAssign rows once, grab both id and project_id from the same query
        $dataAssigns = DataAssign::where('activity_id', $activityId)
            ->where('project_template_id', $templateId)
            ->get(['id', 'project_id']);

        $dataAssignIds = $dataAssigns->pluck('id')->toArray();

        if (empty($dataAssignIds)) {
            return false;
        }

        $commonIds = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)
            ->distinct()->pluck('common_id')->toArray();

        $userIds = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)
            ->distinct()->pluck('user_id')->toArray();

        // Includes child templates assigned through "outlet assigned".
        $templateActivityPairs = UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)
            ->select('project_template_id', 'activity_id')->distinct()->get();

        UserAuditAssigns::whereIn('common_id', $commonIds)->delete();
        UserActivityDataAssign::whereIn('data_assign_id', $dataAssignIds)->delete();

        Verifier::whereIn('user_id', $userIds)
            ->where('project_template_name_id', $templateId)
            ->delete();

        \App\Services\VerifierAssignment::forgetActivity($templateId, $activityId);
        foreach ($templateActivityPairs as $pair) {
            \App\Services\VerifierAssignment::forgetActivity((int) $pair->project_template_id, (int) $pair->activity_id);
        }

        DataAssign::whereIn('id', $dataAssignIds)->delete();

        // Delete rows and activity answer data for THIS template only
        // (previously this incorrectly pulled ALL templates for the project)
        $rowIds = ProjectTemplateNameValuesNew::where('project_template_id', $templateId)
            ->pluck('id');

        if ($rowIds->isNotEmpty()) {
            ActivityInstanceClose::whereIn('row_id', $rowIds)
                ->where('activity_id', $activityId)->delete();

            ActivityRepeatInstance::whereIn('row_id', $rowIds)
                ->where('activity_id', $activityId)->delete();

            TempUserActivityAnswersData::whereIn('row_id', $rowIds)->delete();
        }

        return true;
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
