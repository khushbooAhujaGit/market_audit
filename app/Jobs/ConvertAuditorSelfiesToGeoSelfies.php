<?php

namespace App\Jobs;

use App\Models\TempUserActivityAnswersData;
use App\Services\GeoStamp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Geo-stamps answer images (see GeoStamp). Items are ['answer_id' => id], or — from the web form —
 * ['file_path' => absolute path, 'answer' => answer, ...]: then the answer holding that file is
 * found by its path, so a stale or missing 'answer' never stamps the wrong record.
 */
class ConvertAuditorSelfiesToGeoSelfies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    protected $images;

    public function __construct(array $images)
    {
        $this->images = $images;
        Log::info('image job created with ' . count($images) . ' items.');
    }

    public function handle(): void
    {
        $answerIds = [];
        foreach ($this->images as $item) {
            $id = $this->answerIdOf($item);
            if ($id) $answerIds[$id] = true;
            else Log::warning('Geo stamp: no answer found for image', is_array($item) ? array_diff_key($item, ['answer' => 1]) : ['item' => $item]);
        }

        $totals = ['stamped' => 0, 'already' => 0, 'missing' => 0, 'failed' => 0];
        foreach (TempUserActivityAnswersData::whereIn('id', array_keys($answerIds))->get() as $answer) {
            $result = GeoStamp::stampAnswer($answer);
            foreach ($totals as $key => $n) $totals[$key] = $n + $result[$key];
        }
        Log::info('image job done: ' . json_encode($totals));
    }

    private function answerIdOf($item): ?int
    {
        if (is_numeric($item)) return (int) $item;
        if (!is_array($item)) return null;
        if (!empty($item['answer_id'])) return (int) $item['answer_id'];

        $relPath = ltrim(str_replace([public_path(), '\\'], ['', '/'], (string) ($item['file_path'] ?? '')), '/');
        if ($relPath === '') return null;
        // A JSON list of paths stores "/" as "\/"
        $forms = array_unique([$relPath, str_replace('/', '\\/', $relPath)]);
        $answer = $item['answer'] ?? null;
        foreach ($forms as $form) {
            if ($answer && str_contains((string) $answer->user_answer, $form)) return (int) $answer->id;
        }

        return TempUserActivityAnswersData::where(function ($query) use ($forms) {
            foreach ($forms as $form) $query->orWhere('user_answer', 'like', '%' . addcslashes($form, '%_\\') . '%');
        })->orderByDesc('id')->value('id');
    }
}
