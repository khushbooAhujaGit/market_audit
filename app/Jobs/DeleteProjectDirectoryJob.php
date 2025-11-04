<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class DeleteProjectDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $directoryPath;
    public function __construct($directoryPath)
    {
        $this->directoryPath = $directoryPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if(File::exists($this->directoryPath)){
            File::deleteDirectory($this->directoryPath);
        }
    }
}
