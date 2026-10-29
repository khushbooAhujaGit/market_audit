<?php

namespace App\Jobs;

use App\Services\TemplateDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Downloads the links queued in a template's file columns. Dispatched after the data upload
 * (dispatchAfterResponse), so with the sync queue it runs once the page has been sent.
 */
class FetchTemplateDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $projectTemplateId)
    {
    }

    public function handle(): void
    {
        TemplateDocuments::fetchQueued($this->projectTemplateId);
    }
}
