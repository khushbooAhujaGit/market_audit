<?php

namespace App\Services;

use App\Models\ProjectTemplate;
use App\Models\TemplateDocument;
use App\Models\User;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File columns of project data (heads given Type "File" when the template was created).
 * A cell holds one file name (e.g. abc.pdf) or one http(s) link. A name or link belongs to one
 * template of a project; any number of that template's rows can use it. The file is uploaded
 * later on the Template Documents page, or downloaded from the link after the data upload.
 */
class TemplateDocuments
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'xls', 'xlsx', 'csv', 'doc', 'docx'];
    public const MAX_BYTES  = 25 * 1024 * 1024;
    public const DISK_DIR   = 'template_documents';

    private const MIME_EXT = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv' => 'csv',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Ids of the file columns of a template (template_names.id). */
    public static function fileHeadIds(int $templateNameId): array
    {
        return DB::table('template_name_heads')->where('template_name_id', $templateNameId)
            ->where('is_file', 1)->orderBy('id')->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /** Light clean-up for a file cell: unlike other cells, slashes and colons are kept (links). */
    public static function clean($value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return trim((string) $value);
    }

    /**
     * A file cell as ['kind' => 'url'|'name', 'key' => ..., 'name' => ..., 'url' => ...],
     * ['error' => reason] when invalid, or null when empty.
     */
    public static function parse($value): ?array
    {
        $value = self::clean($value);
        if ($value === '') return null;

        if (preg_match('#^https?://#i', $value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL) || mb_strlen($value) > 2000) {
                return ['error' => 'is not a valid link'];
            }
            $base = rawurldecode(basename((string) parse_url($value, PHP_URL_PATH)));
            return [
                'kind' => 'url',
                'key'  => mb_strtolower($value),
                'name' => Str::limit($base !== '' ? $base : (string) parse_url($value, PHP_URL_HOST), 200, ''),
                'url'  => $value,
            ];
        }

        if (preg_match('#[\\\\/:*?"<>|]#', $value)) {
            return ['error' => 'must be only a file name (e.g. abc.pdf) or an http(s) link'];
        }
        if (!in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            return ['error' => 'must end with one of: .' . implode(', .', self::EXTENSIONS)];
        }
        if (mb_strlen($value) > 200) {
            return ['error' => 'is longer than 200 characters'];
        }
        return ['kind' => 'name', 'key' => mb_strtolower($value), 'name' => $value, 'url' => null];
    }

    public static function hashOf($value): ?string
    {
        $parsed = self::parse($value);
        return ($parsed && !isset($parsed['error'])) ? sha1($parsed['key']) : null;
    }

    /**
     * Problems with the file cells about to be uploaded to a template: invalid values, and names or
     * links that already belong to another template of the project.
     * $cells: iterable of [sheet line, column name, value].
     */
    public static function check(ProjectTemplate $pt, iterable $cells): array
    {
        return array_map(fn($p) => "Row {$p['line']}, {$p['head']}: {$p['reason']}", self::problems($pt, $cells));
    }

    /** The same problems as check(), one per cell: [['line' =>, 'head' =>, 'value' =>, 'reason' =>], ...] in sheet order. */
    public static function problems(ProjectTemplate $pt, iterable $cells): array
    {
        $errors = [];
        $byHash = [];
        $seq = 0;
        foreach ($cells as [$line, $head, $value]) {
            $seq++;
            $parsed = self::parse($value);
            if ($parsed === null) continue;
            $shown = self::clean($value);
            if (isset($parsed['error'])) {
                $errors[] = ['line' => $line, 'head' => $head, 'value' => $shown, 'reason' => "\"{$shown}\" {$parsed['error']}.", 'seq' => $seq];
                continue;
            }
            $byHash[sha1($parsed['key'])][] = [$line, $head, $shown, $seq];
        }

        foreach (array_chunk(array_keys($byHash), 1000) as $hashes) {
            $taken = DB::table('project_template_documents as d')
                ->join('project_templates as pt', 'pt.id', '=', 'd.project_template_id')
                ->leftJoin('template_names as t', 't.id', '=', 'pt.template_name_id')
                ->where('d.project_id', $pt->project_id)
                ->where('d.project_template_id', '!=', $pt->id)
                ->whereIn('d.file_hash', $hashes)
                ->pluck('t.template_name', 'd.file_hash');
            foreach ($taken as $hash => $templateName) {
                foreach ($byHash[$hash] as [$line, $head, $shown, $seq]) {
                    $errors[] = ['line' => $line, 'head' => $head, 'value' => $shown, 'seq' => $seq,
                        'reason' => "\"{$shown}\" is already used in the \"{$templateName}\" template of this project. A file name or link can belong to only one template."];
                }
            }
        }
        // Back in sheet order (row, then column)
        usort($errors, fn($a, $b) => $a['seq'] <=> $b['seq']);
        return $errors;
    }

    /** File cells of an uploaded CSV, by column position (columns are in template order): [[line, column name, value], ...] */
    public static function csvFileCells(string $csvPath, ProjectTemplate $pt): array
    {
        $heads = DB::table('template_name_heads')->where('template_name_id', $pt->template_name_id)
            ->orderBy('id')->get(['id', 'template_head_name', 'is_file'])->values();
        $fileColumns = $heads->filter(fn($h) => (int) $h->is_file === 1)->map(fn($h) => trim($h->template_head_name));
        if ($fileColumns->isEmpty() || !($handle = fopen($csvPath, 'r'))) return [];

        $cells = [];
        fgetcsv($handle);   // header row (checked by the import itself)
        $line = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            foreach ($fileColumns as $index => $headName) {
                $cells[] = [$line, $headName, $data[$index] ?? ''];
            }
        }
        fclose($handle);
        return $cells;
    }

    /**
     * Records the names and links of an uploaded template's file cells (already checked). Names wait
     * for their file; links are queued for download. Returns how many links are queued.
     */
    public static function register(ProjectTemplate $pt, iterable $values, ?int $userId): int
    {
        $records = [];
        $now = now();
        foreach ($values as $value) {
            $parsed = self::parse($value);
            if (!$parsed || isset($parsed['error'])) continue;
            $hash = sha1($parsed['key']);
            $records[$hash] ??= [
                'project_id'          => $pt->project_id,
                'project_template_id' => $pt->id,
                'file_hash'           => $hash,
                'file_name'           => $parsed['name'],
                'source_url'          => $parsed['url'],
                'status'              => $parsed['kind'] === 'url' ? 'queued' : 'awaiting_file',
                'uploaded_by'         => $userId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        foreach (array_chunk(array_values($records), 500) as $chunk) {
            DB::table('project_template_documents')->insertOrIgnore($chunk);
        }
        return TemplateDocument::where('project_template_id', $pt->id)->where('status', 'queued')->count();
    }

    /** Downloads every queued link of a template. */
    public static function fetchQueued(int $projectTemplateId): void
    {
        @set_time_limit(0);
        TemplateDocument::where('project_template_id', $projectTemplateId)->where('status', 'queued')
            ->orderBy('id')->each(fn($doc) => self::download($doc));
    }

    public static function download(TemplateDocument $doc): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tdoc');
        try {
            $url = self::directLink((string) $doc->source_url);
            $response = null;
            for ($hop = 0; $hop <= 5; $hop++) {
                [$host, $port, $ip] = self::publicAddress($url);
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'stream'          => true,
                    'connect_timeout' => 15,
                    'timeout'         => 180,
                    // Connect to the address that was checked (no second DNS lookup)
                    'curl'            => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                ])->withHeaders(['User-Agent' => 'Mozilla/5.0 (MarketAudit document fetch)'])->get($url);

                if (!$response->redirect()) break;
                $location = $response->header('Location');
                if ($location === '' || $hop === 5) throw new \RuntimeException('The link redirects too many times.');
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
            }

            if (!$response->successful()) {
                throw new \RuntimeException("The link returned HTTP {$response->status()}.");
            }
            if ((int) $response->header('Content-Length') > self::MAX_BYTES) {
                throw new \RuntimeException('The file is larger than 25 MB.');
            }

            $body = $response->toPsrResponse()->getBody();
            $out = fopen($tmp, 'w');
            $bytes = 0;
            while (!$body->eof()) {
                $chunk = $body->read(1024 * 1024);
                $bytes += strlen($chunk);
                if ($bytes > self::MAX_BYTES) {
                    fclose($out);
                    throw new \RuntimeException('The file is larger than 25 MB.');
                }
                fwrite($out, $chunk);
            }
            fclose($out);

            // Name for the file type: the link's file name, or the one in Content-Disposition
            $nameHint = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
            if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', (string) $response->header('Content-Disposition'), $m)) {
                $nameHint = rawurldecode(trim($m[1]));
            }
            self::storeFile($doc, $tmp, $nameHint !== '' ? $nameHint : $doc->file_name, $doc->uploaded_by);
        } catch (\Throwable $e) {
            $doc->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }

    /**
     * Saves a file for a document under a unique name. Throws \RuntimeException with a readable
     * reason when the file is empty, too large or not an allowed type.
     */
    public static function storeFile(TemplateDocument $doc, string $tmpPath, string $nameHint, ?int $userId): void
    {
        [$ext, $mime, $size] = self::checkFile($tmpPath, $nameHint);

        $path = self::DISK_DIR . "/{$doc->project_id}/{$doc->project_template_id}/" . Str::uuid() . ".{$ext}";
        $in = fopen($tmpPath, 'r');
        Storage::disk('local')->put($path, $in);
        if (is_resource($in)) fclose($in);

        $old = $doc->stored_path;
        $doc->update([
            'stored_path'   => $path,
            'original_name' => Str::limit($nameHint, 250, ''),
            'mime'          => $mime,
            'size'          => $size,
            'status'        => 'available',
            'error'         => null,
            'uploaded_by'   => $userId,
        ]);
        if ($old && $old !== $path) Storage::disk('local')->delete($old);
    }

    /**
     * Whether a file may be stored: [extension, mime, size], or \RuntimeException with a readable
     * reason when it is empty, too large or not an allowed type (its content is checked, not only its name).
     */
    public static function checkFile(string $tmpPath, string $nameHint): array
    {
        $size = (int) @filesize($tmpPath);
        if ($size === 0) throw new \RuntimeException('The file is empty.');
        if ($size > self::MAX_BYTES) throw new \RuntimeException('The file is larger than 25 MB.');

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: 'application/octet-stream';
        if (str_starts_with($mime, 'text/html')) {
            throw new \RuntimeException('The link returned a web page, not a file. Use a direct download link (or a public share link).');
        }

        $ext = strtolower(pathinfo($nameHint, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) $ext = self::MIME_EXT[$mime] ?? '';
        if ($ext === '') throw new \RuntimeException("This file type ({$mime}) is not allowed.");
        if (in_array($ext, self::IMAGE_EXTENSIONS, true) && !str_starts_with($mime, 'image/')) {
            throw new \RuntimeException('The file is not an image.');
        }
        if ($ext === 'pdf' && $mime !== 'application/pdf') throw new \RuntimeException('The file is not a PDF.');
        // Office files: .xlsx/.docx are zip packages, .xls/.doc are OLE files, .csv is text
        $officeMimes = [
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'],
            'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
            'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        ];
        if (isset($officeMimes[$ext]) && !in_array($mime, $officeMimes[$ext], true)) {
            throw new \RuntimeException(in_array($ext, ['xls', 'xlsx'], true) ? 'The file is not an Excel file.' : 'The file is not a Word file.');
        }
        if ($ext === 'csv' && !str_starts_with($mime, 'text/') && !in_array($mime, ['application/csv', 'application/vnd.ms-excel'], true)) {
            throw new \RuntimeException('The file is not a CSV file.');
        }
        return [$ext, $mime, $size];
    }

    /**
     * File columns of a row added one at a time (the auditor's Add form on the web, the app's
     * storeTemplateData). A file chosen for a column is checked now and kept under its own name — made
     * unique in the project when the name is taken ("photo (2).jpg"), so it never replaces another
     * row's file. A typed name or link is checked like the data upload.
     * $files: [head id => UploadedFile]. Returns [values with the files' names, errors, files to store].
     */
    public static function prepareRowFiles(ProjectTemplate $pt, array $values, array $files): array
    {
        $fileHeadIds = self::fileHeadIds((int) $pt->template_name_id);
        if (!$fileHeadIds) return [$values, [], []];
        $headNames = DB::table('template_name_heads')->whereIn('id', $fileHeadIds)->pluck('template_head_name', 'id');

        $errors = [];
        $pending = [];
        $taken = [];
        foreach ($fileHeadIds as $headId) {
            $file = $files[$headId] ?? null;
            if (!$file instanceof \Illuminate\Http\UploadedFile) continue;
            if (!$file->isValid()) {
                $errors[] = "{$headNames[$headId]}: the file could not be uploaded ({$file->getErrorMessage()}).";
                continue;
            }
            $original = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', self::clean(basename($file->getClientOriginalName())));
            try {
                [$ext] = self::checkFile($file->getRealPath(), $original);
            } catch (\RuntimeException $e) {
                $errors[] = "{$headNames[$headId]}: {$e->getMessage()}";
                continue;
            }
            $stem = Str::limit(pathinfo($original, PATHINFO_FILENAME) ?: 'file', 180, '');
            $name = self::freeName($pt, $stem, $ext, $taken);
            $taken[] = mb_strtolower($name);
            $values[$headId] = $name;
            $pending[$headId] = [$file, $name];
        }

        $typed = [];
        foreach ($fileHeadIds as $headId) {
            if (isset($pending[$headId]) || !array_key_exists($headId, $values)) continue;
            $values[$headId] = self::clean($values[$headId]);
            if ($values[$headId] !== '') $typed[] = [1, $headNames[$headId], $values[$headId]];
        }
        foreach (self::problems($pt, $typed) as $problem) {
            $errors[] = "{$problem['head']}: {$problem['reason']}";
        }
        return [$values, $errors, $pending];
    }

    /** After the row is saved: records its file names and links, stores the chosen files, fetches the links. */
    public static function saveRowFiles(ProjectTemplate $pt, array $values, array $pending, ?int $userId): void
    {
        $fileHeadIds = self::fileHeadIds((int) $pt->template_name_id);
        if (!$fileHeadIds) return;

        $queued = self::register($pt, collect($fileHeadIds)->map(fn($id) => $values[$id] ?? '')->all(), $userId);
        foreach ($pending as [$file, $name]) {
            $doc = TemplateDocument::where('project_template_id', $pt->id)->where('file_hash', sha1(mb_strtolower($name)))->first();
            if (!$doc) continue;
            try {
                self::storeFile($doc, $file->getRealPath(), $name, $userId);
            } catch (\RuntimeException $e) {
                $doc->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
            }
        }
        if ($queued) \App\Jobs\FetchTemplateDocumentsJob::dispatchAfterResponse($pt->id);
    }

    /** "stem.ext", or "stem (2).ext", ... when a template of the project already has that name. */
    private static function freeName(ProjectTemplate $pt, string $stem, string $ext, array $alsoTaken): string
    {
        for ($n = 1; ; $n++) {
            $name = $stem . ($n > 1 ? " ({$n})" : '') . ".{$ext}";
            $lower = mb_strtolower($name);
            if (!in_array($lower, $alsoTaken, true)
                && !TemplateDocument::where('project_id', $pt->project_id)->where('file_hash', sha1($lower))->exists()) {
                return $name;
            }
        }
    }

    /**
     * A file uploaded on the Template Documents page, stored for the file name of the template's data
     * it matches (see matchUpload). Returns [ok, message].
     */
    public static function storeUpload(ProjectTemplate $pt, string $tmpPath, string $originalName, ?int $userId): array
    {
        [$doc, $why] = self::matchUpload($pt->id, $originalName);
        if (!$doc) return [false, $why];

        try {
            $replaced = $doc->status === 'available';
            self::storeFile($doc, $tmpPath, $originalName, $userId);
            return [true, ($replaced ? 'Replaced ' : 'Uploaded as ') . $doc->file_name . '.'];
        } catch (\RuntimeException $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * The file name of a template's data an uploaded file belongs to: the same name, or else a name found
     * inside the uploaded file's name with the same type ("inv1.pdf" for "Scan_inv1 (2).pdf"). A number
     * in the data isn't matched inside a longer number ("inv1.pdf" doesn't take "inv12.pdf"), and when
     * several names fit the longest wins. Only names of this template: a name belongs to one template of
     * the project. Returns [TemplateDocument|null, reason when null].
     */
    public static function matchUpload(int $projectTemplateId, string $uploadedName): array
    {
        $uploaded = mb_strtolower(self::clean($uploadedName));
        $exact = TemplateDocument::where('project_template_id', $projectTemplateId)->whereNull('source_url')
            ->where('file_hash', sha1($uploaded))->first();
        if ($exact) return [$exact, null];

        $ext = self::sameType(pathinfo($uploaded, PATHINFO_EXTENSION));
        $stem = pathinfo($uploaded, PATHINFO_FILENAME);
        if ($stem === '' || !in_array($ext, self::EXTENSIONS, true)) {
            return [null, 'Only ' . strtoupper(implode(', ', self::EXTENSIONS)) . ' files (or a .zip of them) can be uploaded.'];
        }

        // Loaded once per request: an upload of many files (or a .zip) matches each against the same names
        $cacheKey = 'template_document_names.' . $projectTemplateId;
        $names = request()->attributes->get($cacheKey);
        if ($names === null) {
            $names = TemplateDocument::where('project_template_id', $projectTemplateId)->whereNull('source_url')
                ->get(['id', 'file_name'])
                ->map(fn($d) => [
                    'id'   => $d->id,
                    'stem' => mb_strtolower(pathinfo($d->file_name, PATHINFO_FILENAME)),
                    'ext'  => self::sameType(strtolower(pathinfo($d->file_name, PATHINFO_EXTENSION))),
                    'name' => $d->file_name,
                ])->all();
            request()->attributes->set($cacheKey, $names);
        }

        $best = [];
        $bestLength = 0;
        foreach ($names as $name) {
            if ($name['ext'] !== $ext || $name['stem'] === '' || !self::containsName($stem, $name['stem'])) continue;
            $length = mb_strlen($name['stem']);
            if ($length > $bestLength) [$best, $bestLength] = [[$name], $length];
            elseif ($length === $bestLength) $best[] = $name;
        }

        if (!$best) return [null, 'No file name of this template is found in this file\'s name.'];
        if (count($best) > 1) {
            return [null, 'This file\'s name fits more than one file name (' . implode(', ', array_column($best, 'name')) . '); rename it to one of them.'];
        }
        return [TemplateDocument::find($best[0]['id']), null];
    }

    /** jpg and jpeg are the same type. */
    private static function sameType(string $ext): string
    {
        $ext = strtolower($ext);
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    /** $needle inside $haystack, not continuing a number on either side (1 isn't found in 12). */
    private static function containsName(string $haystack, string $needle): bool
    {
        $offset = 0;
        while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
            $before = $pos > 0 ? mb_substr($haystack, $pos - 1, 1) : '';
            $after = mb_substr($haystack, $pos + mb_strlen($needle), 1);
            $joinsNumber = (ctype_digit($before) && ctype_digit(mb_substr($needle, 0, 1)))
                || (ctype_digit($after) && ctype_digit(mb_substr($needle, -1)));
            if (!$joinsNumber) return true;
            $offset = $pos + 1;
        }
        return false;
    }

    /** Documents of a template by hash, for the given cell values. */
    public static function lookup(int $projectTemplateId, iterable $values): array
    {
        $hashes = collect($values)->map(fn($v) => self::hashOf($v))->filter()->unique()->values();
        if ($hashes->isEmpty()) return [];
        return TemplateDocument::where('project_template_id', $projectTemplateId)
            ->whereIn('file_hash', $hashes)->get()->keyBy('file_hash')->all();
    }

    /** The file cells of a row: [head id => document info] (see present()). */
    public static function forRow($row, bool $forApi = false): array
    {
        $templateNameId = (int) DB::table('project_templates')->where('id', $row->project_template_id)->value('template_name_id');
        $fileHeadIds = self::fileHeadIds($templateNameId);
        if (!$fileHeadIds) return [];

        $json = json_decode($row->template_data_json, true) ?: [];
        $docs = self::lookup((int) $row->project_template_id, collect($fileHeadIds)->map(fn($id) => $json[$id] ?? ''));

        $out = [];
        foreach ($fileHeadIds as $headId) {
            $value = $json[$headId] ?? '';
            if (self::clean($value) === '') continue;
            $out[$headId] = self::present($docs[self::hashOf($value)] ?? null, $value, $forApi);
        }
        return $out;
    }

    /**
     * The file columns of several rows for the app, in one go: [row id => [['head_id', 'head_name',
     * 'id', 'file_name', 'status', 'kind', 'mime', 'url', 'error'], ...]]. Rows without a file
     * column (or with every file cell empty) are left out; url is a download link (no token, 24 hours).
     */
    public static function forRowsApi(iterable $rowIds): array
    {
        return self::forRows($rowIds, true);
    }

    /** The same for the web pages (url opens the file for the logged-in user). */
    public static function forRows(iterable $rowIds, bool $forApi = false): array
    {
        $rowIds = collect($rowIds)->map(fn($id) => (int) $id)->filter()->unique()->values();
        if ($rowIds->isEmpty()) return [];

        $rows = DB::table('project_template_name_values_new')->whereIn('id', $rowIds)
            ->get(['id', 'project_template_id', 'template_data_json']);
        $templateNameIds = DB::table('project_templates')->whereIn('id', $rows->pluck('project_template_id')->unique())
            ->pluck('template_name_id', 'id');

        $out = [];
        foreach ($rows->groupBy('project_template_id') as $projectTemplateId => $templateRows) {
            $heads = DB::table('template_name_heads')->where('template_name_id', (int) ($templateNameIds[$projectTemplateId] ?? 0))
                ->where('is_file', 1)->orderBy('id')->pluck('template_head_name', 'id');
            if ($heads->isEmpty()) continue;

            $cells = $templateRows->mapWithKeys(fn($row) => [$row->id => json_decode($row->template_data_json, true) ?: []]);
            $docs = self::lookup((int) $projectTemplateId, $cells->flatMap(fn($json) => $heads->keys()->map(fn($id) => $json[$id] ?? '')));
            foreach ($cells as $rowId => $json) {
                foreach ($heads as $headId => $headName) {
                    $value = $json[$headId] ?? '';
                    if (self::clean($value) === '') continue;
                    $out[$rowId][] = ['head_id' => (int) $headId, 'head_name' => $headName]
                        + self::present($docs[self::hashOf($value)] ?? null, $value, $forApi);
                }
            }
        }
        return $out;
    }

    /** One document for a page or the API: name, status (available / awaiting_file / queued / failed / invalid), kind and link. */
    public static function present(?TemplateDocument $doc, $value, bool $forApi = false): array
    {
        $name = $doc->file_name ?? self::clean($value);
        $ext = strtolower(pathinfo($doc->stored_path ?? $name, PATHINFO_EXTENSION));
        $available = $doc && $doc->status === 'available';
        return [
            'id'        => $doc->id ?? null,
            'file_name' => $name,
            'status'    => $doc->status ?? (self::hashOf($value) ? 'awaiting_file' : 'invalid'),
            'kind'      => in_array($ext, self::IMAGE_EXTENSIONS, true) ? 'image'
                : ($ext === 'pdf' ? 'pdf' : (in_array($ext, ['xls', 'xlsx', 'csv'], true) ? 'excel' : (in_array($ext, ['doc', 'docx'], true) ? 'word' : 'file'))),
            'mime'      => $doc->mime ?? null,
            'url'       => $available ? ($forApi ? self::downloadLink($doc) : route('template_documents.file', $doc->id)) : null,
            'error'     => $doc->error ?? null,
        ];
    }

    /** Hours a download link given to the app keeps working. */
    public const LINK_HOURS = 24;

    /**
     * A download link for the app that works without a login (browser, download manager, image
     * loader) until it expires: /api/documentDownload/{id}/{expires}/{signature}. Built by hand, as
     * the app's URL generator encrypts route parameters (which breaks Laravel's signed routes).
     */
    public static function downloadLink(TemplateDocument $doc): string
    {
        $expires = now()->addHours(self::LINK_HOURS)->timestamp;
        return url("api/documentDownload/{$doc->id}/{$expires}/" . self::linkSignature($doc->id, $expires));
    }

    /**
     * A download link for a report that does not expire ({expires} = 0): reports are mailed and kept,
     * and their answer image links work without a login too. Signed, so the id can't be changed.
     */
    public static function reportLink(TemplateDocument $doc): string
    {
        return url("api/documentDownload/{$doc->id}/0/" . self::linkSignature($doc->id, 0));
    }

    /**
     * File-column cells of a template's rows for a report: [row id => [head id => cell]]. A stored file
     * becomes its report link (the report writer shows it as a "Download" hyperlink); otherwise the
     * name or link with why there is no file — written so the writer doesn't turn it into a link.
     */
    public static function reportCells(int $projectTemplateId, iterable $rows): array
    {
        $templateNameId = (int) DB::table('project_templates')->where('id', $projectTemplateId)->value('template_name_id');
        $fileHeadIds = self::fileHeadIds($templateNameId);
        if (!$fileHeadIds) return [];

        $cells = collect($rows)->mapWithKeys(fn($row) => [$row->id => json_decode($row->template_data_json, true) ?: []]);
        $docs = self::lookup($projectTemplateId, $cells->flatMap(fn($json) => collect($fileHeadIds)->map(fn($id) => $json[$id] ?? '')));
        $notes = ['awaiting_file' => 'file not uploaded yet', 'queued' => 'downloading', 'failed' => 'download failed'];

        $out = [];
        foreach ($cells as $rowId => $json) {
            foreach ($fileHeadIds as $headId) {
                $value = self::clean($json[$headId] ?? '');
                if ($value === '') continue;
                $doc = self::hashOf($value) ? ($docs[self::hashOf($value)] ?? null) : null;
                if ($doc && $doc->status === 'available') {
                    $out[$rowId][$headId] = self::reportLink($doc);
                    continue;
                }
                $note = self::hashOf($value) ? ($notes[$doc->status ?? 'awaiting_file'] ?? 'no file') : 'not a file name';
                $out[$rowId][$headId] = (preg_match('#^https?://#i', $value) ? 'Link: ' : '') . "{$value} ({$note})";
            }
        }
        return $out;
    }

    public static function linkSignature(int $id, int $expires): string
    {
        return hash_hmac('sha256', "template-document|{$id}|{$expires}", (string) config('app.key'));
    }

    /** Who may open a document: auditors and verifiers only on templates assigned to them. */
    public static function canView(User $user, TemplateDocument $doc): bool
    {
        if ($user->hasRole('Super Admin')) return true;
        if ($user->hasRole('Auditor')) {
            return DB::table('user_activity_data_assigns')->where('user_id', $user->id)
                ->where('project_template_id', $doc->project_template_id)->exists();
        }
        if ($user->hasRole('Verifier')) {
            return DB::table('verifiers')->where('user_id', $user->id)->where('project_template_name_id', $doc->project_template_id)->exists()
                || DB::table('verifier_row_assigns')->where('user_id', $user->id)->where('project_template_id', $doc->project_template_id)->exists();
        }
        return true;
    }

    /** Share links of Google Drive, Dropbox and OneDrive/SharePoint as direct downloads. */
    private static function directLink(string $url): string
    {
        if (preg_match('#drive\.google\.com/file/d/([^/?]+)#', $url, $m)
            || (str_contains($url, 'drive.google.com') && preg_match('#[?&]id=([^&]+)#', $url, $m))) {
            return 'https://drive.google.com/uc?export=download&id=' . $m[1];
        }
        if (str_contains($url, 'dropbox.com')) {
            return preg_replace('#([?&])dl=0#', '$1dl=1', $url) . (str_contains($url, 'dl=') ? '' : (str_contains($url, '?') ? '&dl=1' : '?dl=1'));
        }
        if ((str_contains($url, 'sharepoint.com') || str_contains($url, '1drv.ms') || str_contains($url, 'onedrive.live.com'))
            && !str_contains($url, 'download=1')) {
            return $url . (str_contains($url, '?') ? '&' : '?') . 'download=1';
        }
        return $url;
    }

    /**
     * Host, port and the checked IP address of a link. Links to private or reserved addresses
     * (the server's own network) are refused, since the server fetches addresses typed by users.
     */
    private static function publicAddress(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Only http(s) links can be downloaded.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) throw new \RuntimeException("The link's address ({$host}) could not be found.");
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('Links to private or local addresses are not allowed.');
            }
        }
        return [$host, (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), $ips[0]];
    }
}
