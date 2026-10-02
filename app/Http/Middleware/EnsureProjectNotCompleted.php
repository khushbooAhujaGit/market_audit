<?php

namespace App\Http\Middleware;

use App\Helpers\EncryptHelper;
use App\Models\Project;
use App\Services\ProjectCompletion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks auditor/verifier requests that target a project the admin has marked
 * completed (projects.is_completed = 1). The project is resolved from whichever
 * identifier the endpoint carries — route params or request input:
 *
 *   project / project_id            → project id
 *   pt / project_template_id        → project_templates.id
 *   row_id / r                      → project_template_name_values_new.id
 *   instance_id                     → activity_repeat_instances.id
 *
 * Extra route params that hold a row id can be named as middleware arguments,
 * e.g. 'project.active:id' for GET api/getAllActivity/{id}.
 *
 * Super Admin is never blocked (admin views reuse some of these routes).
 */
class EnsureProjectNotCompleted
{
    public function handle(Request $request, Closure $next, string ...$rowIdParams): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $projectId = $this->resolveProjectId($request, $rowIdParams);

        if ($projectId && ProjectCompletion::isCompleted($projectId)) {
            $message = 'This project has been marked as completed and is no longer available.';

            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status'            => 403,
                    'message'           => $message,
                    'project_completed' => true,
                ], 403);
            }

            $fallback = $request->is('Verifier/*') ? 'user.verification.list' : 'user.projects';

            return redirect()->route($fallback)->with('project_completed', $message);
        }

        return $next($request);
    }

    private function resolveProjectId(Request $request, array $rowIdParams): ?int
    {
        $route = $request->route();

        $value = fn(string $key) => $this->plain($route?->parameter($key) ?? $request->input($key));

        $project = $route?->parameter('project');
        if ($project instanceof Project) {
            return (int) $project->id;
        }

        if (is_numeric($id = $this->plain($project) ?? $value('project_id'))) {
            return (int) $id;
        }

        foreach (['pt', 'project_template_id'] as $key) {
            if ($id = ProjectCompletion::projectIdForTemplate($value($key))) return $id;
        }

        foreach (array_merge(['row_id', 'r'], $rowIdParams) as $key) {
            if ($id = ProjectCompletion::projectIdForRow($value($key))) return $id;
        }

        return ProjectCompletion::projectIdForInstance($value('instance_id'));
    }

    // Web route params are normally decrypted already (DecryptRouteParams); this
    // covers any value that still arrives encrypted.
    private function plain($value)
    {
        return is_string($value) ? EncryptHelper::decrypt($value) : $value;
    }
}
