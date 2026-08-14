<?php
namespace App\Jobs;

use App\Imports\ProjectDataImport;
use App\Notifications\DataImportedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessExcelImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    protected $filePath;
    protected $projectId;
    protected $templateId;
    protected $user;

    public function __construct($filePath, $projectId, $templateId, $user)
    {
        Log::info('Job called.');
        $this->filePath = $filePath;
        $this->projectId = $projectId;
        $this->templateId = $templateId;
        $this->user = $user;
    }

    public function handle()
    {
        // try{
        Log::info('Job starting.');

        Excel::queueImport(new ProjectDataImport($this->projectId, $this->templateId), $this->filePath);

        Log::info('Queued Excel import dispatched.');
        Log::info('Job done.');

        // Send notification
        // $this->user->notify(new DataImportedNotification());
        // Log::info('notification send.');
        // }
        // catch(\Exception $e){
        //     Log::info('JOB Error: '. $e->getMessage());
        // }
    }
}
