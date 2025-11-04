<?php

namespace App\Imports;

use App\Models\ProjectTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProjectDataImport2
{
    /**
     * @param Collection $collection
     */
    protected $filePath;
    protected $projectId;
    protected $templateId;
    protected $dataFilter;

    public function __construct($filePath, $projectId, $templateId, $dataFilter=0)
    {
        $this->filePath = $filePath;
        $this->projectId = $projectId;
        $this->templateId = $templateId;
        $this->dataFilter = $dataFilter;
    }

    public function handle()
    {
        if (!file_exists($this->filePath)) {
            return ['success' => false, 'message' => "File not found at: {$this->filePath}"];
        }

        Log::info('import started');
        $startTime = microtime(true);
        $now = now()->format('Y-m-d H:i:s');
        $tempCsvPath = storage_path("app/temp_upload_" . time() . ".csv");

        $inHandle = fopen($this->filePath, 'r');
        $outHandle = fopen($tempCsvPath, 'w');

        if ($inHandle === false || $outHandle === false) {
            return ['success' => false, 'message' => 'File open failed'];
        }

        $projectInfo = DB::table('projects')->find($this->projectId);
        if (!$projectInfo) {
            return ['success' => false, 'message' => 'Project not found'];
        }

        $templateInfo = DB::table('template_names')->find($this->templateId);
        if (!$templateInfo) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        $projectTemplateInfo = DB::table('project_templates')
            ->where('project_id', $projectInfo->id)
            ->where('template_name_id', $templateInfo->id)
            ->first();

        if (!$projectTemplateInfo) {
            return ['success' => false, 'message' => 'Project Template not found'];
        }

        $projectTemplateId = $projectTemplateInfo->id;

        $templateHeads = DB::table('template_name_heads')
            ->where('template_name_id', $templateInfo->id)
            ->orderBy('id')
            ->select('id', 'template_head_name')
            ->get();

        $expectedHeaders = $templateHeads->pluck('template_head_name')->toArray();
        $templateHeadIds = $templateHeads->pluck('id')->toArray();

        $headers = fgetcsv($inHandle);
        // Clean BOM + trim spaces from each header
        $headers = array_map(function ($h) {
            return trim(preg_replace('/\x{FEFF}/u', '', $h));
        }, $headers);

        $headers = array_values(array_filter($headers, fn($h) => trim($h) !== ''));

        if ($expectedHeaders !== $headers) {
            fclose($inHandle);
            fclose($outHandle);
            return ['success' => false, 'message' => 'CSV headers do not match expected template'];
        }

        // for distributor mapping on Outlet Data
        $masterData = ProjectTemplate::where('project_id', $this->projectId)->where('is_master', 1)->first();
        $masterTemplateValuesData = [];
        if ($projectTemplateInfo->is_master == 0 && $this->dataFilter == 1 && $masterData) {
            $masterHeadId = $projectTemplateInfo->master_head_id;
            $masterTemplateValuesData = DB::table('project_template_name_values_new')
                ->where('project_template_id', $masterData->id)
                ->select('id', 'template_data_json')
                ->get()
                ->map(function($row) use ($masterHeadId) {
                    $json = json_decode($row->template_data_json, true);
                    return [
                        'id'    => $row->id,  // keep the row id
                        'value' => $json[$masterHeadId] ?? null // keep only matched key value
                    ];
                });
        }

        DB::connection()->disableQueryLog();
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            while (($data = fgetcsv($inHandle)) !== false) {
                $templateJson = [];

                foreach ($data as $i => $value) {
                    if (!isset($templateHeadIds[$i])) continue;

                    $value = preg_replace('/[\/\\\\\'"]/', '', $value);
//                    Log::info('Raw value: [' . $value . '] ASCII: ' . bin2hex($value));
                    $value = $this->sanitizeCsvValue($value);

                    $templateJson[$templateHeadIds[$i]] = $value !== '' ? $value : '';
                }

                // 🔎 Match against master values first for Mapping Distributor with Outlet
                $matchedMasterId = null;
                if ($this->dataFilter == 1 && !empty($masterTemplateValuesData)) {
                    foreach ($templateJson as $csvValue) {
                        $found = collect($masterTemplateValuesData)->firstWhere('value', $csvValue);
                        if ($found) {
                            $matchedMasterId = $found['id'];
                            break;
                        }
                    }
                }

                $json = json_encode(
                    $templateJson,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );

                // extra clean after encoding
//                $json = preg_replace('/[\/\\\\\'"∕／]/u', '', $json);

                // Step 5: Validate JSON before writing
                if (
                    $json === false ||
                    !mb_check_encoding($json, 'UTF-8') ||
                    json_last_error() !== JSON_ERROR_NONE
                ) {
                    Log::warning("Skipping invalid JSON row: " . json_last_error_msg());
                    continue;
                }

                fputcsv($outHandle, [
                    $projectTemplateId,
                    $json,
                    $matchedMasterId,
                    $now,
                    $now,
                ], ',', '"', "\\");
            }

        } catch (\Exception $e) {
            Log::info('error import -'.$e->getMessage());
        } finally {
            fclose($inHandle);
            fclose($outHandle);
        }

        // Step 2: Import using LOAD DATA LOCAL INFILE
        // Step 2: Import using LOAD DATA LOCAL INFILE
        try {
            DB::statement('ALTER TABLE project_template_name_values_new DISABLE KEYS');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Normalize path for MySQL (Windows safe)
            $mysqlFilePath = str_replace('\\', '/', $tempCsvPath);

            DB::statement("
        LOAD DATA LOCAL INFILE '" . addslashes($mysqlFilePath) . "'
        INTO TABLE project_template_name_values_new
        CHARACTER SET utf8mb4
        FIELDS TERMINATED BY ','
        ENCLOSED BY '\"'
        LINES TERMINATED BY '\n'
        (project_template_id, template_data_json, distributor_id, created_at, updated_at)
    ");
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'LOAD DATA INFILE failed: ' . $e->getMessage()];
        } finally {
            DB::statement('ALTER TABLE project_template_name_values_new ENABLE KEYS');
            // unlink($tempCsvPath);
        }

        $duration = microtime(true) - $startTime;
        return [
            'success' => true,
            'message' => "Successfully uploaded using LOAD DATA INFILE in " . round($duration, 2) . " seconds"
        ];
    }

    private function sanitizeCsvValue($value) {
        // Convert encoding
        $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }

        // Remove BOM
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        // Normalize accents
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        // Replace newlines inside cells
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        // Remove /, \, quotes
        $value = preg_replace('/[\/\\\\\'"∕／]/u', '', $value);

        // Remove control chars
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($value);
    }


    private function sanitizeCsvValueOld($value) {
        // 1. Convert to UTF-8
        $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }

        // 2. Remove BOM
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        // 3. Normalize accents (Café → Cafe)
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        // 4. Remove /, \, single and double quotes
        $value = preg_replace('/[\/\\\\\'"]/', '', $value);

        // 5. Remove control characters except \n, \r, \t
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        // 6. Trim extra spaces
        $value = trim($value);

        return $value;
    }

}
