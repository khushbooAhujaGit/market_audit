<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateGodrejReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Godrej" report (Custom Reports menu): the Report Dump of a project's master template for a date
 * range, reshaped into the client's Required Format (one row per audit) plus a ZIP of the photos.
 * See GenerateGodrejReportJob for the column mapping.
 */
class GodrejReportController extends Controller
{
    private const REPORT_KEY = 'godrej';

    public function index()
    {
        $projects = DB::table('projects')->whereNull('deleted_at')->orderBy('project_name')->get(['id', 'project_name']);
        $recentRuns = DB::table('custom_report_runs')
            ->where('report_key', self::REPORT_KEY)->where('user_id', Auth::id())
            ->orderByDesc('id')->limit(10)->get();

        return view('masters.reports.godrej', compact('projects', 'recentRuns'));
    }

    /** Master templates of a project whose data is audited by a single activity (what the Report Dump needs). */
    public function templates(Request $request)
    {
        $rows = DB::table('project_templates as pt')
            ->join('template_names as tn', 'tn.id', '=', 'pt.template_name_id')
            ->where('pt.project_id', (int) $request->input('project_id'))->where('pt.is_master', 1)
            ->where('pt.activityType', 0)->whereNotNull('pt.activity_group_name_id_or_activity_id')
            ->orderBy('tn.template_name')->get(['pt.id', 'tn.template_name']);

        return response()->json($rows->map(fn ($r) => ['id' => $r->id, 'label' => $r->template_name]));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'project_template_id' => 'required|integer',
            'from_date'           => 'required|date',
            'to_date'             => 'required|date|after_or_equal:from_date',
        ]);

        $template = DB::table('project_templates')->where('id', $data['project_template_id'])->where('is_master', 1)->first();
        if (!$template) {
            return response()->json(['message' => 'Template not found.'], 422);
        }

        $runId = DB::table('custom_report_runs')->insertGetId([
            'report_key'          => self::REPORT_KEY,
            'user_id'             => Auth::id(),
            'project_template_id' => $template->id,
            'row_id'              => 0,
            'header_value'        => $data['from_date'],
            'sub_header_value'    => $data['to_date'],
            'status'              => 'queued',
            'message'             => 'Starting',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        GenerateGodrejReportJob::dispatchAfterResponse($runId);

        return response()->json(['run_id' => $runId]);
    }

    public function status(int $run)
    {
        $row = $this->ownedRun($run);

        return response()->json([
            'status'     => $row->status,
            'message'    => $row->message,
            'report_url' => $row->status === 'done' ? $this->downloadUrl($row->id, 'report') : null,
            'zip_url'    => $row->status === 'done' && $row->zip_path ? $this->downloadUrl($row->id, 'zip') : null,
        ]);
    }

    public function download(int $run, string $type)
    {
        $row  = $this->ownedRun($run);
        $path = $type === 'zip' ? $row->zip_path : $row->xlsx_path;

        abort_unless($row->status === 'done' && $path && is_file($path), 404);

        $period = $row->header_value === $row->sub_header_value ? $row->header_value : "{$row->header_value} to {$row->sub_header_value}";

        return response()->download($path, "Godrej Asset Verification {$period}" . ($type === 'zip' ? ' Photos.zip' : '.xlsx'));
    }

    /** Plain URL: route() encrypts route parameters in this app, and these endpoints take the raw run id. */
    private function downloadUrl(int $runId, string $type): string
    {
        return url("custom-reports/godrej/run/{$runId}/download/{$type}");
    }

    private function ownedRun(int $id): object
    {
        $row = DB::table('custom_report_runs')->where('report_key', self::REPORT_KEY)->where('id', $id)->first();
        abort_unless($row && ($row->user_id === Auth::id() || Auth::user()->hasRole('Super Admin')), 404);

        return $row;
    }
}
