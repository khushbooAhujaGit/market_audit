<?php

namespace App\Jobs;

use App\Models\TempUserActivityAnswersData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreActivityAnswers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60; 
    public string $connection = 'redis';
    public string $queue = 'activity_answers';

    public function __construct(
        private array $toCreate,
        private array $toUpdate,
        private mixed $rowId,
        private mixed $activityId,
        private mixed $groupId,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            if (!empty($this->toCreate)) {
                TempUserActivityAnswersData::insert($this->toCreate);
            }

            foreach ($this->toUpdate as $questionId => $updatePayload) {
                TempUserActivityAnswersData::where('row_id', $this->rowId)
                    ->where(function ($q) {
                        $q->where('activity_id', $this->activityId)
                            ->orWhere('activity_group_name_id', $this->groupId);
                    })
                    ->where('question_id', $questionId)
                    ->update($updatePayload);
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('StoreActivityAnswers job failed', [
            'row_id'      => $this->rowId,
            'activity_id' => $this->activityId,
            'error'       => $e->getMessage(),
        ]);
    }
}
