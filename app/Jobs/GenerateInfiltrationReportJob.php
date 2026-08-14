<?php

namespace App\Jobs;

use App\Services\OutlookMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Generates the standard project report (main sheet + exactly one activity's instance
 * sheet), then runs the external "Infiltration Audit Report Tool" Python script against
 * it — which reformats the data into HCCB's fixed compliance layout and downloads the
 * outlet photo ZIPs referenced in the report — and emails the resulting package.
 *
 * Mirrors GenerateReportJob's structure: delegate to ReportController::buildReportFile()
 * so the base report is always identical to a normal download, then extend with the
 * Python post-processing step.
 */
class GenerateInfiltrationReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Photo downloads are sequential and network-bound (one HTTP call per photo column
    // per item), so this can run considerably longer than a normal report generation.
    public int $tries   = 1;
    public int $timeout = 900; // 15 min; retry_after in queue.php must exceed this

    protected $validated;
    protected $request;
    protected $user;
    protected $mailer;

    public function __construct($validated, $request, $user)
    {
        $this->validated = $validated;
        $this->request   = new \Illuminate\Http\Request($request);
        $this->user      = $user;
        $this->mailer    = new OutlookMailerService();
    }

    public function handle(): void
    {
        $this->step('started', ['user' => $this->user->email]);

        $xlsxPath  = null;
        $outputDir = null;
        $zipPath   = null;

        try {
            ['xlsxPath' => $xlsxPath, 'outputDir' => $outputDir, 'zipPath' => $zipPath] = $this->generateZip();
        } catch (\Throwable $e) {
            $this->step('report generation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->mailer->sendMail(
                $this->user->email,
                'Infiltration Audit Report — Generation Failed',
                '<p>Hello ' . htmlspecialchars($this->user->name) . ',</p>'
                . '<p>Sorry, the Infiltration Audit report could not be generated. '
                . 'Please contact support with the details below.</p>'
                . '<pre style="white-space:pre-wrap;font-size:12px;color:#555;">'
                . htmlspecialchars(substr($e->getMessage(), 0, 2000)) . '</pre>'
            );
            $this->step('failure email sent');
            if ($xlsxPath) @unlink($xlsxPath);
            if ($zipPath) @unlink($zipPath);
            if ($outputDir) $this->deleteDirectory($outputDir);
            return;
        }

        // 4. Email the result.
        $htmlBody = '<!DOCTYPE html><html><head><style>
            body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
            .container { background-color: #ffffff; padding: 20px; border-radius: 10px; }
            .header { font-size: 24px; color: #333; margin-bottom: 10px; }
            .content { font-size: 16px; color: #555; }
            .footer { font-size: 12px; color: #aaa; margin-top: 20px; }
            </style></head><body><div class="container">
            <div class="header">Hello ' . htmlspecialchars($this->user->name) . ',</div>
            <div class="content"><p>Your Infiltration Audit report is ready — attached as a ZIP '
            . 'containing the reformatted report and every outlet\'s photos.</p></div>
            <div class="footer">© ' . date('Y') . ' TNBT</div>
            </div></body></html>';

        $this->step('sending success mail', ['to' => $this->user->email, 'zipPath' => $zipPath]);
        $this->mailer->sendMail($this->user->email, 'Infiltration Audit Report', $htmlBody, [$zipPath]);
        $this->step('success mail sent');

        // 5. Clean up.
        @unlink($xlsxPath);
        @unlink($zipPath);
        $this->deleteDirectory($outputDir);
        $this->step('cleaned up');
    }

    /**
     * Core generation logic, shared by the queued/email flow (handle()) and the
     * synchronous direct-download flow (InfiltrationReportController::download()).
     * Builds the base report, verifies it, runs the Python tool, and zips the result.
     * Throws on any failure — does not email or clean up; that's the caller's job,
     * since the two callers handle failure differently (email vs. HTTP error).
     *
     * @return array{xlsxPath: string, outputDir: string, zipPath: string}
     */
    public function generateZip(): array
    {
        @mkdir(public_path('infiltration_runs'), 0755, true);

        // 1. Build the base report — same generator as the normal download, restricted by
        //    the caller to exactly one activity so the output has exactly 2 sheets (the
        //    Python tool only ever reads worksheets[0] and worksheets[1]).
        $this->step('building base report');
        $controller = app(\App\Http\Controllers\Masters\ReportController::class);
        $result     = $controller->buildReportFile($this->request, $this->validated);
        $xlsxPath   = $result['path'];
        $this->step('base report built', [
            'xlsxPath' => $xlsxPath,
            'exists'   => file_exists($xlsxPath),
            'size'     => file_exists($xlsxPath) ? filesize($xlsxPath) : null,
        ]);

        // The Python tool hardcodes reading exactly worksheets[0] and worksheets[1]
        // (main sheet + one instance sheet). The controller already rejects
        // group-activity template submissions before dispatching this job, but that
        // check lives in a different layer — verify the actual generated file here
        // too, so a mismatch fails loudly instead of the tool silently ignoring any
        // sheets past index 1.
        $sheetCount = $this->countWorksheets($xlsxPath);
        $this->step('worksheet count checked', ['sheetCount' => $sheetCount]);
        if ($sheetCount !== 2) {
            throw new \RuntimeException(
                "Expected exactly 2 worksheets (main + one instance sheet) but the generated "
                . "report has {$sheetCount}. This usually means the selected activity produced "
                . "more than one instance sheet — the Infiltration tool only supports "
                . "single-activity templates."
            );
        }

        $runId     = uniqid('run_', true);
        $outputDir = public_path('infiltration_runs/' . $runId);
        $mkdirOk   = @mkdir($outputDir, 0755, true);
        $this->step('output dir created', [
            'outputDir' => $outputDir,
            'mkdirOk'   => $mkdirOk,
            'isDir'     => is_dir($outputDir),
            'writable'  => is_dir($outputDir) ? is_writable($outputDir) : null,
        ]);

        $zipPath = public_path('infiltration_runs/' . $runId . '.zip');

        // Resolve as many of the report's photo links as possible to local files up
        // front, since this process always runs on the same machine that hosts them —
        // the Python tool can then read them straight off disk instead of making an
        // HTTP request back into this same web server (which, on a single-threaded
        // dev server, deadlocks: that server can't answer the callback while it's
        // still busy handling this very request). Falls back to a normal HTTP fetch
        // for anything it can't resolve, so this degrades gracefully either way.
        $photoCacheDir = public_path('infiltration_runs/' . $runId . '_photo_cache');
        $photoMapPath  = public_path('infiltration_runs/' . $runId . '_photo_map.json');
        try {
            $photoMap = $this->buildLocalPhotoMap($xlsxPath, $photoCacheDir);
            $this->step('local photo map built', ['resolved' => count($photoMap)]);
            $written = @file_put_contents($photoMapPath, json_encode($photoMap));
            if ($written === false) {
                throw new \RuntimeException(
                    "Could not write to {$photoMapPath} - the 'infiltration_runs' folder isn't "
                    . "writable by whichever user is running this request. This often happens if "
                    . "the folder was previously created by a different user (e.g. a queue worker "
                    . "started as root over SSH) than the one serving this web request."
                );
            }

            $this->step('running python tool');
            $this->runPythonTool($xlsxPath, $outputDir, $photoMapPath);
            $this->step('python tool finished successfully');
        } finally {
            @unlink($photoMapPath);
            $this->deleteDirectory($photoCacheDir);
        }
        $this->step('output dir contents after python run', [
            'outputDir' => $outputDir,
            'contents'  => is_dir($outputDir)
                ? array_values(array_diff(scandir($outputDir), ['.', '..']))
                : null,
        ]);

        // 3. Zip the whole output folder: the reformatted xlsx, one photo folder per
        //    outlet, and the tool's own run log.
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->step('zip open attempted', ['zipPath' => $zipPath, 'openResult' => $openResult]);
        if ($openResult !== true) {
            throw new \RuntimeException("Could not create zip at {$zipPath} (ZipArchive error code {$openResult})");
        }
        $this->addFolderToZip($outputDir, $zip);
        $closeResult = $zip->close();
        $this->step('zip close attempted', [
            'closeOk' => $closeResult,
            'exists'  => file_exists($zipPath),
            'size'    => file_exists($zipPath) ? filesize($zipPath) : null,
        ]);
        if (!$closeResult || !file_exists($zipPath) || filesize($zipPath) === 0) {
            throw new \RuntimeException("Zip file was not created correctly at {$zipPath}");
        }
        $this->step('zip created', ['zipPath' => $zipPath, 'size' => filesize($zipPath)]);

        return ['xlsxPath' => $xlsxPath, 'outputDir' => $outputDir, 'zipPath' => $zipPath];
    }

    /**
     * Logs through both the normal Laravel Log facade AND a plain file_put_contents()
     * fallback to a dedicated file — so if Monolog's handler for storage/logs/laravel.log
     * is silently failing (e.g. permissions on that specific file), we still get a trace.
     */
    private function step(string $message, array $context = []): void
    {
        Log::info('GenerateInfiltrationReportJob: ' . $message, $context);
        @file_put_contents(
            public_path('infiltration_runs/infiltration_debug.log'),
            '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . json_encode($context) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Counts worksheets by reading xl/workbook.xml directly, rather than loading the
     * whole file through PhpSpreadsheet — cheap enough to call before every run.
     */
    private function countWorksheets(string $xlsxPath): ?int
    {
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return null;
        }
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        if ($workbookXml === false) {
            return null;
        }
        return substr_count($workbookXml, '<sheet ');
    }

    /**
     * Reads every hyperlink in the report's item sheet (sheet index 1) and resolves as
     * many as possible to a local file path, so runPythonTool() can hand Python a
     * ready-made {url: local_path} map instead of it having to fetch each one over
     * HTTP from this same server.
     */
    private function buildLocalPhotoMap(string $xlsxPath, string $cacheDir): array
    {
        $map = [];
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getSheet(1);
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $hyperlink = $cell->getHyperlink();
                    $url = $hyperlink ? $hyperlink->getUrl() : null;
                    if ($url && !isset($map[$url])) {
                        $local = $this->resolvePhotoUrlToLocalPath($url, $cacheDir);
                        if ($local) {
                            $map[$url] = $local;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->step('local photo map build failed - will fall back to HTTP for all links', ['error' => $e->getMessage()]);
        }
        return $map;
    }

    /**
     * Resolves a single photo hyperlink to a local file path. The report only ever
     * embeds two kinds of photo link: a direct asset() URL (single image — the path is
     * already relative to public/), or the report.download.images.zip route (multiple
     * images — an encrypted answer id needing a DB lookup, mirroring
     * ReportController::downloadImagesZip() but writing the zip to local disk instead
     * of streaming an HTTP response).
     */
    private function resolvePhotoUrlToLocalPath(string $url, string $cacheDir): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        if (str_contains($path, '/report/download-images-zip/')) {
            $token    = basename($path);
            $answerId = \App\Helpers\EncryptHelper::decrypt($token);
            if (!is_numeric($answerId)) {
                return null;
            }

            $answer = \App\Models\TempUserActivityAnswersData::find($answerId);
            if (!$answer) {
                return null;
            }
            $paths = json_decode($answer->user_answer ?? '', true);
            if (!is_array($paths) || empty($paths)) {
                return null;
            }

            @mkdir($cacheDir, 0755, true);
            $zipPath = $cacheDir . '/answer_' . $answerId . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return null;
            }
            $added = 0;
            foreach ($paths as $i => $p) {
                $abs = public_path(ltrim($p, '/'));
                if (file_exists($abs)) {
                    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'jpg';
                    $zip->addFile($abs, 'image_' . ($i + 1) . '.' . $ext);
                    $added++;
                }
            }
            $zip->close();
            if ($added === 0) {
                @unlink($zipPath);
                return null;
            }
            return $zipPath;
        }

        // Direct asset() link (single image) — path is already relative to public/.
        $abs = public_path(ltrim($path, '/'));
        return file_exists($abs) ? $abs : null;
    }

    /**
     * Run the Python tool in CLI mode:
     * `python3 infiltration_tool.py <input.xlsx> <output_dir> <local_photo_map.json>`.
     * Throws on a non-zero exit code so the caller can report the failure.
     */
    private function runPythonTool(string $xlsxPath, string $outputDir, string $photoMapPath): void
    {
        $pythonBin  = config('services.infiltration_tool.python_bin', 'python3');
        $scriptPath = base_path('python-tools/infiltration/infiltration_tool.py');

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException("Infiltration tool script not found at {$scriptPath}");
        }

        $process = new Process([$pythonBin, $scriptPath, $xlsxPath, $outputDir, $photoMapPath]);
        $process->setTimeout(800);
        $process->run();

        $this->step('python process finished', [
            'command'     => $process->getCommandLine(),
            'exitCode'    => $process->getExitCode(),
            'output'      => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
        ]);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "Infiltration tool exited with code {$process->getExitCode()}: "
                . trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }
    }

    private function addFolderToZip(string $folder, ZipArchive $zip, string $base = ''): void
    {
        foreach (scandir($folder) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path      = $folder . DIRECTORY_SEPARATOR . $item;
            $localPath = $base === '' ? $item : $base . '/' . $item;
            if (is_dir($path)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($path, $zip, $localPath);
            } else {
                $zip->addFile($path, $localPath);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
