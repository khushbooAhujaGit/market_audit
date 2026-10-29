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
        $verifierId = $currentUserRole == 'Verifier' ? (int) $currentUser->id : null;
        if (in_array($currentUserRole, ['Super Admin', 'Admin'])) {
            // Full visibility
        } elseif ($verifierId) {
            // Assignments an admin gave the verifier (with or without auditors) and the ones they made
            $scope->whereIn('id', DB::table('verifier_data_assigns')->where('verifier_id', $verifierId)->select('data_assign_id'));
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

        // One list row per assigned project template and assignment date (newest date first),
        // showing the activities and auditors assigned on that date.
        $data_assigned_values = DB::query()
            ->fromSub($this->assignmentEvents((clone $scope)->select('id'), $verifierId), 'e')
            ->select(
                'project_template_id',
                'assigned_on',
                DB::raw('MIN(data_assign_id) as first_id'),
                DB::raw('MAX(data_assign_id) as latest_id'),
                DB::raw('COUNT(DISTINCT user_id) as auditor_count'),
                DB::raw('GROUP_CONCAT(DISTINCT activity_id) as activity_ids')
            )
            ->groupBy('project_template_id', 'assigned_on')
            ->orderByDesc('assigned_on')
            ->orderByDesc('latest_id')
            ->paginate(10);

        $templateIds = $data_assigned_values->pluck('project_template_id')->unique();

        $firstAssigns = DataAssign::with(['getProjectTemplate.getProject.getUnit.getZone.getCompany', 'templateName', 'TemplateHeadName'])
            ->whereIn('id', $data_assigned_values->pluck('first_id'))
            ->get()
            ->keyBy('id');

        // Every activity of the template (any date): delete still removes the whole template's assignment.
        $assignsByTemplate = (clone $scope)
            ->whereIn('project_template_id', $templateIds)
            ->orderBy('id')
            ->get(['id', 'project_template_id', 'activity_id'])
            ->groupBy('project_template_id');

        $activityNames = \App\Models\Activity::whereIn('id', $assignsByTemplate->flatten()->pluck('activity_id')->unique())
            ->pluck('activity_name', 'id');

        // Rows given to verifiers that no auditor has yet, per template (shown on the template's newest line)
        $pendingByTemplate = [];
        $withoutData = DB::table('project_templates')->whereIn('id', $templateIds)->where('with_data', 0)->pluck('id')->flip();
        foreach ($templateIds as $templateId) {
            $assigns = $assignsByTemplate->get($templateId) ?? collect();
            $activityIds = $assigns->pluck('activity_id')->map(fn($id) => (int) $id)->unique()->values()->all();
            $holders = DB::table('verifier_data_assigns')->whereIn('data_assign_id', $assigns->pluck('id'))
                ->when($verifierId, fn($q) => $q->where('verifier_id', $verifierId))
                ->distinct()->pluck('verifier_id')->map(fn($id) => (int) $id)->all();

            $byVerifier = [];
            if ($withoutData->has($templateId)) {
                foreach ($holders as $holder) {
                    if (\App\Services\VerifierAssignment::activitiesWithoutAuditor($holder, (int) $templateId, $activityIds)) $byVerifier[$holder] = 0;
                }
            } else {
                foreach (\App\Services\VerifierAssignment::pendingRowsByVerifier((int) $templateId, $activityIds, $holders) as $holder => $rowsByActivity) {
                    $byVerifier[$holder] = collect($rowsByActivity)->flatten()->unique()->count();
                }
            }
            if ($byVerifier) {
                $pendingByTemplate[$templateId] = [
                    'rows'      => array_sum($byVerifier),
                    'with_data' => !$withoutData->has($templateId),
                    'verifiers' => User::whereIn('id', array_keys($byVerifier))->orderBy('name')->pluck('name')->all(),
                ];
            }
        }
        $shownPending = [];

        $data_assigned_values->getCollection()->transform(function ($group) use ($firstAssigns, $assignsByTemplate, $activityNames, $pendingByTemplate, &$shownPending) {
            $group->pending = null;
            if (isset($pendingByTemplate[$group->project_template_id]) && !isset($shownPending[$group->project_template_id])) {
                $group->pending = $pendingByTemplate[$group->project_template_id];
                $shownPending[$group->project_template_id] = true;
            }
            // Plain query rows (grouped by template + date), not models: set the first assignment as a property
            $group->assign = $firstAssigns->get($group->first_id);
            $dateActivityIds = array_map('intval', explode(',', (string) $group->activity_ids));
            $group->activities = ($assignsByTemplate->get($group->project_template_id) ?? collect())
                ->pluck('activity_id')
                ->unique()
                ->filter(fn($id) => in_array((int) $id, $dateActivityIds, true))
                ->map(fn($id) => ['id' => $id, 'name' => $activityNames[$id] ?? 'Activity #' . $id])
                ->values();
            $group->template_activity_ids = ($assignsByTemplate->get($group->project_template_id) ?? collect())
                ->pluck('activity_id')->unique()->values();
            return $group;
        });

        $isVerifier = (bool) $verifierId;
        return view('masters.data_templates.data_assigned', compact('data_assigned_values', 'isVerifier'));
    }

    /**
     * One record per assigned row with the date it was assigned (data_assign_id, project_template_id,
     * activity_id, user_id, common_id, row_id, assigned_on). An auditor without rows yet, and a
     * data assignment without auditors, appear once with a NULL row_id. Rows of a template without
     * data are created by the auditor, so they count from the date the auditor was assigned.
     * Giving an assignment to a verifier (with or without auditors) also counts on that date.
     * $verifierId: only that verifier's auditors (verifier_auditor_links) and assignments.
     */
    private function assignmentEvents($dataAssignIds, ?int $verifierId = null)
    {
        $rows = DB::table('data_assigns as d')
            ->leftJoin('user_activity_data_assigns as u', 'u.data_assign_id', '=', 'd.id')
            ->leftJoin('project_templates as pt', 'pt.id', '=', 'u.project_template_id')
            ->leftJoin('user_audit_assigns as a', 'a.common_id', '=', 'u.common_id')
            ->whereIn('d.id', $dataAssignIds)
            ->when($verifierId, fn($q) => $q->where(fn($w) => $w->whereNull('u.user_id')->orWhereExists(fn($e) => $e
                ->select(DB::raw(1))
                ->from('verifier_auditor_links as l')
                ->where('l.verifier_id', $verifierId)
                ->whereColumn('l.auditor_id', 'u.user_id')
                ->whereColumn('l.project_template_id', 'u.project_template_id')
                ->whereColumn('l.activity_id', 'u.activity_id'))))
            ->select('d.id as data_assign_id', 'd.project_template_id', 'd.activity_id', 'u.user_id', 'u.common_id', 'a.row_id')
            ->selectRaw('DATE(CASE
                WHEN u.id IS NULL THEN d.created_at
                WHEN a.id IS NULL OR pt.with_data = 0 THEN u.created_at
                ELSE a.created_at END) as assigned_on');

        // Assignments recorded before verifiers were tracked per assignment have no assigned_by
        $givenToVerifiers = DB::table('verifier_data_assigns as vd')
            ->join('data_assigns as d', 'd.id', '=', 'vd.data_assign_id')
            ->whereIn('d.id', $dataAssignIds)
            ->whereNotNull('vd.assigned_by')
            ->when($verifierId, fn($q) => $q->where('vd.verifier_id', $verifierId))
            ->select('d.id as data_assign_id', 'd.project_template_id', 'd.activity_id')
            ->selectRaw('NULL as user_id, NULL as common_id, NULL as row_id, DATE(vd.updated_at) as assigned_on');

        return $rows->unionAll($givenToVerifiers);
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

            // A dated list row: only the auditors and rows assigned on that date.
            $rowIdsByCommon = null;
            $assignedOn = $request->input('assigned_on');
            if ($assignedOn && preg_match('/^\d{4}-\d{2}-\d{2}$/', $assignedOn)) {
                $rowIdsByCommon = DB::query()
                    ->fromSub($this->assignmentEvents($siblingIds), 'e')
                    ->where('assigned_on', $assignedOn)
                    ->whereNotNull('common_id')
                    ->get(['common_id', 'row_id'])
                    ->groupBy('common_id')
                    ->map(fn($events) => $events->pluck('row_id')->filter()->unique()->values()->all());
            }
            $rowIdsFor = fn($commonIds) => $rowIdsByCommon !== null
                ? collect($commonIds)->flatMap(fn($c) => $rowIdsByCommon->get($c, []))->unique()->values()->all()
                : UserAuditAssigns::whereIn('common_id', $commonIds)->distinct()->pluck('row_id')->all();

            /* Get all assigned records across all matching DataAssigns */
            $user_list = UserActivityDataAssign::whereIn('data_assign_id', $siblingIds)
                ->when($rowIdsByCommon !== null, fn($q) => $q->whereIn('common_id', $rowIdsByCommon->keys()))
                ->select('user_id', 'common_id', 'activity_id', 'project_template_id')
                ->with('get_user_info')
                ->get()
                ->groupBy('user_id');

            $assignedValues = [];
            $getAssignedData = DataAssign::find($request->data_assigned_id);
            $headId = $getAssignedData->template_name_head_id;

            $templateId = (int) $request->project_template_id;

            // Main/Sub Header and name of every template of the project (rows can also be outlet rows)
            $rowHeaders = DB::table('project_templates as pt')
                ->leftJoin('template_names as tn', 'tn.id', '=', 'pt.template_name_id')
                ->where('pt.project_id', DB::table('project_templates')->where('id', $templateId ?: $getAssignedData->project_template_id)->value('project_id'))
                ->get(['pt.id', 'pt.main_header', 'pt.sub_header', 'tn.template_name'])
                ->keyBy('id');

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

            // A Verifier sees only the lines they verify
            $viewerVerifierId = Auth::user()->hasRole('Verifier') ? (int) Auth::id() : null;

            // Lines are built per auditor + activity (one per row-wise verifier, one for the
            // template-wise verifiers, one for rows with no verifier), then lines identical
            // across activities are merged into one line listing all of those activities.
            foreach ($user_list as $userId => $records) {
                $userName = optional($records->first()->get_user_info)->name;

                $allRowIds = $rowIdsFor($records->pluck('common_id')->unique());

                $rows = $this->rowLabels($allRowIds, $headId, $rowHeaders);

                $merged = [];
                foreach ($records->groupBy('activity_id') as $activityId => $activityRecords) {
                    $rowIds = $rowIdsFor($activityRecords->pluck('common_id')->unique());

                    $rowsByVerifier = DB::table('verifier_row_assigns as v')
                        ->join('users', 'users.id', '=', 'v.user_id')
                        ->where('v.activity_id', $activityId)
                        ->whereIn('v.row_id', $rowIds)
                        ->select('v.user_id', 'users.name', 'v.row_id')
                        ->distinct()
                        ->get()
                        ->groupBy('user_id');

                    // Only this auditor's own verifiers: the same rows can also be given to another
                    // verifier for their own auditors (e.g. on another date). Auditors with no verifier
                    // links (older assignments) keep every verifier of their rows.
                    $auditorVerifierIds = DB::table('verifier_auditor_links')
                        ->where('auditor_id', $userId)
                        ->whereIn('project_template_id', $activityRecords->pluck('project_template_id')->unique())
                        ->where('activity_id', $activityId)
                        ->pluck('verifier_id')
                        ->all();
                    if ($auditorVerifierIds) {
                        $rowsByVerifier = $rowsByVerifier->only($auditorVerifierIds);
                    }

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
                            'legacy_ids'    => $legacyVerifiers->pluck('user_id')->map(fn($id) => (int) $id)->all(),
                        ];
                    }
                    $unverifiedRowIds = array_values(array_diff($rowIds, $coveredRowIds));
                    if (!empty($unverifiedRowIds) || empty($lines)) {
                        $lines[] = ['verifier_id' => null, 'verifier_name' => null, 'template_wise' => false, 'row_ids' => $unverifiedRowIds];
                    }
                    if ($viewerVerifierId) {
                        $lines = array_values(array_filter($lines, fn($line) => (int) $line['verifier_id'] === $viewerVerifierId
                            || in_array($viewerVerifierId, $line['legacy_ids'] ?? [], true)));
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
                    'pending'        => false,
                    'activity_names' => collect($l['activity_ids'])->map(fn($id) => $activityNames[$id] ?? 'Activity #' . $id)->unique()->values(),
                    'audits'         => $rows->only($l['row_ids'])->values(),
                ], array_values($merged));

                // Row-wise verifiers first, then template-wise, then rows with no verifier
                $rank = fn($l) => $l['template_wise'] ? 1 : ($l['verifier_name'] === null ? 2 : 0);
                usort($lines, fn($a, $b) => [$rank($a), strtolower((string) $a['verifier_name'])] <=> [$rank($b), strtolower((string) $b['verifier_name'])]);
                array_push($assignedValues, ...$this->splitOutletLines($lines, $templateId ?: (int) $getAssignedData->project_template_id, $rowHeaders));
            }

            usort($assignedValues, fn($a, $b) => strcasecmp((string) $a['user_name'], (string) $b['user_name']));

            // Rows given to a verifier that no auditor has yet, after the auditors' lines
            array_push($assignedValues, ...$this->splitOutletLines(
                $this->pendingLines($siblingIds, $templateId, $headId, $rowHeaders, $viewerVerifierId),
                $templateId ?: (int) $getAssignedData->project_template_id,
                $rowHeaders
            ));
        }
        return response()->json([
            'message'        => "Success",
            'user_list'      => $user_list,
            'assignedValues' => $assignedValues,
        ]);
    }


    /**
     * Each row shown by its own template's Main Header (and Sub Header), e.g. the outlet name: the
     * column the assignment was made on (e.g. "Status") is often the same for every row and can't
     * tell rows apart. Keyed by row id.
     */
    private function rowLabels(array $rowIds, $headId, $rowHeaders)
    {
        return DB::table('project_template_name_values_new')
            ->whereIn('id', $rowIds)
            ->get(['id', 'project_template_id', 'template_data_json'])
            ->map(function ($row) use ($headId, $rowHeaders) {
                $data   = json_decode($row->template_data_json, true) ?: [];
                $pt     = $rowHeaders[$row->project_template_id] ?? null;
                $main   = trim((string) ($pt ? ($data[$pt->main_header] ?? '') : ''));
                $sub    = trim((string) ($pt ? ($data[$pt->sub_header] ?? '') : ''));
                $chosen = trim((string) ($data[$headId] ?? ''));
                $label  = $main !== '' ? $main . ($sub !== '' && $sub !== $main ? " ({$sub})" : '') : ($chosen !== '' ? $chosen : "Row {$row->id}");
                return (object) [
                    'row_id'              => $row->id,
                    'project_template_id' => $row->project_template_id,
                    'head_value'          => $label,
                    'assigned_value'      => $chosen,
                ];
            })
            ->keyBy('row_id');
    }

    /**
     * Outlet rows ("Assign Outlets Also") are stored under the distributor's assignment: each popup line
     * is split by template, the distributor rows first, then one line per outlet template (outlet_template
     * = its name; null on the distributor line).
     */
    private function splitOutletLines(array $lines, int $templateId, $rowHeaders): array
    {
        $out = [];
        foreach ($lines as $line) {
            $byTemplate = collect($line['audits'])->groupBy(fn($audit) => (int) $audit->project_template_id);
            if ($byTemplate->keys()->diff([$templateId])->isEmpty()) {
                $out[] = $line + ['outlet_template' => null];
                continue;
            }
            $order = $byTemplate->keys()->sortBy(fn($id) => [$id === $templateId ? 0 : 1, (string) ($rowHeaders[$id]->template_name ?? '')]);
            foreach ($order as $id) {
                $out[] = array_merge($line, [
                    'audits'          => $byTemplate[$id]->values(),
                    'outlet_template' => $id === $templateId ? null : ($rowHeaders[$id]->template_name ?? 'Template #' . $id),
                ]);
            }
        }
        return $out;
    }

    /** Popup lines for rows given to verifiers of these assignments that no auditor has yet. */
    private function pendingLines(array $dataAssignIds, int $templateId, $headId, $rowHeaders, ?int $verifierId): array
    {
        $activityIds = DataAssign::whereIn('id', $dataAssignIds)->pluck('activity_id')->map(fn($id) => (int) $id)->unique()->values()->all();
        $holders = DB::table('verifier_data_assigns')->whereIn('data_assign_id', $dataAssignIds)
            ->when($verifierId, fn($q) => $q->where('verifier_id', $verifierId))
            ->distinct()->pluck('verifier_id')->map(fn($id) => (int) $id)->all();
        if (!$holders || !$activityIds) return [];

        $withData = (int) DB::table('project_templates')->where('id', $templateId)->value('with_data') !== 0;
        $names = User::whereIn('id', $holders)->pluck('name', 'id');
        $activityNames = \App\Models\Activity::whereIn('id', $activityIds)->pluck('activity_name', 'id');
        $activityLabels = fn($ids) => collect($ids)->map(fn($id) => $activityNames[$id] ?? 'Activity #' . $id)->unique()->values();

        $lines = [];
        if ($withData) {
            foreach (\App\Services\VerifierAssignment::pendingRowsByVerifier($templateId, $activityIds, $holders) as $holder => $rowsByActivity) {
                $rowIds = collect($rowsByActivity)->flatten()->unique()->values()->all();
                $lines[] = [
                    'user_id' => null, 'user_name' => null, 'pending' => true,
                    'verifier_id' => $holder, 'verifier_name' => $names[$holder] ?? null, 'template_wise' => false,
                    'activity_names' => $activityLabels(array_keys($rowsByActivity)),
                    'audits' => $this->rowLabels($rowIds, $headId, $rowHeaders)->values(),
                ];
            }
        } else {
            foreach ($holders as $holder) {
                $missing = \App\Services\VerifierAssignment::activitiesWithoutAuditor($holder, $templateId, $activityIds);
                if (!$missing) continue;
                $lines[] = [
                    'user_id' => null, 'user_name' => null, 'pending' => true,
                    'verifier_id' => $holder, 'verifier_name' => $names[$holder] ?? null, 'template_wise' => false,
                    'activity_names' => $activityLabels($missing),
                    'audits' => [],
                ];
            }
        }

        usort($lines, fn($a, $b) => strcasecmp((string) $a['verifier_name'], (string) $b['verifier_name']));
        return $lines;
    }

    public function assignedDestroy(Request $request)
    {
        // Deleting a whole template assignment is for admins; a verifier removes their auditors' rows in the popup
        abort_if(Auth::user()->hasRole('Verifier'), 403, 'Verifiers cannot delete a whole assignment.');

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

        DB::table('verifier_data_assigns')->whereIn('data_assign_id', $dataAssignIds)->delete();
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

            // A Verifier removes rows only from their own auditors
            if (Auth::user()->hasRole('Verifier')
                && !User::where('id', $userId)->where('verifier_user_id', Auth::id())->exists()) {
                return response()->json(['status' => 'error', 'message' => 'You can remove rows only from your own auditors.']);
            }

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
