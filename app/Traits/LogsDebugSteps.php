<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Logs through both the normal Laravel Log facade AND a plain file_put_contents()
 * fallback to a dedicated file — so if Monolog's handler for storage/logs/laravel.log
 * is silently failing (permissions, misconfiguration, etc.), there's still a trace.
 * Same pattern used by GenerateInfiltrationReportJob.
 */
trait LogsDebugSteps
{
    protected function step(string $logFile, string $prefix, string $message, array $context = []): void
    {
        Log::info($prefix . ': ' . $message, $context);
        @mkdir(dirname($logFile), 0755, true);
        @file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . $prefix . ': ' . $message . ' ' . json_encode($context) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
