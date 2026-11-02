<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAwlDepoAuditReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "AWL Depo Audit" report (Custom Reports menu): mapped projects -> master data template -> distributor row.
 * One distributor in, the filled AWL DB Audit workbook out, plus (as chosen) a PDF, the Styled Report PDF and
 * a photo ZIP; see GenerateAwlDepoAuditReportJob and python-tools/awl_depo_audit.
 */
class AwlDepoAuditReportController extends Controller
{
    private const REPORT_KEY = 'awl_depo_audit';

    public function index()
    {
        $mappedIds = $this->mappedProjectIds();
        $canMap    = Auth::user()->can('AWL Depo Audit Report Map Projects');

        $projects = DB::table('projects')->whereNull('deleted_at')
            ->when(!$canMap, fn ($q) => $q->whereIn('id', $mappedIds))
            ->orderBy('project_name')->get(['id', 'project_name']);

        $recentRuns = DB::table('custom_report_runs')
            ->where('report_key', self::REPORT_KEY)->where('user_id', Auth::id())
            ->orderByDesc('id')->limit(5)->get();

        return view('masters.reports.awl_depo_audit', compact('projects', 'mappedIds', 'canMap', 'recentRuns'));
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

    /** Distributors (main header - sub header) of the chosen template, for the selection dropdown. */
    public function distributors(Request $request)
    {
        [, $rows] = $this->masterRows((int) $request->input('project_template_id'));

        return response()->json(array_map(fn ($r) => [
            'id'    => $r['row_id'],
            'label' => trim($r['header'] . ($r['sub_header'] !== '' ? ' - ' . $r['sub_header'] : '')),
        ], $rows));
    }

    /** Master data of the chosen template, searched and paginated for the distributor table. */
    public function rows(Request $request)
    {
        [$template, $rows, $columns] = $this->masterRows((int) $request->input('project_template_id'));

        if ($request->filled('row_id')) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['row_id'] === (int) $request->input('row_id')));
        }

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
                ->orderBy('id')->get(['id', 'row_id', 'xlsx_path', 'zip_path', 'pdf_path', 'styled_path']);
            foreach ($runs as $run) {
                if ($run->xlsx_path && is_file($run->xlsx_path)) {
                    $latest[$run->row_id] = $this->links($run);
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
            'book_stock'          => 'nullable|file|mimes:xlsx,xlsm,xls|max:20480',
            'physical_stock'      => 'nullable|file|mimes:xlsx,xlsm,xls|max:20480',
        ]);

        [$template, $rows] = $this->masterRows((int) $request->project_template_id);
        $match = collect($rows)->first(fn ($r) => $r['row_id'] === (int) $request->row_id);

        if (!$template || !$match) {
            return response()->json(['message' => 'That distributor was not found in the selected template.'], 422);
        }

        $options = [
            'pdf'    => $request->boolean('pdf'),
            'styled' => $request->boolean('styled'),
        ];

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

        // Both stock files are needed for the reconciliation; one alone is ignored, as in the desktop tool.
        if ($request->hasFile('book_stock') && $request->hasFile('physical_stock')) {
            $dir = storage_path('app/awl-depo-audit-reports/' . $runId);
            $options['book']     = $request->file('book_stock')->move($dir, 'book_stock.' . $request->file('book_stock')->getClientOriginalExtension())->getPathname();
            $options['physical'] = $request->file('physical_stock')->move($dir, 'physical_stock.' . $request->file('physical_stock')->getClientOriginalExtension())->getPathname();
        }

        DB::table('custom_report_runs')->where('id', $runId)->update(['options' => json_encode($options)]);

        GenerateAwlDepoAuditReportJob::dispatchAfterResponse($runId);

        return response()->json(['run_id' => $runId]);
    }

    public function status(int $run)
    {
        $row = $this->ownedRun($run);

        return response()->json([
            'status'  => $row->status,
            'message' => $row->message,
        ] + ($row->status === 'done' ? $this->links($row) : ['report_url' => null, 'pdf_url' => null, 'styled_url' => null, 'zip_url' => null]));
    }

    public function download(int $run, string $type)
    {
        $row  = $this->ownedRun($run);
        $path = match ($type) {
            'zip'    => $row->zip_path,
            'pdf'    => $row->pdf_path,
            'styled' => $row->styled_path,
            default  => $row->xlsx_path,
        };

        abort_unless($row->status === 'done' && $path && is_file($path), 404);

        return response()->download($path, basename($path));
    }

    /** Download links for the files a finished run produced. */
    private function links(object $run): array
    {
        $has = fn ($path) => $path && is_file($path);

        return [
            'report_url' => $has($run->xlsx_path) ? $this->downloadUrl($run->id, 'report') : null,
            'pdf_url'    => $has($run->pdf_path) ? $this->downloadUrl($run->id, 'pdf') : null,
            'styled_url' => $has($run->styled_path) ? $this->downloadUrl($run->id, 'styled') : null,
            'zip_url'    => $has($run->zip_path) ? $this->downloadUrl($run->id, 'zip') : null,
        ];
    }

    /** Plain URL: route() encrypts route parameters in this app, and these endpoints take the raw run id. */
    private function downloadUrl(int $runId, string $type): string
    {
        return url("custom-reports/awl-depo-audit/run/{$runId}/download/{$type}");
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

        return Auth::user()->can('AWL Depo Audit Report Map Projects')
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
