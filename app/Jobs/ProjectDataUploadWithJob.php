<?php

namespace App\Jobs;

use App\Models\ProjectTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

//use Illuminate\Foundation\Bus\Dispatchable;

class ProjectDataUploadWithJob implements ShouldQueue
{
    use  InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    protected $filePath;
    protected $projectId;
    protected $templateId;
    protected $dataFilter;

    public function __construct($filePath, $projectId, $templateId, $user,  $dataFilter)
    {
        Log::info('Upload Data JOb Called');
        $this->filePath = $filePath;
        $this->projectId = $projectId;
        $this->templateId = $templateId;
        $this->dataFilter = $dataFilter;
    }

    public function handle()
    {
        Log::info('Upload Data JOb started');

        if (!file_exists($this->filePath)) {
            Log::error("Data Upload Job File not found at: {$this->filePath}");
            return;
        }

        $startTime = microtime(true);
        $now = now()->format('Y-m-d H:i:s');
        $tempCsvPath = storage_path("app/temp_upload_" . time() . ".csv");

        $inHandle = fopen($this->filePath, 'r');
        $outHandle = fopen($tempCsvPath, 'w');

        if ($inHandle === false) {
            Log::error("Failed to open file: {$this->filePath}");
            return;
        }

        $projectInfo = DB::table('projects')->find($this->projectId);
        if (!$projectInfo) {
            Log::error('Project not found');
            return;
        }

        $templateInfo = DB::table('template_names')->find($this->templateId);
        if (!$templateInfo) {
            Log::error('Template not found');
            return;
        }

        $projectTemplateInfo = DB::table('project_templates')
            ->where('project_id', $projectInfo->id)
            ->where('template_name_id', $templateInfo->id)
            ->first();

        if (!$projectTemplateInfo) {
            Log::error('Project Template not found');
            return;
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
        $headers = array_filter($headers, fn($h) => trim($h) !== '');
        $headers = array_values($headers);

        if ($expectedHeaders !== $headers) {
            fclose($inHandle);
            fclose($outHandle);
            Log::error("CSV headers do not match expected template");
            return;
        }

        // for distributor mapping on Outlet Data
        $masterData = ProjectTemplate::where('project_id', $this->projectId)->where('is_master', 1)->first();
//        dd( $this->dataFilter);
        $masterTemplateValuesData = [];
        if ($projectTemplateInfo->is_master == 0 && $this->dataFilter == 1 && !empty($masterData)) {
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

        $pdo = DB::connection()->getPdo();
        $pdo->beginTransaction();

        $insertBaseSql = "INSERT INTO project_template_name_values_new
                  (project_template_id, template_data_json, distributor_id, created_at, updated_at)";

        $chunkSize = 500;
        $batchInsertData = [];

        $now = now()->format('Y-m-d H:i:s');
//        $statement = $pdo->prepare($insertBaseSql);

        while (($data = fgetcsv($inHandle)) !== false) {
            $templateDataJson = [];

            foreach ($data as $i => $value) {
                if (!isset($templateHeadIds[$i])) continue;
                $value = preg_replace('/[\/\\\\\'"]/', '', $value);
                $value = $this->sanitizeCsvValue($value);
                $templateDataJson[$templateHeadIds[$i]] = $value;
            }

            // 🔎 Match against master values first for Mapping Distributor with Outlet
            $matchedMasterId = null;
            if ($this->dataFilter == 1 && !empty($masterTemplateValuesData)) {
                foreach ($templateDataJson as $csvValue) {
                    $found = collect($masterTemplateValuesData)->firstWhere('value', $csvValue);
                    if ($found) {
                        $matchedMasterId = $found['id'];
                        break;
                    }
                }
            }

            $batchInsertData[] = [
                $projectTemplateId,
                json_encode($templateDataJson),
                $matchedMasterId,
                $now,
                $now
            ];

            if (count($batchInsertData) >= $chunkSize) {
                $placeholders = $this->buildMultiInsertQuery($insertBaseSql, count($batchInsertData));
                $flatValues = array_merge(...array_map('array_values', $batchInsertData));
                $stmt = $pdo->prepare($placeholders);
                $stmt->execute($flatValues);
                $batchInsertData = [];
            }
        }

        if (!empty($batchInsertData)) {
            $placeholders = $this->buildMultiInsertQuery($insertBaseSql, count($batchInsertData));
            $flatValues = array_merge(...array_map('array_values', $batchInsertData));
            $stmt = $pdo->prepare($placeholders);
            $stmt->execute($flatValues);
        }

        fclose($inHandle);
        $pdo->commit();

        fclose($outHandle);

        unlink($tempCsvPath);

        $duration = microtime(true) - $startTime;
        Log::info("Project data imported successfully in " . round($duration, 2) . " seconds");
    }

    function buildMultiInsertQuery($baseSql, $rowCount)
    {
        return $baseSql . ' VALUES ' . implode(',', array_fill(0, $rowCount, '(?, ?, ?, ?, ?)'));
    }

    private function sanitizeCsvValue($value) {
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
