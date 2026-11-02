<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * "City, State" of a lat/long (OpenStreetMap / Nominatim), kept forever in custom_report_geocodes
 * per ~1 km cell (key "cs:lat,lng"). Nominatim allows one request a second, so a single run may only
 * look up $budget uncached spots (-1 = unlimited); the rest return '' and fill in on a later run.
 * Returns '' without the custom_report_geocodes table (migration 2026_10_28_000003), and stops
 * looking up for the rest of the run after a failed request.
 */
class CityState
{
    private static float $last = 0.0;
    private static int $lookups = 0;
    private static bool $failed = false;
    private static ?bool $hasTable = null;

    /** Start a new run: a fresh lookup budget, and Nominatim is tried again. */
    public static function resetLookups(): void
    {
        self::$lookups = 0;
        self::$failed  = false;
    }

    public static function label($lat, $lng, int $budget = -1): string
    {
        if (!is_numeric($lat) || !is_numeric($lng) || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return '';
        }
        if (!(self::$hasTable ??= Schema::hasTable('custom_report_geocodes'))) {
            return '';
        }
        $key = 'cs:' . round((float) $lat, 2) . ',' . round((float) $lng, 2);

        $cached = DB::table('custom_report_geocodes')->where('lat_lng', $key)->value('address');
        if ($cached !== null) {
            return $cached;
        }
        if (self::$failed || ($budget >= 0 && self::$lookups >= $budget)) {
            return '';
        }
        self::$lookups++;

        $wait = 1.1 - (microtime(true) - self::$last);
        if ($wait > 0 && self::$last) {
            usleep((int) ($wait * 1e6));
        }
        self::$last = microtime(true);

        try {
            $data = Http::timeout(10)->withHeaders(['User-Agent' => 'MarketAuditReports/1.0'])
                ->get('https://nominatim.openstreetmap.org/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'json', 'zoom' => 10, 'addressdetails' => 1])
                ->throw()->json();
        } catch (\Throwable $e) {
            self::$failed = true;
            return '';
        }
        $a = $data['address'] ?? [];
        $city = '';
        foreach (['city', 'town', 'village', 'municipality', 'city_district', 'suburb', 'county', 'state_district'] as $f) {
            if (!empty($a[$f])) { $city = $a[$f]; break; }
        }
        $state = $a['state'] ?? ($a['region'] ?? '');
        $label = trim($city . ', ' . $state, ', ');
        if ($label !== '') {
            DB::table('custom_report_geocodes')->insertOrIgnore(['lat_lng' => $key, 'address' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $label;
    }
}
