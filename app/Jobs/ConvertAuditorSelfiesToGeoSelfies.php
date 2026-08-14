<?php

namespace App\Jobs;

use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Traits\InterventionImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConvertAuditorSelfiesToGeoSelfies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InterventionImage;

    public int $tries   = 3;
    public int $timeout = 120;

    protected $images;

    /**
     * Create a new job instance.
     */
    public function __construct(array $images)
    {
        $this->images = $images;
        Log::info('image job created with ' . count($images) . ' items.');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('image job started');
        try {
            Log::info('image conversion started for ' . count($this->images) . ' images.');

            foreach ($this->images as $image) {

                $imagePath = $image['file_path'] ?? null;
                $latitude = $image['latitude'] ?? null;
                $longitude = $image['longitude'] ?? null;
                $answer = $image['answer'] ?? null;
                $directory = $image['directory'] ?? null;
                $userId = $image['user_id'] ?? null;

                if (!$imagePath || !$answer || !$directory) {
                    Log::warning('Skipping invalid image data', $image);
                    continue;
                }

                $user = User::find($userId);

                $this->convertToGeoLocationImage($imagePath, $latitude, $longitude, $answer, $directory, $user);

            }

        } catch (\Throwable $e) {
            Log::error('Failed to convert image: ' . $e->getMessage());
        }

    }

}
