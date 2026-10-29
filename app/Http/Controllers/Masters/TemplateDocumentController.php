<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Jobs\FetchTemplateDocumentsJob;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateDocument;
use App\Services\TemplateDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Template Documents: the files named in a template's file columns. Files are uploaded here
 * (several at once, or a .zip) and matched by name; files from links are downloaded after the
 * data upload and can be retried here. The page loads its templates, table and uploads by ajax.
 */
class TemplateDocumentController extends Controller
{
    private const STATUSES = ['failed', 'awaiting_file', 'queued', 'available'];

    public function index()
    {
        $projects = Project::whereIn('id', ProjectTemplate::whereIn('template_name_id', $this->fileTemplateNameIds())->select('project_id'))
            ->orderByDesc('id')->get(['id', 'project_name']);

        // The browser sends files in batches small enough for this server's upload limits
        $uploadLimits = [
            'files' => max(1, (int) ini_get('max_file_uploads') ?: 20),
            'bytes' => $this->iniBytes(ini_get('post_max_size')) ?: 8 * 1024 * 1024,
            'file'  => $this->iniBytes(ini_get('upload_max_filesize')) ?: 2 * 1024 * 1024,
        ];

        return view('masters.data_templates.template_documents', compact('projects', 'uploadLimits'));
    }

