<?php

namespace App\Jobs;

use App\Http\Controllers\Masters\ReportController;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Builds the "Godrej" report: runs the Report Dump for the template's activity and date range, then
 * reshapes it into the client's Required Format (one row per audit) and zips the photos.
 *
 * Like GenerateGokulReportJob it runs with dispatchAfterResponse() (the queue worker is only
 * cron-restarted every 30 minutes).
 */
class GenerateGodrejReportJob
{
    use Dispatchable;

    private const CHANNEL = 'Ice Cream';
    private const DSM     = 'Ice Cream-A25 - SAMPATH(ICE)';

    /** Dump header of an Excel image-link column => short name used for the photo file. */
    private const PHOTO_NAMES = [
        'Outlet Status → Image of Outlet'            => 'Outlet',
        'Outlet Status → Image of the Closed Outlet' => 'Closed outlet',
        'Asset Availability → Image'                 => 'Asset',
    ];

    /**
     * Required Format column => source. A string is the exact Report Dump header (the last one of
     * that name when it repeats, as 'ASE Name' does: master column first, audit question later);
     * array forms are special sources.
     */
    private const COLUMNS = [
        'Date of the Audit'                                 => ['date' => 'Date'],
        'Name of the Auditor'                               => 'User',
        'Division'                                          => 'Division',
        'Channel'                                           => ['static' => self::CHANNEL],
        'Divisional DSM'                                    => ['static' => self::DSM],
        'Divisional ASM'                                    => 'ASM Name',
        'Name of the ASE'                                   => 'ASE Name',
        'City/Upcountry'                                    => 'City',
        'Customer Classification'                           => 'Customer Classification',
        'Customer SAPCODE'                                  => 'SAP Code',
        'Customer Name(DB/Agent)'                           => 'Name',
        'Customer Address(DB/Agent)'                        => ['geocode' => true],
        'Customer Number(DB/Agent)'                         => 'Outlet Status → Customer Number (DB)',
        'Outlet Bizom ID'                                   => 'Outlet Bizom ID',
        'Outlet Name'                                       => 'Outlet Status → Name of the Outlet',
        'Outlet Full Address'                               => ['geocode' => true],
        'Outlet Contact No'                                 => 'Outlet Status → Mention the Contact Number of Outlet Owner',
        'Outlet Status'                                     => 'Outlet Status',
        'Asset Availability'                                => 'Asset Availability',
        'QR Code ("NA", if asset not Available)(Asset 1)'   => 'Asset Availability → QR Code ("NA", if asset not Available)(Asset 1)',
        'Asset Serial No'                                   => 'Asset Availability → Asset Serial No',
        'Type'                                              => 'Asset Availability → What is the Type of Asset available',
        'Capacity (Ltrs)'                                   => 'Asset Availability → Capacity (Ltrs)',
        'Brand'                                             => 'Asset Availability → Brand',
        'Image '                                            => ['link' => 'Asset Availability → Image'],
        'Status'                                            => 'Asset Availability → Condition of the Asset',
        'Shifted'                                           => 'Shifted',
        'If Shifted, Reason for the shifting'               => 'Shifted → If Shifted, Reason for the shifting',
        'Shifted to'                                        => 'Shifted → Shifted to',
        'If Shifted, Sapcode'                               => 'Shifted → If Shifted, SAP code',
        'if Shifted, Customer Name(DB/Agent)'               => 'Shifted → If Shifted, Customer Name(DB/Agent)',
        'if Shifted, Customer Address(DB/Agent)'            => 'Shifted → If Shifted, Customer Address(DB/Agent)',
        'if Shifted, Customer Phone Number(DB/Agent)'       => 'Shifted → If Shifted, Customer Phone Number(DB/Agent)',
        'If shifted, Outlet Id'                             => 'Shifted → If shifted, Outlet Id',
        'If Shifted, Outlet Name'                           => 'Shifted → If Shifted, Outlet Name',
        'If Shifted, Outlet Full Address'                   => 'Shifted → If Shifted, Outlet Full Address',
        'If Shifted, Outlet Contact'                        => 'Shifted → If Shifted, Outlet Contact',
        'Reason for Asset Not Available'                    => 'Asset Availability → Reason for Asset Not Available',
        'If Permanently Closed/Asset not Available'         => 'Asset Availability → Reason for Asset Not Available',
        'Image of Outlet'                                   => ['link' => 'Outlet Status → Image of Outlet'],
        'Latitude'                                          => 'latitude',
        'Longitude'                                         => 'longitude',
        'Open Location'                                     => ['maps' => true],
    ];

