<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $projectId;

    /**
     * Create a new job instance.
     */
    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $project = Project::find($this->projectId);

        if ($project) {
            \Log::info("Deleting project: {$project->id}");
            $project->delete();
        } else {
            \Log::warning("Project not found: {$this->projectId}");
        }
    }
}
