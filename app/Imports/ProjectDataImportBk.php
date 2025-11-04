<?php

namespace App\Imports;

use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\TemplateNameHead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProjectDataImportBk implements ToCollection, WithChunkReading, ShouldQueue
{
    /**
     * @param Collection $collection
     */
    private $projectId;
    private $templateId;
    private $projectTemplateInfo;
    private $headers;
    private $templateHeadsMap;
    private $row_id_counter;
    private $isHeaderChecked;

    public function __construct($projectId, $templateId)
    {
        $this->projectId = $projectId;
        $this->templateId = $templateId;
        $this->isHeaderChecked = 0;
        $this->row_id_counter = ProjectTemplateNameValue::max('row_id') + 1;
        $this->projectTemplateInfo = ProjectTemplate::with('getTemplate.getTemplateHeads')
            ->where('project_id', $this->projectId)
            ->where('template_name_id', $this->templateId)
            ->first();
        if (!$this->projectTemplateInfo) {
            return; // Exit gracefully if no project template found
        }
        $templateHeads = TemplateNameHead::where('template_name_id', $this->projectTemplateInfo->template_name_id)
            ->orderBy('id')
            ->get();
        $this->headers = $templateHeads->pluck('template_head_name')->toArray();
        $this->templateHeadsMap = $templateHeads->pluck('id', 'template_head_name')->toArray();
    }

    public function collection(Collection $collection)
    {
            \Log::error(" is : {$this->isHeaderChecked}");
        if ($this->isHeaderChecked === 0) {
            $firstRow = $collection->first();
            $excelHeaders = $firstRow->toArray();
            // Trim and normalize headers for comparison
            $trimmedHeaders = collect($this->headers)->map(fn($item) => trim(strtolower($item)))->all();
            $trimmedExcelHeaders = collect($excelHeaders)->map(fn($item) => trim(strtolower($item)))->all();
            // Check if the trimmed arrays match
//            \Log::error('header', json_encode($trimmedHeaders));
//            \Log::error('excel', json_encode($trimmedExcelHeaders));
            if (!empty(array_diff($trimmedHeaders, $trimmedExcelHeaders)) || !empty(array_diff($trimmedExcelHeaders, $trimmedHeaders))) {
                \Log::error('Excel headers do not match template headers');
                return; // Exit gracefully or throw an exception
            }
            // Mark header check as done
           $this->isHeaderChecked = 1;
//            \Log::warning("Missing template head for header:".json_encode($collection));
        }
//        if (self::$isHeaderChecked == 1) {
//            $this->processChunk($collection, $this->projectTemplateInfo, 1);
//        } else {
//        }
        // Initialize headers and template head mappings during the first chunk
            $this->processChunk($collection, $this->projectTemplateInfo);
    }

    private function processChunk(Collection $collection, $projectTemplateInfo)
    {
        $insertProjectTemplateData = [];
        foreach ($collection as $row) {

            $template_data = $row->toArray();
            if (empty(array_filter($template_data, fn($value) => !is_null($value) && $value !== ''))) {
                continue;
            }
            $template_data = array_slice($template_data, 0, count($this->headers));
            $template_data = array_map('strval', $template_data);
            foreach ($template_data as $index => $value) {
                $header = $this->headers[$index] ?? null;
                if (!$header || !isset($this->templateHeadsMap[$header])) {
                    continue;
                }
                $insertProjectTemplateData[] = [
                    'row_id' => $this->row_id_counter,
                    'project_template_id' => $projectTemplateInfo->id,
                    'template_name_head_id' => $this->templateHeadsMap[$header],
                    'value' => $value,
                ];
            }
            $this->row_id_counter++;
            if (count($insertProjectTemplateData) >= 1000) {
                ProjectTemplateNameValue::insert($insertProjectTemplateData);
                $insertProjectTemplateData = [];
            }
        }
        if (!empty($insertProjectTemplateData)) {
            ProjectTemplateNameValue::insert($insertProjectTemplateData);
        }
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
