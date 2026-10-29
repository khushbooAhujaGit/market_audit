<?php

namespace App\Services;

use App\Models\User;
use App\Models\Verifier;
use App\Models\VerifierAuditorLink;
use App\Models\VerifierRowAssign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VerifierAssignment
{
    public static function assignRows(array $verifierIds, int $templateId, int $activityId, array $rowIds): void
    {
        if (empty($verifierIds) || empty($rowIds)) return;

        $now = now();
        $records = [];
        foreach ($verifierIds as $verifierId) {
            foreach (array_unique($rowIds) as $rowId) {
                $records[] = [
                    'user_id'             => (int) $verifierId,
                    'project_template_id' => $templateId,
                    'activity_id'         => $activityId,
                    'row_id'              => (int) $rowId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }
        foreach (array_chunk($records, 1000) as $chunk) {
            DB::table('verifier_row_assigns')->insertOrIgnore($chunk);
        }
    }

    /**
     * Link an auditor to verifiers on a template/activity so rows the auditor creates
     * later are handed to them. $includeExistingRows also hands over rows the auditor
     * already holds there — only correct for templates without uploaded data, where
     * every row was created by an auditor.
     */
    public static function linkAuditor(array $verifierIds, int $auditorId, int $templateId, int $activityId, bool $includeExistingRows): void
    {
        if (empty($verifierIds)) return;

        $now = now();
        $links = [];
        foreach ($verifierIds as $verifierId) {
            $links[] = [
                'verifier_id'         => (int) $verifierId,
                'auditor_id'          => $auditorId,
                'project_template_id' => $templateId,
                'activity_id'         => $activityId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        DB::table('verifier_auditor_links')->insertOrIgnore($links);

        if ($includeExistingRows) {
            self::assignRows($verifierIds, $templateId, $activityId, self::auditorRowIds($auditorId, $templateId, $activityId));
        }
    }

    public static function assignAuditorCreatedRow(int $auditorId, int $templateId, array $activityIds, int $rowId): void
    {
        foreach (array_unique($activityIds) as $activityId) {
            $verifierIds = VerifierAuditorLink::where('auditor_id', $auditorId)
                ->where('project_template_id', $templateId)
                ->where('activity_id', $activityId)
                ->pluck('verifier_id')
                ->all();
            self::assignRows($verifierIds, $templateId, (int) $activityId, [$rowId]);
        }
    }

    /**
     * Narrow a template's row ids to what this user may verify for the activity: rows
     * assigned to them, plus — for assignments made before row-wise assignment — every
     * row up to the legacy cutoff. Super Admin, and users not assigned as a verifier on
     * this template at all, are not narrowed (unchanged behaviour).
     */
    public static function filterRowIds(User $user, int $templateId, int $activityId, Collection $rowIds): Collection
    {
        if ($user->hasRole('Super Admin')) return $rowIds;

        $templateEntries = Verifier::where('user_id', $user->id)
            ->where('project_template_name_id', $templateId)
            ->get(['legacy_max_row_id']);

        $assignedRowIds = VerifierRowAssign::where('user_id', $user->id)
            ->where('project_template_id', $templateId)
            ->where('activity_id', $activityId)
            ->pluck('row_id')
            ->flip();

        if ($templateEntries->isEmpty() && $assignedRowIds->isEmpty()) return $rowIds;

        $legacyCutoff = $templateEntries->max('legacy_max_row_id');

        return $rowIds->filter(fn($rowId) => $assignedRowIds->has($rowId)
            || ($legacyCutoff !== null && (int) $rowId <= (int) $legacyCutoff)
        )->values();
    }

    public static function forgetActivity(int $templateId, int $activityId): void
    {
        VerifierRowAssign::where('project_template_id', $templateId)->where('activity_id', $activityId)->delete();
        VerifierAuditorLink::where('project_template_id', $templateId)->where('activity_id', $activityId)->delete();
    }

    /** Verifiers hold this data assignment: it shows on their Data Assigned List and Assign Data page. */
    public static function recordDataAssign(array $verifierIds, int $dataAssignId, ?int $assignedBy): void
    {
        $verifierIds = array_values(array_unique(array_filter(array_map('intval', $verifierIds))));
        if (empty($verifierIds)) return;

        // Giving it again (e.g. more rows) moves its "given on" date (updated_at) and who gave it
        $now = now();
        DB::table('verifier_data_assigns')->upsert(array_map(fn($verifierId) => [
            'data_assign_id' => $dataAssignId,
            'verifier_id'    => $verifierId,
            'assigned_by'    => $assignedBy,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], $verifierIds), ['data_assign_id', 'verifier_id'], ['assigned_by', 'updated_at']);
    }

    /**
     * A row an auditor added gets its own data assignment: the verifiers linked to the auditor on that
     * template/activity hold it too, so it shows on their Data Assigned List (no "given by" — it is the
     * auditor's own assignment).
     */
    public static function recordForAuditor(int $dataAssignId, int $auditorId, int $templateId, int $activityId): void
    {
        $verifierIds = VerifierAuditorLink::where('auditor_id', $auditorId)
            ->where('project_template_id', $templateId)
            ->where('activity_id', $activityId)
            ->pluck('verifier_id')
            ->all();
        self::recordDataAssign($verifierIds, $dataAssignId, null);
    }

    /** Data assignments (of open projects) a verifier holds. */
    public static function assignedDataAssigns(int $verifierId): Collection
    {
        return DB::table('data_assigns as d')
            ->join('verifier_data_assigns as vd', 'vd.data_assign_id', '=', 'd.id')
            ->join('projects as p', 'p.id', '=', 'd.project_id')
            ->where('vd.verifier_id', $verifierId)
            ->where('p.is_completed', 0)
            ->select('d.id', 'd.project_id', 'd.project_template_id', 'd.activity_id', 'd.activity_group_id',
                'd.template_name_head_id', 'd.agency_id', 'd.company_user_id', 'd.is_outlet_assigned')
            ->orderByDesc('d.id')
            ->get();
    }

    /**
     * Rows of a template/activity a verifier was given and may hand to their auditors: rows
     * assigned to them, plus every row up to the cutoff of a template-wise (older) assignment.
     * Unlike filterRowIds, a verifier with nothing on the template gets nothing.
     */
    public static function ownRowIds(int $verifierId, int $templateId, int $activityId, ?array $within = null): array
    {
        $assigned = VerifierRowAssign::where('user_id', $verifierId)
            ->where('project_template_id', $templateId)
            ->where('activity_id', $activityId)
            ->when($within !== null, fn($q) => $q->whereIn('row_id', $within))
            ->pluck('row_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $cutoff = Verifier::where('user_id', $verifierId)
            ->where('project_template_name_id', $templateId)
            ->max('legacy_max_row_id');
        if ($cutoff === null) return $assigned;

        $legacy = $within !== null
            ? array_filter(array_map('intval', $within), fn($id) => $id <= (int) $cutoff)
            : DB::table('project_template_name_values_new')->where('project_template_id', $templateId)
                ->where('id', '<=', (int) $cutoff)->pluck('id')->map(fn($id) => (int) $id)->all();

        return array_values(array_unique(array_merge($assigned, $legacy)));
    }

    /** Rows given to a verifier on a template that no auditor has yet for that activity: [activity_id => [row ids]]. */
    public static function pendingRows(int $verifierId, int $templateId, array $activityIds): array
    {
        return self::pendingRowsByVerifier($templateId, $activityIds, [$verifierId])[$verifierId] ?? [];
    }

    /**
     * Rows given to verifiers on a template that none of that verifier's auditors has yet for the
     * activity (an auditor of another verifier on the same row doesn't count):
     * [verifier_id => [activity_id => [row ids]]]. $verifierIds null: every verifier.
     */
    public static function pendingRowsByVerifier(int $templateId, array $activityIds, ?array $verifierIds = null): array
    {
        if (empty($activityIds) || $verifierIds === []) return [];

        $given = VerifierRowAssign::where('project_template_id', $templateId)
            ->whereIn('activity_id', $activityIds)
            ->when($verifierIds !== null, fn($q) => $q->whereIn('user_id', $verifierIds))
            ->get(['user_id', 'activity_id', 'row_id']);
        if ($given->isEmpty()) return [];

        // Rows held by auditors linked to each verifier: "verifier|activity" => [row id => ...]
        $withOwnAuditor = DB::table('user_activity_data_assigns as u')
            ->join('user_audit_assigns as a', 'a.common_id', '=', 'u.common_id')
            ->join('verifier_auditor_links as l', function ($join) {
                $join->on('l.auditor_id', '=', 'u.user_id')
                    ->on('l.project_template_id', '=', 'u.project_template_id')
                    ->on('l.activity_id', '=', 'u.activity_id');
            })
            ->where('u.project_template_id', $templateId)
            ->whereIn('u.activity_id', $given->pluck('activity_id')->unique())
            ->whereIn('l.verifier_id', $given->pluck('user_id')->unique())
            ->select('l.verifier_id', 'u.activity_id', 'a.row_id')
            ->distinct()
            ->get()
            ->groupBy(fn($r) => $r->verifier_id . '|' . $r->activity_id)
            ->map(fn($rows) => $rows->pluck('row_id')->map(fn($id) => (int) $id)->flip());

        $pending = [];
        foreach ($given->groupBy('user_id') as $verifierId => $verifierRows) {
            foreach ($verifierRows->groupBy('activity_id') as $activityId => $rows) {
                $taken = $withOwnAuditor->get($verifierId . '|' . $activityId) ?? collect();
                $left = $rows->pluck('row_id')->map(fn($id) => (int) $id)->reject(fn($id) => $taken->has($id))->unique()->values()->all();
                if ($left) $pending[(int) $verifierId][(int) $activityId] = $left;
            }
        }
        return $pending;
    }

    /** Template without uploaded data: activities where the verifier has no auditor linked yet. */
    public static function activitiesWithoutAuditor(int $verifierId, int $templateId, array $activityIds): array
    {
        $linked = VerifierAuditorLink::where('verifier_id', $verifierId)
            ->where('project_template_id', $templateId)
            ->whereIn('activity_id', $activityIds)
            ->pluck('activity_id')
            ->map(fn($id) => (int) $id)
            ->all();
        return array_values(array_diff(array_map('intval', $activityIds), $linked));
    }

    private static function auditorRowIds(int $auditorId, int $templateId, int $activityId): array
    {
        return DB::table('user_activity_data_assigns as uada')
            ->join('user_audit_assigns as uaa', 'uaa.common_id', '=', 'uada.common_id')
            ->where('uada.user_id', $auditorId)
            ->where('uada.project_template_id', $templateId)
            ->where('uada.activity_id', $activityId)
            ->distinct()
            ->pluck('uaa.row_id')
            ->all();
    }
}
