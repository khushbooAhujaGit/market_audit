<?php

namespace App\Jobs;

use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\TemplateNameHead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class UploadProjectDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $requestData;
    protected $fileName;
    public function __construct(array $requestData, string $fileName)
    {
        $this->requestData = $requestData;
        $this->fileName = $fileName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $projectTemplateInfo = ProjectTemplate::where('project_id', $this->requestData['project_id'])
            ->where('template_name_id', $this->requestData['template_name_id'])->first(); // this will return us the project template id
        $template_heads_count = count($projectTemplateInfo->getTemplate->getTemplateHeads);

        if($projectTemplateInfo){
            $filePath = public_path('assets/project_data_excels/'.$this->fileName);
            if(file_exists($filePath)){
                $data = Excel::toCollection(null, $filePath)->first();
                $dataArray = $data->toArray();
                $headers_data = array_shift($dataArray);
                $headers = array_slice($headers_data, 0, $template_heads_count);
                $row_id_counter = ProjectTemplateNameValue::max('row_id') + 1;
                foreach ($dataArray as $index => $template_data) {
                    // Check if all values in the row are empty, ' ', or null
                    if (empty(array_filter($template_data, fn($value) => !is_null($value) && $value !== ''))) {
                        continue; // Skip empty row
                    }
                    $template_data = array_slice($template_data, 0, $template_heads_count);
                    $template_data = array_map('strval', $template_data);
                    foreach ($template_data as $inside => $data) {
                        $length = count($template_data);
                        $let_in = 1;
                        if ($let_in == 1) {
                            $template_headInfo = TemplateNameHead::where('template_name_id', $projectTemplateInfo->template_name_id)->where('template_head_name', $headers[$inside])->first();
                            ProjectTemplateNameValue::create([
                                'row_id' => $row_id_counter,
                                'project_template_id' => $projectTemplateInfo->id,
                                'template_name_head_id' => $template_headInfo->id,
                                'value' => $data
                            ]);
                        }
                    }
                    $row_id_counter += 1;
                }

            }

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

    }
}
