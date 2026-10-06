<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Project "completed" (closed) state — see projects.is_completed.
 *
 * A completed project is hidden from auditors (APK + web panel) and from the
 * verifier queue, but stays reportable. Most auditor/verifier endpoints only
 * receive a row_id or project_template_id, so these helpers resolve those back
 * to the owning project (row → project_template → project).
 */
class ProjectCompletion
{
    /**
     * 'completed' | 'open' from a report page's ?view= query. Accepts the plain value and the
     * encrypted form EncryptedUrlGenerator produces when it is passed through route().
     */
    public static function reportView(\Illuminate\Http\Request $request): string
    {
        $raw = (string) $request->query('view', '');

        if ($raw !== '' && $raw !== 'completed') {
            try {
                $raw = \Illuminate\Support\Facades\Crypt::decryptString(urldecode($raw));
            } catch (\Throwable $e) {
                // not encrypted / tampered — treated as the default (open)
            }
        }

        return $raw === 'completed' ? 'completed' : 'open';
    }

    public static function projectIdForRow($rowId): ?int
    {
        if (!is_numeric($rowId)) return null;

        $id = DB::table('project_template_name_values_new as v')
            ->join('project_templates as pt', 'pt.id', '=', 'v.project_template_id')
            ->where('v.id', (int) $rowId)
            ->value('pt.project_id');

        return $id ? (int) $id : null;
    }

    public static function projectIdForTemplate($projectTemplateId): ?int
    {
        if (!is_numeric($projectTemplateId)) return null;

        $id = DB::table('project_templates')->where('id', (int) $projectTemplateId)->value('project_id');

        return $id ? (int) $id : null;
    }

    public static function projectIdForInstance($instanceId): ?int
    {
        if (!is_numeric($instanceId)) return null;

        $rowId = DB::table('activity_repeat_instances')->where('id', (int) $instanceId)->value('row_id');

        return $rowId ? self::projectIdForRow($rowId) : null;
    }

    public static function isCompleted(?int $projectId): bool
    {
        if (!$projectId) return false;

        return (bool) DB::table('projects')->where('id', $projectId)->value('is_completed');
    }

    /**
     * A project can only be marked completed once at least one auditor has been
     * assigned to it (user_activity_data_assigns → data_assigns.project_id).
     */
    public static function hasAssignedAuditors(int $projectId): bool
    {
        return DB::table('user_activity_data_assigns as uada')
            ->join('data_assigns as da', 'da.id', '=', 'uada.data_assign_id')
            ->where('da.project_id', $projectId)
            ->exists();
    }
}
