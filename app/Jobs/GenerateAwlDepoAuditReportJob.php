<?php

namespace App\Jobs;

use App\Http\Controllers\Masters\ReportController;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds the AWL Depo Audit report for one distributor row: the app's standard distributor report
 * (the raw audit export) is produced in-process, then python-tools/awl_depo_audit/tool/server_run.py
 * (the AWL DB Audit Automation tool) fills the report template from it and, as chosen, writes a PDF,
 * the Styled Report PDF and a ZIP of the photos.
 *
 * Run with dispatchAfterResponse() like the other custom reports: the queue worker is only restarted
 * by cron every 30 minutes, too slow for a page the user is watching.
 */
class GenerateAwlDepoAuditReportJob
{
    use Dispatchable;

    public function __construct(private int $runId)
    {
    }

    public function handle(): void
    {
        set_time_limit(0);

        $run = DB::table('custom_report_runs')->find($this->runId);
        if (!$run) {
            return;
        }

        $this->update(['status' => 'running', 'message' => 'Collecting the audit export']);

        try {
            $options = json_decode((string) $run->options, true) ?: [];
            $outDir  = storage_path('app/awl-depo-audit-reports/' . $run->id);
            File::ensureDirectoryExists($outDir);

            $input = $this->buildExport($run, $outDir);

            $command = [
                config('services.infiltration_tool.python_bin', 'python3'),
                base_path('python-tools/awl_depo_audit/tool/server_run.py'),
                '--input', $input, '--outdir', $outDir, '--zip',
            ];
            if (!empty($options['pdf'])) {
                $command[] = '--pdf';
            }
            if (!empty($options['styled'])) {
                $command[] = '--styled';
            }
            if (!empty($options['book']) && !empty($options['physical'])) {
                array_push($command, '--book', $options['book'], '--physical', $options['physical']);
            }

            $result = null;
            $process = new Process($command);
            $process->setTimeout(1500);
            $process->run(function ($type, $buffer) use (&$result) {
                foreach (preg_split('/\r?\n/', $buffer) as $line) {
                    if (str_starts_with($line, 'STEP: ')) {
                        $this->update(['message' => substr($line, 6)]);
                    } elseif (str_starts_with($line, 'RESULT: ')) {
                        $result = json_decode(substr($line, 8), true);
                    }
                }
            });

            if (!$result) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Report generator exited with an error.');
            }
            if (empty($result['ok'])) {
                throw new \RuntimeException($result['error'] ?? 'Report generator failed.');
            }

            @unlink($input);

            $message = ($result['cd_name'] ?? 'Report') . ' ready';
            if (!empty($result['notes'])) {
                $message .= ' (' . implode(' ', $result['notes']) . ')';
            }

            $this->update([
                'status'      => 'done',
                'message'     => mb_substr($message, 0, 250),
                'xlsx_path'   => $result['xlsx'] ?? null,
                'pdf_path'    => $result['pdf'] ?? null,
                'styled_path' => $result['styled'] ?? null,
                'zip_path'    => $result['zip'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AWL Depo Audit report failed', ['run' => $this->runId, 'error' => $e->getMessage()]);
            $this->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 250)]);
        }
    }

    /** The standard distributor report for the row, i.e. the raw audit export the AWL tool reads. */
    private function buildExport(object $run, string $outDir): string
    {
        $template = DB::table('project_templates')->find($run->project_template_id);
        Auth::loginUsingId($run->user_id);

        $request = new Request([
            'project_id'       => $template->project_id,
            'template_name_id' => $template->template_name_id,
            'row_id'           => $run->row_id,
            'data_get_helper'  => 'filled',
            'user_details'     => 1,
            'date_required'    => 1,
        ]);

        $response = app(ReportController::class)->project_distributor_report($request);
        $source   = $response->getFile()->getPathname();

        $input = $outDir . '/audit_export.xlsx';
        copy($source, $input);
        @unlink($source);

        return $input;
    }

    private function update(array $values): void
    {
        DB::table('custom_report_runs')->where('id', $this->runId)->update($values + ['updated_at' => now()]);
    }
}
