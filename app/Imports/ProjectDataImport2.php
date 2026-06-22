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

    public function __construct($filePath, $projectId, $templateId, $dataFilter = 0)
    {
        $this->filePath    = $filePath;
        $this->projectId   = $projectId;
        $this->templateId  = $templateId;
        $this->dataFilter  = $dataFilter;
    }

    public function handle()
    {
        if (!file_exists($this->filePath)) {
            return ['success' => false, 'message' => "File not found at: {$this->filePath}"];
        }

        Log::info('import started');
        $startTime = microtime(true);
        $now = now()->format('Y-m-d H:i:s');

        $inHandle = fopen($this->filePath, 'r');
        if ($inHandle === false) {
            return ['success' => false, 'message' => 'File open failed'];
        }

        $projectInfo = DB::table('projects')->find($this->projectId);
        if (!$projectInfo) {
            fclose($inHandle);
            return ['success' => false, 'message' => 'Project not found'];
        }

        $templateInfo = DB::table('template_names')->find($this->templateId);
        if (!$templateInfo) {
            fclose($inHandle);
            return ['success' => false, 'message' => 'Template not found'];
        }

        $projectTemplateInfo = DB::table('project_templates')
            ->where('project_id', $projectInfo->id)
            ->where('template_name_id', $templateInfo->id)
            ->first();

        if (!$projectTemplateInfo) {
            fclose($inHandle);
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

        // Read and clean CSV headers
        $headers = fgetcsv($inHandle);
        $headers = array_map(function ($h) {
            return trim(preg_replace('/\x{FEFF}/u', '', $h));
        }, $headers);
        $headers = array_values(array_filter($headers, function ($h) { return trim($h) !== ''; }));

        if ($expectedHeaders !== $headers) {
            fclose($inHandle);
            return ['success' => false, 'message' => 'CSV headers do not match expected template. Expected: [' . implode(', ', $expectedHeaders) . '] Got: [' . implode(', ', $headers) . ']'];
        }

        // For distributor mapping on Outlet Data
        $masterData = ProjectTemplate::where('project_id', $this->projectId)->where('is_master', 1)->first();
        $masterTemplateValuesData = [];
        if ($projectTemplateInfo->is_master == 0 && $this->dataFilter == 1 && $masterData) {
            $masterHeadId = $projectTemplateInfo->master_head_id;
            $masterTemplateValuesData = DB::table('project_template_name_values_new')
                ->where('project_template_id', $masterData->id)
                ->select('id', 'template_data_json')
                ->get()
                ->map(function ($row) use ($masterHeadId) {
                    $json = json_decode($row->template_data_json, true);
                    return [
                        'id'    => $row->id,
                        'value' => $json[$masterHeadId] ?? null,
                    ];
                });
        }

        DB::connection()->disableQueryLog();

        $rows          = [];
        $chunkSize     = 500;
        $totalInserted = 0;

        try {
            while (($data = fgetcsv($inHandle)) !== false) {
                // Skip completely empty rows
                $nonEmpty = array_filter($data, function ($v) { return trim($v) !== ''; });
                if (empty($nonEmpty)) {
                    continue;
                }

                $templateJson = [];
                foreach ($data as $i => $value) {
                    if (!isset($templateHeadIds[$i])) continue;
                    $value = $this->sanitizeCsvValue($value);
                    $templateJson[$templateHeadIds[$i]] = $value;
                }

                // Match against master values for Distributor -> Outlet mapping
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
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );

                if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Skipping row with bad JSON: ' . json_last_error_msg());
                    continue;
                }

                $rows[] = [
                    'project_template_id' => $projectTemplateId,
                    'template_data_json'  => $json,
                    'distributor_id'      => $matchedMasterId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                // Flush chunk to DB
                if (count($rows) >= $chunkSize) {
                    DB::table('project_template_name_values_new')->insert($rows);
                    $totalInserted += count($rows);
                    $rows = [];
                }
            }

            // Insert remaining rows
            if (!empty($rows)) {
                DB::table('project_template_name_values_new')->insert($rows);
                $totalInserted += count($rows);
            }

        } catch (\Exception $e) {
            fclose($inHandle);
            Log::error('import error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
        }

        fclose($inHandle);

        $duration = microtime(true) - $startTime;
        Log::info('import done - ' . $totalInserted . ' rows in ' . round($duration, 2) . 's');

        return [
            'success' => true,
            'message' => "Successfully uploaded {$totalInserted} rows in " . round($duration, 2) . " seconds",
        ];
    }

    private function sanitizeCsvValue($value)
    {
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

        // Remove slashes and quotes (prevent JSON/SQL issues)
        $value = preg_replace('/[\/\\\\\'"]/u', '', $value);

        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($value);
    }
}
