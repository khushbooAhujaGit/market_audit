<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateGokulReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Gokul Distributor Fitment" report (Custom Reports menu).
 *
 * Flow: mapped projects (saved once, pre-selected) -> master data template -> header value ->
 * sub-header value -> Generate. One distributor row in, one workbook + one photo ZIP out; see
 * GenerateGokulReportJob and python-tools/gokul/gokul_report.py.
 */
class GokulReportController extends Controller
{
    private const REPORT_KEY = 'gokul';

    public function index()
    {
        $mappedIds = $this->mappedProjectIds();
        $canMap    = Auth::user()->can('Gokul Report Map Projects');

        $projects = DB::table('projects')->whereNull('deleted_at')
            ->when(!$canMap, fn ($q) => $q->whereIn('id', $mappedIds))
            ->orderBy('project_name')->get(['id', 'project_name']);

        $recentRuns = DB::table('custom_report_runs')
            ->where('report_key', self::REPORT_KEY)->where('user_id', Auth::id())
            ->orderByDesc('id')->limit(5)->get();

        return view('masters.reports.gokul', compact('projects', 'mappedIds', 'canMap', 'recentRuns'));
    }

    public function saveMapping(Request $request)
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('project_ids', []))));
        $ids = DB::table('projects')->whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            DB::table('custom_report_project_maps')->where('report_key', self::REPORT_KEY)->delete();
            foreach ($ids as $id) {
                DB::table('custom_report_project_maps')->insert([
                    'report_key' => self::REPORT_KEY, 'project_id' => $id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['saved' => count($ids)]);
    }

    /** Master data templates of the chosen (mapped) projects. */
    public function templates(Request $request)
    {
        $projectIds = $this->allowedProjectIds((array) $request->input('project_ids', []));

        $rows = DB::table('project_templates as pt')
            ->join('template_names as tn', 'tn.id', '=', 'pt.template_name_id')
            ->join('projects as p', 'p.id', '=', 'pt.project_id')
            ->whereIn('pt.project_id', $projectIds)->where('pt.is_master', 1)
            ->whereNotNull('pt.main_header')->whereNotNull('pt.sub_header')
            ->orderBy('p.project_name')->orderBy('tn.template_name')
            ->get(['pt.id', 'tn.template_name', 'p.project_name']);

        return response()->json($rows->map(fn ($r) => [
            'id'    => $r->id,
            'label' => $r->template_name . ' (' . $r->project_name . ')',
        ]));
    }

    /** Master data of the chosen template, searched and paginated for the distributor table. */
    public function rows(Request $request)
    {
        [$template, $rows, $columns] = $this->masterRows((int) $request->input('project_template_id'));

        $search = mb_strtolower(trim((string) $request->input('search', '')));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower(implode(' ', $r['cells'])), $search)));
        }

        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);
        $total   = count($rows);
        $pages   = max((int) ceil($total / $perPage), 1);
        $page    = min(max((int) $request->input('page', 1), 1), $pages);
        $slice   = array_slice($rows, ($page - 1) * $perPage, $perPage);

        // Latest finished report of this user for each visible row, so its downloads are one click away.
        $latest = [];
        if ($template && $slice) {
            $runs = DB::table('custom_report_runs')
                ->where('report_key', self::REPORT_KEY)->where('user_id', Auth::id())
                ->where('project_template_id', $template->id)->where('status', 'done')
                ->whereIn('row_id', array_column($slice, 'row_id'))
                ->orderBy('id')->get(['id', 'row_id', 'xlsx_path', 'zip_path']);
            foreach ($runs as $run) {
                if ($run->xlsx_path && is_file($run->xlsx_path)) {
                    $latest[$run->row_id] = [
                        'report_url' => $this->downloadUrl($run->id, 'report'),
                        'zip_url'    => $this->downloadUrl($run->id, 'zip'),
                    ];
                }
            }
        }

        return response()->json([
            'columns'  => $columns,
            'rows'     => array_map(fn ($r) => [
                'row_id' => $r['row_id'], 'cells' => $r['cells'], 'latest' => $latest[$r['row_id']] ?? null,
            ], $slice),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'project_template_id' => 'required|integer',
            'row_id'              => 'required|integer',
        ]);

        [$template, $rows] = $this->masterRows((int) $request->project_template_id);
        $match = collect($rows)->first(fn ($r) => $r['row_id'] === (int) $request->row_id);

        if (!$template || !$match) {
            return response()->json(['message' => 'That distributor was not found in the selected template.'], 422);
        }

        $runId = DB::table('custom_report_runs')->insertGetId([
            'report_key'          => self::REPORT_KEY,
            'user_id'             => Auth::id(),
            'project_template_id' => $template->id,
            'row_id'              => $match['row_id'],
            'header_value'        => $match['header'],
            'sub_header_value'    => $match['sub_header'],
            'status'              => 'queued',
            'message'             => 'Starting',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        GenerateGokulReportJob::dispatchAfterResponse($runId);

        return response()->json(['run_id' => $runId]);
    }

    public function status(int $run)
    {
        $row = $this->ownedRun($run);

        return response()->json([
            'status'     => $row->status,
            'message'    => $row->message,
            'report_url' => $row->status === 'done' ? $this->downloadUrl($row->id, 'report') : null,
            'zip_url'    => $row->status === 'done' ? $this->downloadUrl($row->id, 'zip') : null,
        ]);
    }

    public function download(int $run, string $type)
    {
        $row  = $this->ownedRun($run);
        $path = $type === 'zip' ? $row->zip_path : $row->xlsx_path;

        abort_unless($row->status === 'done' && $path && is_file($path), 404);

        $name = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', ($row->sub_header_value ?: 'Distributor') . ' - Gokul Fitment ' . ($type === 'zip' ? 'with Photos.zip' : 'Report.xlsx'));

        return response()->download($path, $name);
    }

    /** Plain URL: route() encrypts route parameters in this app, and these endpoints take the raw run id. */
    private function downloadUrl(int $runId, string $type): string
    {
        return url("custom-reports/gokul/run/{$runId}/download/{$type}");
    }

    private function ownedRun(int $id): object
    {
        $row = DB::table('custom_report_runs')->where('report_key', self::REPORT_KEY)->where('id', $id)->first();
        abort_unless($row && ($row->user_id === Auth::id() || Auth::user()->hasRole('Super Admin')), 404);

        return $row;
    }

    private function mappedProjectIds(): array
    {
        return DB::table('custom_report_project_maps')->where('report_key', self::REPORT_KEY)
            ->pluck('project_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Users who cannot map projects may only use the mapped ones. */
    private function allowedProjectIds(array $requested): array
    {
        $requested = array_map('intval', $requested);

        return Auth::user()->can('Gokul Report Map Projects')
            ? $requested
            : array_values(array_intersect($requested, $this->mappedProjectIds()));
    }

    /**
     * @return array{0: object|null, 1: array<int, array{row_id:int, header:string, sub_header:string, cells:array<int,string>}>, 2: array<int,string>}
     */
    private function masterRows(int $projectTemplateId): array
    {
        $template = DB::table('project_templates')->where('id', $projectTemplateId)->where('is_master', 1)->first();
        if (!$template || !in_array((int) $template->project_id, $this->allowedProjectIds([$template->project_id]), true)) {
            return [null, [], []];
        }

        $heads = DB::table('template_name_heads')->where('template_name_id', $template->template_name_id)
            ->whereNull('deleted_at')->orderBy('id')->get(['id', 'template_head_name']);

        $rows = [];
        foreach (DB::table('project_template_name_values_new')->where('project_template_id', $template->id)
                     ->orderBy('id')->get(['id', 'template_data_json']) as $row) {
            $data = json_decode($row->template_data_json, true) ?: [];
            $rows[] = [
                'row_id'     => (int) $row->id,
                'header'     => trim((string) ($data[$template->main_header] ?? '')),
                'sub_header' => trim((string) ($data[$template->sub_header] ?? '')),
                'cells'      => $heads->map(fn ($h) => trim((string) ($data[$h->id] ?? '')))->all(),
            ];
        }

        return [$template, $rows, $heads->pluck('template_head_name')->all()];
    }
}
