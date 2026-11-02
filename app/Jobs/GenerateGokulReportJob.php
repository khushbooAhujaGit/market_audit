<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds the Gokul Distributor Fitment workbook + photo ZIP for one distributor row.
 *
 * Run with dispatchAfterResponse() rather than through the queue: the worker here is only
 * (re)started by cron every 30 minutes, which is too slow for a page the user is watching.
 * One distributor is a small job (a few dozen answers and photos), so running it in the web
 * process right after the response is fine.
 */
class GenerateGokulReportJob
{
    use Dispatchable;

    /** activity-name keyword => payload key; the Gokul activity group names its activities "GKL <Name>". */
    private const ACTIVITY_KEYS = [
        'basic'     => 'basic',
        'vehicle'   => 'vehicle',
        'warehouse' => 'warehouse',
        'stock'     => 'stock',
        'process'   => 'process',
        'market'    => 'market',
    ];

    public function __construct(private int $runId)
    {
    }

    public function handle(): void
    {
        set_time_limit(0);

        $run = DB::table('custom_report_runs')->find($this->runId);
        if (!$run) {
            return;
        }

        $this->update(['status' => 'running', 'message' => 'Collecting audit answers']);

        try {
            $payload = $this->buildPayload($run);

            $outDir = storage_path('app/gokul-reports/' . $run->id);
            File::ensureDirectoryExists($outDir);
            $payload['out_dir']     = $outDir;
            $payload['public_root'] = public_path();

            $payloadPath = $outDir . '/payload.json';
            file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

            $this->update(['message' => 'Building the workbook and photo folders']);

            $process = new Process([
                config('services.infiltration_tool.python_bin', 'python3'),
                base_path('python-tools/gokul/gokul_report.py'),
                $payloadPath,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Report generator exited with an error.');
            }

            $result = json_decode(trim($process->getOutput()), true) ?: [];
            @unlink($payloadPath);

            $this->update([
                'status'    => 'done',
                'message'   => ($result['photos'] ?? 0) . ' photo(s) packaged',
                'xlsx_path' => $result['xlsx'] ?? null,
                'zip_path'  => $result['zip'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gokul report failed', ['run' => $this->runId, 'error' => $e->getMessage()]);
            $this->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 250)]);
        }
    }

    private function update(array $values): void
    {
        DB::table('custom_report_runs')->where('id', $this->runId)->update($values + ['updated_at' => now()]);
    }

    private function buildPayload(object $run): array
    {
        $template = DB::table('project_templates')->find($run->project_template_id);
        if (!$template || (int) $template->activityType !== 1) {
            throw new \RuntimeException('The selected template has no activity group configured.');
        }

        $activities = DB::table('activity_group_pivots as p')
            ->join('activities as a', 'a.id', '=', 'p.activity_id')
            ->where('p.activity_group_id', $template->activity_group_name_id_or_activity_id)
            ->orderBy('p.sequence')
            ->get(['a.id', 'a.activity_name']);

        $activityIdByKey = [];
        foreach ($activities as $activity) {
            foreach (self::ACTIVITY_KEYS as $needle => $key) {
                if (!isset($activityIdByKey[$key]) && stripos($activity->activity_name, $needle) !== false) {
                    $activityIdByKey[$key] = $activity->id;
                }
            }
        }

        $answers = $this->loadAnswers($run->row_id, array_values($activityIdByKey));

        $payload = [];
        foreach ($activityIdByKey as $key => $activityId) {
            $questions = DB::table('questions')->where('activity_id', $activityId)
                ->whereNull('deleted_at')->orderBy('question_sequence')->get()->keyBy('id');
            $instances = $answers[$activityId] ?? [];
            ksort($instances);

            $payload[$key] = match ($key) {
                'basic'  => $this->textAnswers($questions, reset($instances) ?: []),
                'vehicle' => array_values(array_filter(array_map(
                    fn ($instance) => $this->textAnswers($questions, $instance), $instances
                ))),
                'market' => $this->outlets($questions, $instances),
                default  => $this->particulars($questions, reset($instances) ?: []),
            };
        }

        $payload['identity'] = $this->identity($run, $template, array_values($activityIdByKey));

        return $payload;
    }

    /**
     * "Audit Identification" block of Basic Info: master data of the distributor, plus the date of the
     * latest submission and every auditor (users with an Auditor role) who submitted for it.
     */
    private function identity(object $run, object $template, array $activityIds): array
    {
        $heads  = DB::table('template_name_heads')->where('template_name_id', $template->template_name_id)
            ->whereNull('deleted_at')->pluck('id', 'template_head_name');
        $master = json_decode((string) DB::table('project_template_name_values_new')
            ->where('id', $run->row_id)->value('template_data_json'), true) ?: [];
        $value  = fn (string $head) => trim((string) ($master[$heads[$head] ?? 0] ?? ''));

        $submitted = DB::table('temp_user_activity_answers_data')
            ->where('row_id', $run->row_id)->whereIn('activity_id', $activityIds)
            ->whereNull('deleted_at')->where('is_draft', 0);
        $latest    = (clone $submitted)->max('updated_at');
        $userIds   = (clone $submitted)->distinct()->pluck('user_id');

        $auditorIds = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('mr.model_type', 'App\\Models\\User')->whereIn('mr.model_id', $userIds)
            ->where('r.name', 'like', 'Auditor%')->pluck('mr.model_id');
        $names = DB::table('users')->whereIn('id', $auditorIds->isNotEmpty() ? $auditorIds : $userIds)
            ->orderBy('name')->pluck('name')->all();

        return [
            'CD Code'                                => $value('DB Code'),
            'Name of Distributor'                    => $value('DB Name'),
            'Firm / Legal Entity Name'               => $value('DB Name'),
            'Zone / Region'                          => $value('Zone/Region'),
            'State'                                  => $value('State'),
            'Reporting ASE / ASM'                    => $value('SO/ASE Name'),
            'Audit Date'                             => $latest ? date('d.m.Y', strtotime($latest)) : '',
            'Audit Type (Surprise / Pre-announced)'  => 'Pre-announced',
            'Audit Planning Cycle (e.g. Q1 FY27)'    => 'Q3 FY 26-27',
            'Name of Audit Team Member'              => implode(', ', $names),
        ];
    }

    /** @return array<int, array<int, array<int, string>>> activity => sequence => question id => answer */
    private function loadAnswers(int $rowId, array $activityIds): array
    {
        $rows = DB::table('temp_user_activity_answers_data')
            ->where('row_id', $rowId)
            ->whereIn('activity_id', $activityIds)
            ->whereNull('deleted_at')
            ->where('is_draft', 0)
            ->orderBy('id')
            ->get(['activity_id', 'activity_sequence', 'question_id', 'user_answer']);

        $out = [];
        foreach ($rows as $row) {
            if ($row->user_answer === null || trim($row->user_answer) === '') {
                continue;
            }
            // ordered by id, so a later (re-saved) answer overwrites an earlier one
            $out[$row->activity_id][(int) $row->activity_sequence][$row->question_id] = $row->user_answer;
        }

        return $out;
    }

    /** Question text => answer, for the non-image questions of one activity instance. */
    private function textAnswers($questions, array $instance): array
    {
        $out = [];
        foreach ($questions as $q) {
            if ($q->question_type === 'Image' || !isset($instance[$q->id])) {
                continue;
            }
            $out[trim($q->question)] = $instance[$q->id];
        }

        return $out;
    }

    private function outlets($questions, array $instances): array
    {
        $outlets = [];
        foreach ($instances as $instance) {
            $photos = [];
            foreach ($questions as $q) {
                if ($q->question_type === 'Image' && isset($instance[$q->id])) {
                    $photos[trim($q->question)] = $this->splitPaths($instance[$q->id]);
                }
            }
            $answers = $this->textAnswers($questions, $instance);
            if ($answers || $photos) {
                $outlets[] = ['answers' => $answers, 'photos' => $photos];
            }
        }

        return $outlets;
    }

    /** Warehouse / Stock / Process & Finance: one entry per parent question ("particular"). */
    private function particulars($questions, array $instance): array
    {
        $links = DB::table('question_sub_questions')
            ->whereIn('parent_question_id', $questions->keys())
            ->orderBy('sequence')->get()->groupBy('parent_question_id');

        $items = [];
        foreach ($questions->whereIn('id', $links->keys())->sortBy('question_sequence') as $parent) {
            $item = ['name' => trim($parent->question), 'value' => null, 'photos' => [], 'photo_label' => 'Photo',
                     'adequate' => null, 'remark' => null];

            foreach ($links[$parent->id] as $link) {
                $child = $questions->get($link->child_question_id);
                if (!$child) {
                    continue;
                }
                $answer = $instance[$child->id] ?? null;
                $text   = strtolower($child->question);

                if ($child->question_type === 'Image') {
                    $item['photo_label'] = trim($child->question);
                    $item['photos']      = $answer ? $this->splitPaths($answer) : [];
                } elseif (str_contains($text, 'remark')) {
                    $item['remark'] = $answer;
                } elseif (str_contains($text, 'adequate')) {
                    $item['adequate'] = $answer;
                } elseif ($item['value'] === null) {
                    $item['value'] = $answer;
                }
            }
            $items[] = $item;
        }

        return $items;
    }

    private function splitPaths(string $answer): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/,(?=\s*activityAnswerImages\/)/', $answer))));
    }
}
