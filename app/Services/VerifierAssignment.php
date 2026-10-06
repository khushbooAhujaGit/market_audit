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
