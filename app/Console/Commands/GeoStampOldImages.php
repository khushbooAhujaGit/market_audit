<?php

namespace App\Console\Commands;

use App\Models\TempUserActivityAnswersData;
use App\Services\GeoStamp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Geo-stamps images of answers saved before stamping ran (or when it failed), using each answer's
 * own latitude / longitude and time. Images already stamped are left alone, so it can be run again.
 * Address lookups are limited to one a second (OpenStreetMap), so large runs take a while: use
 * --limit to do it in batches.
 *
 *   php artisan images:geo-stamp --dry-run                     (only report)
 *   php artisan images:geo-stamp --limit=500                    (stamp the first 500 answers needing it)
 *   php artisan images:geo-stamp --project=138 --from=2026-10-01 --to=2026-10-31
 */
class GeoStampOldImages extends Command
{
    protected $signature = 'images:geo-stamp
        {--dry-run : Only report what would be stamped}
        {--project= : Only this project id}
        {--activity= : Only this activity id}
        {--from= : Answers saved on or after this date (Y-m-d)}
        {--to= : Answers saved on or before this date (Y-m-d)}
        {--limit=0 : Stop after this many answers with images to stamp (0 = all)}';

    protected $description = 'Geo-stamp the images of older answers (address, coordinates and time) that are not stamped yet';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = DB::table('temp_user_activity_answers_data as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->where('q.question_type', 'Image')
            ->whereNotNull('a.user_answer')->where('a.user_answer', '!=', '')
            ->when($this->option('activity'), fn($q, $id) => $q->where('a.activity_id', (int) $id))
            ->when($this->option('from'), fn($q, $date) => $q->whereDate('a.created_at', '>=', $date))
            ->when($this->option('to'), fn($q, $date) => $q->whereDate('a.created_at', '<=', $date))
            ->when($this->option('project'), fn($q, $id) => $q->whereIn('a.row_id',
                DB::table('project_template_name_values_new as v')
                    ->join('project_templates as pt', 'pt.id', '=', 'v.project_template_id')
                    ->where('pt.project_id', (int) $id)->select('v.id')))
            ->orderBy('a.id')
            ->select('a.id');

        $counts = ['answers' => 0, 'rows' => [], 'to_stamp' => 0, 'to_repair' => 0, 'stamped' => 0, 'repaired' => 0,
            'already' => 0, 'missing' => 0, 'failed' => 0, 'no_location' => 0];
        $done = 0;

        // One "image answer" = one answered Image question of one row (an outlet / distributor
        // usually has several), so it counts more than the rows answered
        $query->chunk(500, function ($chunk) use (&$counts, &$done, $dryRun, $limit) {
            foreach (TempUserActivityAnswersData::whereIn('id', $chunk->pluck('id'))->orderBy('id')->get() as $answer) {
                [$paths] = GeoStamp::imagePaths($answer->user_answer);
                if (!$paths) continue;
                $counts['answers']++;
                $counts['rows'][$answer->row_id] = true;

                $toStamp = $toRepair = 0;
                foreach ($paths as $p) {
                    if (GeoStamp::isStamped($p)) $counts['already']++;
                    elseif (GeoStamp::stampedSibling($p)) $toRepair++;
                    elseif (is_file(public_path($p))) $toStamp++;
                    else $counts['missing']++;
                }
                if ($toStamp && !GeoStamp::hasLocation($answer->latitude, $answer->longitude)) {
                    $counts['no_location']++;
                    $toStamp = 0;
                }
                if (!$toStamp && !$toRepair) continue;

                $counts['to_stamp'] += $toStamp;
                $counts['to_repair'] += $toRepair;
                if (!$dryRun) {
                    $result = GeoStamp::stampAnswer($answer);
                    $counts['stamped'] += $result['stamped'];
                    $counts['repaired'] += $result['repaired'];
                    $counts['failed'] += $result['failed'];
                }

                $done++;
                if ($done % 25 === 0) {
                    $this->line("  … {$done} image answers done (" . ($dryRun ? "{$counts['to_stamp']} images to stamp" : "{$counts['stamped']} images stamped") . ')');
                }
                if ($limit && $done >= $limit) return false;
            }
        });

        $this->table(['', 'Count'], [
            ['Rows (outlets / distributors) with image answers', count($counts['rows'])],
            ['Image answers (one per answered Image question)', $counts['answers']],
            [$dryRun ? 'Images to stamp' : 'Images needing a stamp', $counts['to_stamp']],
            ['Images stamped now', $dryRun ? '— (dry run)' : $counts['stamped']],
            ['Half-done stamps to fix (run stopped before)', $dryRun ? $counts['to_repair'] : $counts['repaired']],
            ['Already stamped', $counts['already']],
            ['File not on the server', $counts['missing']],
            ['Answers without latitude / longitude (skipped)', $counts['no_location']],
            ['Failed (see the log)', $counts['failed']],
        ]);
        if ($limit && $done >= $limit) {
            $this->info("Stopped at --limit={$limit}. Run it again to continue with the next ones.");
        }
        return self::SUCCESS;
    }
}
