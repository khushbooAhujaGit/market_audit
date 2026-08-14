<?php

namespace App\Jobs;

use App\Services\OneDrivePersonalService;
use App\Services\OutlookMailerService;
use App\Traits\LogsDebugSteps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the Infiltration Audit report (reusing GenerateInfiltrationReportJob's
 * generateZip(), the same pipeline the email and direct-download flows use) and
 * uploads the result into the requesting user's own OneDrive (delegated OAuth — see
 * OneDrivePersonalService) instead of emailing it. Runs queued since the upload,
 * like the email attachment, can take a while.
 *
 * Sibling to UploadInfiltrationReportToSharedOneDriveJob, which does the same thing
 * but uploads into one fixed account's OneDrive via app-only auth.
 */
class UploadInfiltrationReportToPersonalOneDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogsDebugSteps;

    public int $tries   = 1;
    public int $timeout = 900;

    protected $validated;
    protected $request;
    protected $user;
    protected $mailer;
    protected $logFile;

    public function __construct($validated, $request, $user)
    {
        $this->validated = $validated;
        $this->request   = $request;
        $this->user      = $user;
        $this->mailer    = new OutlookMailerService();
        $this->logFile   = public_path('infiltration_runs/onedrive_debug.log');
    }

    public function handle(): void
    {
        $this->step($this->logFile, 'UploadInfiltrationReportToPersonalOneDriveJob', 'started', ['user' => $this->user->email]);

        $generator = new GenerateInfiltrationReportJob($this->validated, $this->request, $this->user);
        $xlsxPath  = null;
        $outputDir = null;
        $zipPath   = null;

        try {
            ['xlsxPath' => $xlsxPath, 'outputDir' => $outputDir, 'zipPath' => $zipPath] = $generator->generateZip();

            $fileName = 'Infiltration Audit Report - ' . date('d-m-Y') . '.zip';
            $result   = app(OneDrivePersonalService::class)->uploadZip($this->user, $zipPath, $fileName);

            $this->step($this->logFile, 'UploadInfiltrationReportToPersonalOneDriveJob', 'upload complete', $result);

            $linkHtml = $result['webUrl']
                ? '<p><a href="' . htmlspecialchars($result['webUrl']) . '">Open it in OneDrive</a></p>'
                : '';
            $this->mailer->sendMail(
                $this->user->email,
                'Infiltration Audit Report — Uploaded to OneDrive',
                '<p>Hello ' . htmlspecialchars($this->user->name) . ',</p>'
                . '<p>Your Infiltration Audit report has been uploaded to the "Infiltration Report" '
                . 'folder in your OneDrive as <strong>' . htmlspecialchars($fileName) . '</strong>.</p>'
                . $linkHtml
            );
            $this->step($this->logFile, 'UploadInfiltrationReportToPersonalOneDriveJob', 'confirmation email sent');
        } catch (\Throwable $e) {
            $this->step($this->logFile, 'UploadInfiltrationReportToPersonalOneDriveJob', 'FAILED', ['error' => $e->getMessage()]);
            $this->mailer->sendMail(
                $this->user->email,
                'Infiltration Audit Report — OneDrive Upload Failed',
                '<p>Hello ' . htmlspecialchars($this->user->name) . ',</p>'
                . '<p>Sorry, the Infiltration Audit report could not be uploaded to OneDrive. '
                . 'Please contact support with the details below.</p>'
                . '<pre style="white-space:pre-wrap;font-size:12px;color:#555;">'
                . htmlspecialchars(substr($e->getMessage(), 0, 2000)) . '</pre>'
            );
        } finally {
            if ($xlsxPath) @unlink($xlsxPath);
            if ($zipPath) @unlink($zipPath);
            if ($outputDir && is_dir($outputDir)) {
                $this->deleteDirectory($outputDir);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