    private float $lastGeocodeAt = 0;

    public function __construct(private int $runId)
    {
    }

    public function handle(): void
    {
        set_time_limit(0);
        \App\Services\CityState::resetLookups();

        $run = DB::table('custom_report_runs')->find($this->runId);
        if (!$run) {
            return;
        }

        $this->update(['status' => 'running', 'message' => 'Preparing the Report Dump']);
        $dumpPath = null;

        try {
            $outDir = storage_path('app/godrej-reports/' . $run->id);
            File::ensureDirectoryExists($outDir);

            $dumpPath = $this->buildDump($run);

            $this->update(['message' => 'Building the report']);
            [$table, $links] = $this->readDump($dumpPath);

            [$xlsx, $rowCount] = $this->writeWorkbook($table, $links, $outDir);
            $zip = $this->writeZip($table, $links, $outDir);

            $this->update([
                'status'    => 'done',
                'message'   => "{$rowCount} audit(s)" . ($zip ? '' : ', no photos'),
                'xlsx_path' => $xlsx,
                'zip_path'  => $zip,
            ]);
        } catch (\Throwable $e) {
            Log::error('Godrej report failed: ' . $e->getMessage(), ['run' => $run->id]);
            $this->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 240)]);
        } finally {
            if ($dumpPath) {
                @unlink($dumpPath);
            }
        }
    }

    /** The standard Report Dump of the template's activity, as the Report page would produce it. */
    private function buildDump(object $run): string
    {
        $template = DB::table('project_templates')->find($run->project_template_id);
        Auth::loginUsingId($run->user_id);

        $request = new Request([
            'project_id'       => $template->project_id,
            'template_name_id' => $template->template_name_id,
            'from_date'        => $run->header_value,
            'to_date'          => $run->sub_header_value,
            'data_get_helper'  => 'filled',
            'activity_id'      => [(int) $template->activity_group_name_id_or_activity_id],
            'user_details'     => 1,
            'date_required'    => 1,
        ]);

        // false: one header row with the old one-line headers ("Outlet Status → Name of the Outlet"),
        // no relation row or report header block — readDump() and COLUMNS below expect that layout
        return app(ReportController::class)
            ->buildReportFile($request, $request->only(['project_id', 'template_name_id', 'from_date', 'to_date', 'data_get_helper']), false)['path'];
    }

    /**
     * @return array{0: array<int, array<string, string>>, 1: array<int, array<string, string>>}
     *   rows keyed by dump header (last of a repeated header wins), and each row's image links keyed by header.
     */
    private function readDump(string $path): array
    {
        $ws = IOFactory::load($path)->getActiveSheet();

        $headerRow = null;
        for ($r = 1; $r <= 12; $r++) {
            if (trim((string) $ws->getCell("A{$r}")->getValue()) === 'S.NO') {
                $headerRow = $r;
                break;
            }
        }
        if (!$headerRow) {
            throw new \RuntimeException('The Report Dump has no header row.');
        }

        $lastCol = Coordinate::columnIndexFromString($ws->getHighestColumn());
        $headers = [];
        for ($c = 1; $c <= $lastCol; $c++) {
            $headers[$c] = trim((string) $ws->getCell([$c, $headerRow])->getValue());
        }

        $table = $links = [];
        for ($r = $headerRow + 1; $r <= $ws->getHighestRow(); $r++) {
            $row = $rowLinks = [];
            $any = false;
            foreach ($headers as $c => $h) {
                $cell = $ws->getCell([$c, $r]);
                $value = trim((string) $cell->getFormattedValue());
                $any = $any || $value !== '';
                $row[$h] = $value;
                if ($cell->hasHyperlink() && $value !== '') {
                    $rowLinks[$h] = $cell->getHyperlink()->getUrl();
                }
            }
            if ($any) {
                $table[] = $row;
                $links[] = $rowLinks;
            }
        }

        return [$table, $links];
    }

    private function writeWorkbook(array $table, array $links, string $outDir): array
    {
        $columns = self::COLUMNS;
        if (!filter_var(env('OPEN_LOCATION_COLUMN', true), FILTER_VALIDATE_BOOLEAN)) {
            unset($columns['Open Location']);
        }
        $names = array_keys($columns);
        $sheet = ($book = new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Required Format');

        foreach ($names as $i => $name) {
            $sheet->setCellValue([$i + 1, 1], $name);
        }

        $geoCols = [];
        foreach ($table as $n => $row) {
            $r = $n + 2;
            // Outlet Closed / Permanently Closed: the Asset Availability and Shifted answers are blanked
            // (Permanently Closed keeps only the Reason for Asset Not Available). Revertible: GODREJ_CASCADE_BLANK=false.
            $status = strtolower(trim((string) ($row['Outlet Status'] ?? '')));
            $cascade = filter_var(env('GODREJ_CASCADE_BLANK', true), FILTER_VALIDATE_BOOLEAN)
                ? (str_contains($status, 'permanent') ? 'permanent' : ($status === 'closed' || str_contains($status, 'closed') ? 'closed' : null))
                : null;
            foreach (array_values($columns) as $i => $src) {
                $col = $i + 1;
                if ($cascade) {
                    $key = is_string($src) ? $src : (is_array($src) ? ($src['link'] ?? '') : '');
                    $inGroup = $key === 'Asset Availability' || $key === 'Shifted'
                        || str_starts_with($key, 'Asset Availability → ') || str_starts_with($key, 'Shifted → ');
                    $isReason = $key === 'Asset Availability → Reason for Asset Not Available';
                    if ($inGroup && !($cascade === 'permanent' && $isReason)) {
                        continue;
                    }
                }
                if (is_string($src)) {
                    $sheet->setCellValueExplicit([$col, $r], $row[$src] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } elseif (isset($src['static'])) {
                    $sheet->setCellValue([$col, $r], $src['static']);
                } elseif (isset($src['date'])) {
                    $this->writeDate($sheet, $col, $r, $row[$src['date']] ?? '');
                } elseif (isset($src['link'])) {
                    if ($url = $links[$n][$src['link']] ?? null) {
                        $url = $this->liveUrl($url);
                        $sheet->setCellValue([$col, $r], 'Download');
                        $sheet->getCell([$col, $r])->getHyperlink()->setUrl($url);
                        $sheet->getStyle([$col, $r])->getFont()->getColor()->setRGB('0563C1');
                    }
                } elseif (isset($src['maps'])) {
                    $lat = $row['latitude'] ?? '';
                    $lng = $row['longitude'] ?? '';
                    if (is_numeric($lat) && is_numeric($lng) && !((float) $lat === 0.0 && (float) $lng === 0.0)) {
                        $sheet->setCellValue([$col, $r], \App\Services\CityState::label($lat, $lng) ?: 'Open Location');
                        $sheet->getCell([$col, $r])->getHyperlink()->setUrl('https://www.google.com/maps?q=' . trim($lat) . ',' . trim($lng));
                        $sheet->getStyle([$col, $r])->getFont()->getColor()->setRGB('0563C1');
                    }
                } elseif (isset($src['geocode'])) {
                    $geoCols[$col] = true;
                }
            }
        }

        // Addresses from the audit's coordinates (the same lookup serves both address columns of a row).
        $this->update(['message' => 'Looking up addresses']);
        $total = count($table);
        foreach ($table as $n => $row) {
            $r = $n + 2;
            $address = $this->address($row['latitude'] ?? '', $row['longitude'] ?? '');
            foreach (array_keys($geoCols) as $col) {
                $sheet->setCellValue([$col, $r], $address);
            }
            if ($n % 10 === 0) {
                $this->update(['message' => 'Looking up addresses (' . ($n + 1) . ' of ' . $total . ')']);
            }
        }

        $this->style($sheet, count($names), $total + 1, $names);

        $path = $outDir . '/Godrej Asset Verification.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return [$path, $total];
    }

    private function writeDate($sheet, int $col, int $r, string $value): void
    {
        try {
            $sheet->setCellValue([$col, $r], XlDate::PHPToExcel(Carbon::parse($value)->startOfDay()));
            $sheet->getStyle([$col, $r])->getNumberFormat()->setFormatCode('dd-mmm-yyyy');
        } catch (\Throwable $e) {
            $sheet->setCellValue([$col, $r], $value);
        }
    }

    /** Header look, widths and borders limited to the filled range. */
    private function style($sheet, int $cols, int $lastRow, array $names): void
    {
        $range = 'A1:' . Coordinate::stringFromColumnIndex($cols) . $lastRow;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $head = $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($cols) . '1');
        $head->getFont()->setBold(true);
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $head->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        for ($c = 1; $c <= $cols; $c++) {
            $w = str_contains($names[$c - 1], 'Address') ? 45 : (str_contains($names[$c - 1], 'Name') ? 26 : 18);
            $sheet->getColumnDimensionByColumn($c)->setWidth($w);
        }
        $sheet->freezePane('A2');
    }

    private function address(string $lat, string $lng): string
    {
        if (!is_numeric($lat) || !is_numeric($lng) || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return '';
        }
        $key = round((float) $lat, 5) . ',' . round((float) $lng, 5);

        $cached = DB::table('custom_report_geocodes')->where('lat_lng', $key)->value('address');
        if ($cached !== null) {
            return $cached;
        }

        // Nominatim's usage policy: at most one request per second.
        $wait = 1.1 - (microtime(true) - $this->lastGeocodeAt);
        if ($wait > 0 && $this->lastGeocodeAt) {
            usleep((int) ($wait * 1e6));
        }
        $this->lastGeocodeAt = microtime(true);

        try {
            $data = Http::timeout(15)->withHeaders(['User-Agent' => 'MarketAuditReports/1.0'])
                ->get('https://nominatim.openstreetmap.org/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'json'])->json();
            $address = (string) ($data['display_name'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
        if ($address !== '') {
            DB::table('custom_report_geocodes')->insertOrIgnore(['lat_lng' => $key, 'address' => $address, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $address;
    }

    /** ZIP of every photo in the dump, in one folder per audit. Null when there are none. */
    private function writeZip(array $table, array $links, string $outDir): ?string
    {
        $zipPath = $outDir . '/Godrej Asset Verification Photos.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $added = 0;

        foreach ($table as $n => $row) {
            $folder = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', trim(($row['Outlet Bizom ID'] ?: 'audit') . ' - ' . ($row['Bizom outlet Name'] ?? '')));
            foreach ($links[$n] as $header => $url) {
                if (!isset(self::PHOTO_NAMES[$header])) {
                    continue;
                }
                $relative = rawurldecode(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
                $files = str_contains($relative, 'report/download-images-zip/')
                    ? $this->multiImageFiles(basename($relative))
                    : [$relative];

                foreach (array_values($files) as $i => $rel) {
                    if (str_contains($rel, '..')) {
                        continue;
                    }
                    $rel  = is_file(public_path($rel)) ? $rel : (\App\Services\GeoStamp::stampedSibling($rel) ?? $rel);
                    $file = public_path($rel);
                    if (!is_file($file)) {
                        continue;
                    }
                    $suffix = count($files) > 1 ? ' ' . ($i + 1) : '';
                    $zip->addFile($file, $folder . '/' . self::PHOTO_NAMES[$header] . $suffix . '.' . (pathinfo($file, PATHINFO_EXTENSION) ?: 'jpg'));
                    $added++;
                }
            }
        }

        $zip->close();
        if (!$added) {
            @unlink($zipPath);
            return null;
        }

        return $zipPath;
    }

    /**
     * Geo-stamping replaces an image with "geo_<name>" and deletes the original, so a dump built
     * before the answer was updated can link to a file that no longer exists. Point at the stamped copy.
     */
    private function liveUrl(string $url): string
    {
        $path = rawurldecode(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
        if ($path === '' || str_contains($path, '..') || str_contains($path, 'report/download-images-zip/') || is_file(public_path($path))) {
            return $url;
        }
        $stamped = \App\Services\GeoStamp::stampedSibling($path);

        return $stamped ? url(implode('/', array_map('rawurlencode', explode('/', $stamped)))) : $url;
    }

    /** Image paths (relative to public/) behind a multi-image answer's download-images-zip link. */
    private function multiImageFiles(string $token): array
    {
        $answerId = \App\Helpers\EncryptHelper::decrypt($token);
        $answer = is_numeric($answerId) ? \App\Models\TempUserActivityAnswersData::find($answerId) : null;
        $paths = json_decode($answer->user_answer ?? '', true);

        return is_array($paths) ? array_map(fn ($p) => ltrim((string) $p, '/'), $paths) : [];
    }

    private function update(array $fields): void
    {
        DB::table('custom_report_runs')->where('id', $this->runId)->update($fields + ['updated_at' => now()]);
    }
}
