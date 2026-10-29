<?php

namespace App\Services;

use App\Jobs\ConvertAuditorSelfiesToGeoSelfies;
use App\Models\TempUserActivityAnswersData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

/**
 * Geo-stamps answer images: a box at the bottom with the address (from the answer's latitude /
 * longitude), the coordinates and the answer time. A stamped file is saved next to the original as
 * "geo_<name>" and the original removed, so a file is never stamped twice and any answer format
 * (one path, a JSON list of paths) keeps working. Used by ConvertAuditorSelfiesToGeoSelfies.
 */
class GeoStamp
{
    public const PREFIX = 'geo_';

    /** Stamps the unstamped images of a row/activity (one instance, or every instance when null). */
    public static function queueFor(int $rowId, int $activityId, ?int $sequence = null): void
    {
        $ids = DB::table('temp_user_activity_answers_data as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->where('q.question_type', 'Image')
            ->where('a.row_id', $rowId)
            ->where('a.activity_id', $activityId)
            ->when($sequence !== null, fn($query) => $sequence > 0
                ? $query->where('a.activity_sequence', $sequence)
                : $query->where(fn($w) => $w->whereNull('a.activity_sequence')->orWhere('a.activity_sequence', 0)))
            ->whereNotNull('a.user_answer')->where('a.user_answer', '!=', '')
            ->get(['a.id', 'a.user_answer'])
            ->filter(fn($answer) => self::hasUnstamped($answer->user_answer))
            ->pluck('id')
            ->all();
        self::queueAnswers($ids);
    }

    /** Stamps the given answers' images (in the background when a queue worker runs, else right away). */
    public static function queueAnswers(array $answerIds): void
    {
        $answerIds = array_values(array_unique(array_filter(array_map('intval', $answerIds))));
        if ($answerIds) {
            dispatch(new ConvertAuditorSelfiesToGeoSelfies(array_map(fn($id) => ['answer_id' => $id], $answerIds)));
        }
    }

    /**
     * Stamps every unstamped image of one answer and writes the new paths back (in the same format).
     * Returns counts: ['stamped' => n, 'already' => n, 'missing' => n, 'failed' => n, 'no_location' => bool].
     */
    public static function stampAnswer(TempUserActivityAnswersData $answer): array
    {
        $result = ['stamped' => 0, 'already' => 0, 'repaired' => 0, 'missing' => 0, 'failed' => 0, 'no_location' => false];
        [$paths, $format] = self::imagePaths($answer->user_answer);
        if (!$paths) return $result;

        $hasLocation = self::hasLocation($answer->latitude, $answer->longitude);
        $time = $answer->created_at ? Carbon::parse($answer->created_at) : now();
        foreach ($paths as $i => $path) {
            if (self::isStamped($path)) { $result['already']++; continue; }
            // A stamp stopped half way (e.g. Ctrl+C) before the answer got the new path: use that copy
            $stampedSibling = self::stampedSibling($path);
            if ($stampedSibling) {
                $paths[$i] = $stampedSibling;
                self::savePaths($answer->id, $paths, $format);
                if (is_file(public_path($path))) @unlink(public_path($path));
                $result['repaired']++;
                continue;
            }
            if (!is_file(public_path($path))) { $result['missing']++; continue; }
            if (!$hasLocation) { $result['no_location'] = true; continue; }
            try {
                // Order matters, so stopping at any point loses nothing: stamped copy saved, the
                // answer points to it, then the original is removed
                $paths[$i] = self::stampFile($path, $answer->latitude, $answer->longitude, $time, false);
                self::savePaths($answer->id, $paths, $format);
                @unlink(public_path($path));
                $result['stamped']++;
            } catch (\Throwable $e) {
                $paths[$i] = $path;
                $result['failed']++;
                Log::error("Geo stamp failed for answer {$answer->id} ({$path}): " . $e->getMessage());
            }
        }
        return $result;
    }

    /** Writes an answer's image paths back in their format; its updated_at stays as it was. */
    private static function savePaths(int $answerId, array $paths, string $format): void
    {
        // The answer's updated_at (verification time in reports) stays as it was: the column
        // refreshes itself on update (ON UPDATE CURRENT_TIMESTAMP) unless it is set
        DB::table('temp_user_activity_answers_data')->where('id', $answerId)
            ->update([
                'user_answer' => match ($format) {
                    'json'  => json_encode(array_values($paths)),
                    'comma' => implode(',', $paths),
                    default => $paths[0],
                },
                'updated_at' => DB::raw('updated_at'),
            ]);
    }

    /** The stamped copy of a missing original ("geo_<name>" next to it), if it exists. */
    public static function stampedSibling(string $relPath): ?string
    {
        $dir = trim(str_replace('\\', '/', dirname($relPath)), '/');
        $candidate = ($dir !== '' && $dir !== '.' ? $dir . '/' : '') . self::PREFIX . basename($relPath);
        return is_file(public_path($candidate)) ? $candidate : null;
    }

    /**
     * Stamps one image file (path relative to public/); returns the new relative path. The original
     * is removed unless $removeOriginal is false (then the caller removes it once the answer is updated).
     */
    public static function stampFile(string $relPath, $latitude, $longitude, Carbon $time, bool $removeOriginal = true): string
    {
        $absPath = public_path($relPath);
        $img = ImageManager::gd()->read($absPath);
        $img->resize(1440, 1920);

        $y = 1920 - 150 - 10;
        $img->drawRectangle(0, $y - 20, function ($rectangle) {
            $rectangle->size(1440, 190);
            $rectangle->background('rgba(64, 63, 62, 0.8)');
        });

        $address = self::address($latitude, $longitude);
        $lines = [];
        if ($address['main'] !== '') $lines[] = [$address['main'], 28];
        foreach (array_filter(explode("\n", wordwrap($address['location'], 130, "\n", false))) as $line) $lines[] = [$line, 23];
        $lines[] = ['Latitude : ' . $latitude, 23];
        $lines[] = ['Longitude : ' . $longitude, 23];
        $lines[] = ['Time : ' . $time->format('d-m-Y H:i:s'), 23];

        foreach ($lines as [$text, $size]) {
            $img->text($text, 20, $y, function ($font) use ($size) {
                $font->size($size);
                $font->filename(public_path('assets/fonts/arial/ARIAL.TTF'));
                $font->color('white');
                $font->align('start');
                $font->valign('middle');
                $font->lineHeight(1.5);
            });
            $y += 30;
        }

        $dir = trim(str_replace('\\', '/', dirname($relPath)), '/');
        $name = self::PREFIX . basename($relPath);
        if (is_file(public_path($dir . '/' . $name))) {
            $name = self::PREFIX . uniqid() . '_' . basename($relPath);
        }
        $newRelPath = ($dir !== '' && $dir !== '.' ? $dir . '/' : '') . $name;
        $img->save(public_path($newRelPath));
        @chmod(public_path($newRelPath), 0777);
        if ($removeOriginal && is_file($absPath)) @unlink($absPath);
        return $newRelPath;
    }

    /** [paths, 'single'|'json'|'comma'] of an image answer. */
    public static function imagePaths(?string $userAnswer): array
    {
        $userAnswer = trim((string) $userAnswer);
        if ($userAnswer === '') return [[], 'single'];
        $decoded = json_decode($userAnswer, true);
        if (is_array($decoded)) {
            return [array_values(array_filter($decoded, fn($p) => is_string($p) && $p !== '')), 'json'];
        }
        // Several pre-uploaded paths saved as "a.jpg,b.jpg"
        if (str_contains($userAnswer, ',') && !is_file(public_path($userAnswer))) {
            $parts = array_map('trim', explode(',', $userAnswer));
            if (count(array_filter($parts, fn($p) => preg_match('/\.(jpe?g|png|gif|webp)$/i', $p))) === count($parts)) {
                return [$parts, 'comma'];
            }
        }
        return [[$userAnswer], 'single'];
    }

    /**
     * Already stamped: our "geo_" files, and images stamped before it existed (named
     * "<13-char uniqid><original name>" and resized to 1440x1920 by the stamp).
     */
    public static function isStamped(string $relPath): bool
    {
        $name = basename($relPath);
        if (str_starts_with($name, self::PREFIX)) return true;
        if (preg_match('/^[0-9a-f]{13}/', $name) && is_file(public_path($relPath))) {
            $size = @getimagesize(public_path($relPath));
            return $size && $size[0] === 1440 && $size[1] === 1920;
        }
        return false;
    }

    public static function hasLocation($latitude, $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude) && ((float) $latitude != 0 || (float) $longitude != 0);
    }

