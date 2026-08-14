<?php

namespace App\Jobs;

use App\Models\ProjectTemplate;
use Illuminate\Support\Facades\DB;
use App\Models\{ProjectTemplateNameValue};
use App\Models\TemplateNameHead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class UploadProjectTemplateDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $requestData;
    public $filePath;
    public int $tries   = 2;
    public int $timeout = 600; // retry_after in queue.php must be > 600 (set to 660)

    /**
     * Create a new job instance.
     */
    public function __construct($requestData, $filePath)
    {
        $this->requestData = $requestData;
        $this->filePath = $filePath; // Only the file path is passed
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            DB::beginTransaction();

            $file = Storage::path($this->filePath);
            $data = Excel::toCollection(null, $file)->first();
            $dataArray = $data->toArray();

            // Eager load TemplateNameHead model
            $projectTemplateInfo = ProjectTemplate::with('getTemplate.getTemplateHeads')
                ->where('project_id', $this->requestData['project_id'])
                ->where('template_name_id', $this->requestData['template_name_id'])->first();

            $template_heads_count = count($projectTemplateInfo->getTemplate->getTemplateHeads);
            $headers_data = array_shift($dataArray);
            $headers = array_slice($headers_data, 0, $template_heads_count);
            $row_id_counter = ProjectTemplateNameValue::max('row_id') + 1;
            $now = now()->toDateTimeString();
            // Process data in chunks of 1000 rows
            $chunks = collect($dataArray)->chunk(1000);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $template_data) {
                    if (empty(array_filter($template_data, fn($value) => !is_null($value) && $value !== ''))) {
                        continue; // Skip empty row
                    }
                    $template_data = array_slice($template_data, 0, $template_heads_count);
                    $template_data = array_map('strval', $template_data);
                    foreach ($template_data as $index => $value) {
                        $template_headInfo = TemplateNameHead::where('template_name_id', $projectTemplateInfo->template_name_id)
                            ->where('template_head_name', $headers[$index])->first();
                        $insertProjectTemplateData[] = [
                            'row_id' => $row_id_counter,
                            'project_template_id' => $projectTemplateInfo->id,
                            'template_name_head_id' => $template_headInfo->id,
                            'value' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    $row_id_counter++;
                    if (count($insertProjectTemplateData) >= 1000) {
                        ProjectTemplateNameValue::insert($insertProjectTemplateData);
                        $insertProjectTemplateData = [];
                    }
                }
                // Insert any remaining data
                if (!empty($insertProjectTemplateData)) {
                    ProjectTemplateNameValue::insert($insertProjectTemplateData);
                    $insertProjectTemplateData = [];
                }
            }

            DB::commit();
            Storage::delete($this->filePath);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in job: ' . $e->getMessage());
            throw $e; // Re-throw to mark the job as failed
        }
    }
}