    /** Templates of a project that have a file column. */
    public function templates(Request $request)
    {
        $request->validate(['project_id' => 'required|integer']);
        $templates = ProjectTemplate::with('getTemplate')->where('project_id', (int) $request->project_id)
            ->whereIn('template_name_id', $this->fileTemplateNameIds())->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->getTemplate->template_name ?? 'Template #' . $t->id])
            ->values();

        return response()->json(['templates' => $templates]);
    }

    /** A template's documents for the table (DataTables server side), with the status counts. */
    public function list(Request $request)
    {
        $request->validate(['project_template_id' => 'required|integer']);
        $projectTemplate = ProjectTemplate::findOrFail((int) $request->project_template_id);
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $status = in_array($request->status, self::STATUSES, true) ? $request->status : null;

        $all = TemplateDocument::where('project_template_id', $projectTemplate->id);
        $filtered = (clone $all)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w->where('file_name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('source_url', 'like', '%' . addcslashes($search, '%_\\') . '%')));

        $documents = (clone $filtered)
            ->orderByRaw("FIELD(status, 'failed', 'awaiting_file', 'queued', 'available')")
            ->orderBy('file_name')
            ->skip(max(0, (int) $request->input('start', 0)))
            ->take(min(max(1, (int) $request->input('length', 25)), 500))
            ->get();

        $usage = $this->rowUsage($projectTemplate);
        $data = $documents->map(fn($doc) => [
            'file_name'  => $doc->file_name,
            'source_url' => $doc->source_url ? Str::limit($doc->source_url, 90) : null,
            'from'       => $doc->source_url ? 'Link' : 'File name',
            'rows'       => $usage[$doc->file_hash] ?? 0,
            'status'     => $doc->status,
            'error'      => $doc->error,
            'size'       => $doc->size ? number_format($doc->size / 1024, 0) . ' KB' : '',
            'view_url'   => $doc->status === 'available' ? route('template_documents.file', $doc->id) : null,
        ]);

        $counts = (clone $all)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => (int) $counts->sum(),
            'recordsFiltered' => (clone $filtered)->count(),
            'data'            => $data,
            'counts'          => $counts,
            'failed_links'    => (clone $all)->where('status', 'failed')->whereNotNull('source_url')->count(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'project_template_id' => 'required|integer',
            'files'               => 'required|array',
            'files.*'             => 'file|max:204800',
            'batch'               => 'nullable|alpha_num|max:40',
        ]);
        $projectTemplate = ProjectTemplate::findOrFail($request->project_template_id);

        // One click can send several requests (batches of files): a data file name takes only the first file of the click
        $batchKey = $request->filled('batch') ? 'template_documents_upload.' . Auth::id() . '.' . $request->batch : null;
        $taken = $batchKey ? Cache::get($batchKey, []) : [];

        $results = [];
        foreach ($request->file('files') as $file) {
            if (strtolower($file->getClientOriginalExtension()) === 'zip') {
                array_push($results, ...$this->fromZip($projectTemplate, $file->getRealPath(), $file->getClientOriginalName(), $taken));
                continue;
            }
            $results[] = $this->storeOne($projectTemplate, $file->getRealPath(), $file->getClientOriginalName(), $taken);
        }
        if ($batchKey) Cache::put($batchKey, $taken, now()->addHour());

        return response()->json(['results' => $results]);
    }

    /** Re-queues the template's failed links and downloads them after the response is sent. */
    public function retry(Request $request)
    {
        $request->validate(['project_template_id' => 'required|integer']);
        $count = TemplateDocument::where('project_template_id', (int) $request->project_template_id)
            ->where('status', 'failed')->whereNotNull('source_url')
            ->update(['status' => 'queued', 'error' => null]);
        if ($count) FetchTemplateDocumentsJob::dispatchAfterResponse((int) $request->project_template_id);

        return response()->json(['message' => $count
            ? "{$count} failed " . Str::plural('link', $count) . ' will be downloaded again in the background. Refresh the table in a minute to see the result.'
            : 'There are no failed links to retry.']);
    }

    /** Opens a stored file (inline; ?download=1 to save it). */
    public function file(Request $request, $document)
    {
        $doc = TemplateDocument::findOrFail((int) $document);
        abort_unless(TemplateDocuments::canView(Auth::user(), $doc), 403);
        abort_unless($doc->status === 'available' && $doc->stored_path && Storage::disk('local')->exists($doc->stored_path), 404);

        $name = $doc->original_name ?: $doc->file_name;
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.' . pathinfo($doc->stored_path, PATHINFO_EXTENSION);
        return Storage::disk('local')->response($doc->stored_path, $name, ['Content-Type' => $doc->mime ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline');
    }

    /** All stored files of one row's file columns as a .zip (auditors and verifiers of the template, admins). */
    public function rowZip($row)
    {
        $rowData = DB::table('project_template_name_values_new')->where('id', (int) $row)->first();
        abort_unless($rowData, 404);

        $docIds = collect(TemplateDocuments::forRows([$rowData->id])[$rowData->id] ?? [])
            ->where('status', 'available')->pluck('id')->unique();
        $documents = TemplateDocument::whereIn('id', $docIds)->get()
            ->filter(fn($doc) => $doc->stored_path && Storage::disk('local')->exists($doc->stored_path));
        abort_if($documents->isEmpty(), 404, 'This row has no stored documents yet.');
        abort_unless(TemplateDocuments::canView(Auth::user(), $documents->first()), 403);

        if (!class_exists(\ZipArchive::class)) {
            return redirect()->back()->with('error', 'ZIP files can\'t be made on this server.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tdzip');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $used = [];
        foreach ($documents as $doc) {
            $name = $doc->file_name;
            if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.' . pathinfo($doc->stored_path, PATHINFO_EXTENSION);
            $name = str_replace(['/', '\\'], '_', $name);
            // Two links can end in the same file name: keep both
            $base = $name;
            for ($n = 2; isset($used[strtolower($name)]); $n++) {
                $name = pathinfo($base, PATHINFO_FILENAME) . " ({$n})." . pathinfo($base, PATHINFO_EXTENSION);
            }
            $used[strtolower($name)] = true;
            $zip->addFile(Storage::disk('local')->path($doc->stored_path), $name);
        }
        $zip->close();

        // Named after the row's main header value, e.g. "Shree Industries documents.zip"
        $mainHeader = DB::table('project_templates')->where('id', $rowData->project_template_id)->value('main_header');
        $label = trim((string) ((json_decode($rowData->template_data_json, true) ?: [])[$mainHeader] ?? ''));
        $zipName = Str::limit(trim(preg_replace('/[^\pL\pN _.-]+/u', ' ', $label)) ?: 'Row ' . $rowData->id, 80, '') . ' documents.zip';

        return response()->download($tmp, $zipName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /** Stores one uploaded file unless its data file name already took a file in this upload. */
    private function storeOne(ProjectTemplate $projectTemplate, string $path, string $name, array &$taken): array
    {
        [$doc] = TemplateDocuments::matchUpload($projectTemplate->id, $name);
        if ($doc && isset($taken[$doc->id])) {
            return ['name' => $name, 'ok' => false, 'message' => "Skipped: {$doc->file_name} already got \"{$taken[$doc->id]}\" in this upload."];
        }
        [$ok, $message] = TemplateDocuments::storeUpload($projectTemplate, $path, $name, Auth::id());
        if ($ok && $doc) $taken[$doc->id] = $name;
        return ['name' => $name, 'ok' => $ok, 'message' => $message];
    }

    /** Each file of a .zip, matched by its own name (folders inside the zip are ignored). */
    private function fromZip(ProjectTemplate $projectTemplate, string $zipPath, string $zipName, array &$taken): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [['name' => $zipName, 'ok' => false, 'message' => 'ZIP files can\'t be opened on this server; upload the files themselves.']];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [['name' => $zipName, 'ok' => false, 'message' => 'The ZIP file could not be opened.']];
        }

        $results = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entry = $stat['name'];
            $base = basename(str_replace('\\', '/', $entry));
            if (str_ends_with($entry, '/') || $base === '' || str_starts_with($base, '.') || str_contains($entry, '__MACOSX')) continue;
            if ($stat['size'] > TemplateDocuments::MAX_BYTES) {
                $results[] = ['name' => $base, 'ok' => false, 'message' => 'The file is larger than 25 MB.'];
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'tdz');
            $in = $zip->getStream($entry);
            file_put_contents($tmp, $in);
            if (is_resource($in)) fclose($in);
            $results[] = $this->storeOne($projectTemplate, $tmp, $base, $taken);
            @unlink($tmp);
        }
        $zip->close();
        return $results;
    }

    /** How many rows use each file, by file hash (file cells are stored cleaned, compared lower-cased). */
    private function rowUsage(ProjectTemplate $projectTemplate): array
    {
        $usage = [];
        foreach (TemplateDocuments::fileHeadIds((int) $projectTemplate->template_name_id) as $headId) {
            DB::table('project_template_name_values_new')
                ->where('project_template_id', $projectTemplate->id)
                ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')))) as v, COUNT(*) as n")
                ->groupBy('v')
                ->pluck('n', 'v')
                ->each(function ($n, $v) use (&$usage) {
                    if ($v === null || $v === '' || $v === 'null') return;
                    $usage[sha1($v)] = ($usage[sha1($v)] ?? 0) + $n;
                });
        }
        return $usage;
    }

    /** Templates (template_names) with at least one file column. */
    private function fileTemplateNameIds()
    {
        return DB::table('template_name_heads')->where('is_file', 1)->distinct()->pluck('template_name_id');
    }

    /** php.ini size ("8M", "2G") in bytes. */
    private function iniBytes($value): int
    {
        $value = trim((string) $value);
        if ($value === '') return 0;
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
