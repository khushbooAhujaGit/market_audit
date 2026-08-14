<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInfiltrationReportJob;
use App\Jobs\UploadInfiltrationReportToPersonalOneDriveJob;
use App\Jobs\UploadInfiltrationReportToSharedOneDriveJob;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateName;
use App\Models\Verifier;
use App\Models\User;
use App\Services\OneDrivePersonalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * Dedicated page for the "Infiltration Audit Report" — runs the standard report
 * generator restricted to exactly one activity (main sheet + one instance sheet,
 * which is what the external Infiltration Audit Report Tool expects), then hands
 * the result to that Python tool and emails back the reformatted report + outlet
 * photos. Kept separate from the general-purpose Get Report page because that page
 * allows multi-activity selection, which this tool can't handle.
 */
class InfiltrationReportController extends Controller
{
    public function index()
    {
        // Same role-scoped project/template lists as ReportController::index() —
        // duplicated rather than shared, matching this codebase's existing pattern
        // of near-identical per-page copies (see ReportController::index() vs
        // project_report()).
        $activities       = Activity::all();
        $activity_groups  = ActivityGroup::all();
        $currentuser      = User::find(Auth::user()->id);
        $currentUserRole  = $currentuser->getRoleNames()->first();

        // Only projects explicitly flagged for it (via the "Infiltration Report
        // Applicable" checkbox on the project form) should appear here — this page
        // is for a specific HCCB-style report layout, not every project.
        if ($currentuser->is_agency_user == 1 || $currentUserRole == 'Agency') {
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(function ($q) use ($projectsIds) {
                    $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)
                      ->orWhere('agency_id', Auth::user()->agency_user_id);
                })
                ->where('is_infiltration_report_applicable', 1)
                ->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        } elseif ($currentUserRole == 'Super Admin') {
            $template_names = TemplateName::all();
            $projects = Project::where('is_infiltration_report_applicable', 1)->get();
        } else {
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(function ($q) use ($projectsIds) {
                    $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)
                      ->orWhere('agency_id', Auth::user()->agency_user_id);
                })
                ->where('is_infiltration_report_applicable', 1)
                ->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }

        $oneDriveConnected = app(OneDrivePersonalService::class)->isConnected($currentuser);

        return view('masters.reports.infiltration', compact('projects', 'template_names', 'activities', 'activity_groups', 'oneDriveConnected'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'project_id'       => 'required',
            'template_name_id' => 'required',
            'from_date'        => 'required',
            'to_date'          => 'required',
        ]);

        $activityIds = array_values(array_filter((array) $request->input('activity_id', [])));
        if ($error = $this->validateSingleActivitySelection($validated, $activityIds)) {
            return back()->withErrors(['activity_id' => $error])->withInput();
        }

        $validated['data_get_helper'] = 'all';

        GenerateInfiltrationReportJob::dispatch($validated, $request->all(), Auth::user());

        return back()->with('message', 'Generating your Infiltration Audit report — you will receive it by email shortly.');
    }

    /**
     * Runs the exact same generation pipeline as generate(), but synchronously in this
     * request instead of via the queue, and streams the resulting zip straight back
     * instead of emailing it. Kept as a separate route (rather than a "mode" flag on
     * generate()) so the async/email path is unaffected.
     *
     * This can take several minutes for projects with many outlets, since photos are
     * downloaded one at a time — set_time_limit() raises PHP's own limit to match the
     * queued job's timeout, but that does NOT override any web-server/reverse-proxy
     * level timeout (e.g. a shared-hosting request cap around 60s), which is outside
     * PHP's control and may still cut this off for very large projects.
     */
    public function download(Request $request)
    {
        $validated = $request->validate([
            'project_id'       => 'required',
            'template_name_id' => 'required',
            'from_date'        => 'required',
            'to_date'          => 'required',
        ]);

        $activityIds = array_values(array_filter((array) $request->input('activity_id', [])));
        if ($error = $this->validateSingleActivitySelection($validated, $activityIds)) {
            return back()->withErrors(['activity_id' => $error])->withInput();
        }

        $validated['data_get_helper'] = 'all';

        set_time_limit(900);

        $job = new GenerateInfiltrationReportJob($validated, $request->all(), Auth::user());

        try {
            $paths = $job->generateZip();
        } catch (\Throwable $e) {
            return back()->withErrors([
                'activity_id' => 'Report generation failed: ' . $e->getMessage(),
            ])->withInput();
        }

        @unlink($paths['xlsxPath']);
        File::deleteDirectory($paths['outputDir']);

        return response()->download($paths['zipPath'], 'Infiltration Audit Report.zip')->deleteFileAfterSend(true);
    }

    /**
     * Queues the same generation pipeline as generate(), but uploads the result into
     * an "Infiltration Report" folder in the user's own OneDrive (delegated OAuth —
     * see OneDrivePersonalService), created automatically if it doesn't exist,
     * instead of emailing it. Requires the user to have already connected OneDrive
     * via OneDriveController::connect().
     */
    public function uploadToPersonalOneDrive(Request $request)
    {
        $validated = $request->validate([
            'project_id'       => 'required',
            'template_name_id' => 'required',
            'from_date'        => 'required',
            'to_date'          => 'required',
        ]);

        $activityIds = array_values(array_filter((array) $request->input('activity_id', [])));
        if ($error = $this->validateSingleActivitySelection($validated, $activityIds)) {
            return back()->withErrors(['activity_id' => $error])->withInput();
        }

        if (!app(OneDrivePersonalService::class)->isConnected(Auth::user())) {
            return back()->withErrors([
                'activity_id' => 'Please connect OneDrive first using the button above.',
            ])->withInput();
        }

        $validated['data_get_helper'] = 'all';

        UploadInfiltrationReportToPersonalOneDriveJob::dispatch($validated, $request->all(), Auth::user());

        return back()->with('message', 'Uploading your Infiltration Audit report to your OneDrive — you will receive a confirmation email shortly.');
    }

    /**
     * Same as uploadToPersonalOneDrive(), but uploads into one fixed account's
     * OneDrive via app-only auth (see OneDriveSharedService) instead of the
     * requesting user's own — no per-user connection required.
     */
    public function uploadToSharedOneDrive(Request $request)
    {
        $validated = $request->validate([
            'project_id'       => 'required',
            'template_name_id' => 'required',
            'from_date'        => 'required',
            'to_date'          => 'required',
        ]);

        $activityIds = array_values(array_filter((array) $request->input('activity_id', [])));
        if ($error = $this->validateSingleActivitySelection($validated, $activityIds)) {
            return back()->withErrors(['activity_id' => $error])->withInput();
        }

        $validated['data_get_helper'] = 'all';

        UploadInfiltrationReportToSharedOneDriveJob::dispatch($validated, $request->all(), Auth::user());

        return back()->with('message', 'Uploading your Infiltration Audit report to the shared OneDrive — you will receive a confirmation email shortly.');
    }

    /**
     * Shared by generate() and download(): confirms exactly one activity was submitted
     * and that it's a genuine single-activity template — the picker page only
     * disables/hides group-activity templates client-side (JS), so this can't be
     * trusted without a server-side re-check. Returns an error message, or null if valid.
     */
    private function validateSingleActivitySelection(array $validated, array $activityIds): ?string
    {
        // This tool only ever reads the first two worksheets of the generated report
        // (main sheet + one activity's instance sheet), so exactly one activity must
        // be selected — more than one would silently produce extra sheets the tool
        // never looks at, and the wrong one might land in the "sheet 2" position.
        if (count($activityIds) !== 1) {
            return 'Please select exactly one activity for the Infiltration Audit report.';
        }

        $projectTemplate = ProjectTemplate::where('project_id', $validated['project_id'])
            ->where('template_name_id', $validated['template_name_id'])
            ->first();

        if (!$projectTemplate || (int) $projectTemplate->activityType !== 0) {
            return 'This data template is a group-activity template. The Infiltration Audit Report Tool only supports single-activity templates.';
        }

        if ((int) $activityIds[0] !== (int) $projectTemplate->activity_group_name_id_or_activity_id) {
            return 'Selected activity does not match this data template\'s mapped activity.';
        }

        return null;
    }
}