    private static function hasUnstamped(?string $userAnswer): bool
    {
        [$paths] = self::imagePaths($userAnswer);
        foreach ($paths as $path) {
            if (!str_starts_with(basename($path), self::PREFIX)) return true;
        }
        return false;
    }

    /**
     * Address of a location from OpenStreetMap (Nominatim): kept for 30 days per ~10 m spot, and at
     * most one lookup a second (Nominatim's limit). Empty when the lookup fails — the image is still
     * stamped with the coordinates and time.
     */
    private static function address($latitude, $longitude): array
    {
        $key = 'geo_stamp_address:' . round((float) $latitude, 4) . ',' . round((float) $longitude, 4);
        $cached = Cache::get($key);
        if (is_array($cached)) return $cached;

        static $lastLookup = 0.0;
        $wait = 1.1 - (microtime(true) - $lastLookup);
        if ($wait > 0) usleep((int) ($wait * 1000000));
        $lastLookup = microtime(true);

        try {
            $data = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'MarketAudit/1.0 (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude, 'lon' => $longitude, 'format' => 'json', 'addressdetails' => 1,
                ])->json();
        } catch (\Throwable $e) {
            Log::warning('Geo stamp address lookup failed: ' . $e->getMessage());
            return ['main' => '', 'location' => ''];
        }
        if (!is_array($data) || empty($data['display_name'])) {
            return ['main' => '', 'location' => ''];
        }

        $address = [
            'main'     => trim(($data['address']['state_district'] ?? '') . ', ' . ($data['address']['state'] ?? '') . ', India', ', '),
            'location' => (string) $data['display_name'],
        ];
        Cache::put($key, $address, now()->addDays(30));
        return $address;
    }
}
