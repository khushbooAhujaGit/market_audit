<?php

namespace App\Jobs;

use App\Models\ProjectTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FilterTemplateValuesData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected $projectId;

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Template Values Filter Data JOb started');

        $masterData = ProjectTemplate::where('project_id', $this->projectId)->where('is_master', 1)->first();
        $projectTemplateValueMasterDataExists = DB::table('project_template_name_values_new')
            ->where('project_template_id', $masterData->id)
            ->exists();
        $childTempalteData = ProjectTemplate::where('project_id', $this->projectId)->where('is_master', 0)->get();
        if ($projectTemplateValueMasterDataExists) {

            if (count($childTempalteData) > 0) {
                foreach ($childTempalteData as $childTempalte) {
                    $masterTemplateValuesData = [];
                    $masterHeadId = $childTempalte->master_head_id;
                    $masterTemplateValuesData = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $masterData->id)
                        ->select('id', 'template_data_json')
                        ->get()
                        ->map(function ($row) use ($masterHeadId) {
                            $json = json_decode($row->template_data_json, true);
                            return [
                                'id' => $row->id,  // keep the row id
                                'value' => $json[$masterHeadId] ?? null // keep only matched key value
                            ];
                        });

                    $ChildTemplateValuesData = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $childTempalte->id)->get();

                    foreach ($ChildTemplateValuesData as $childRow) {
                        $childJson = json_decode($childRow->template_data_json, true);

                        foreach ($masterTemplateValuesData as $masterRow) {
                            if (in_array($masterRow['value'], $childJson)) {
                                // ✅ Found match → update child row with distributor_id
                                DB::table('project_template_name_values_new')
                                    ->where('id', $childRow->id)
                                    ->update([
                                        'distributor_id' => $masterRow['id']
                                    ]);
                            }
                        }
                    }

                }
            }
        }

        Log::info('Template Values Filter Data Done');

    }


}
