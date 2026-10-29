<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time fix for the verifier queue, which now lists only submitted audits (status 1).
 *
 * Before that change the web form saved submitted audits with status 0 (same as drafts),
 * so older web submissions would drop out of the queue. This marks every audit
 * (row + activity + instance) that has only status 0 answers and every required question
 * answered as submitted. Audits with a required answer missing stay drafts, so they stay
 * out of the queue until the auditor completes and submits them.
 *
 *   php artisan answers:mark-legacy-submitted --dry-run     (only report)
 *   php artisan answers:mark-legacy-submitted                (apply)
 *   php artisan answers:mark-legacy-submitted --before=2026-10-14
 */
class MarkLegacySubmittedAnswers extends Command
{
    protected $signature = 'answers:mark-legacy-submitted
        {--dry-run : Only report what would change}
        {--before= : Only audits last saved before this date (Y-m-d)}';

    protected $description = 'Mark complete audits that have only status 0 answers (older web submissions) as submitted (status 1)';

    public function handle(): int
    {
        $before = $this->option('before');
        $dryRun = (bool) $this->option('dry-run');

        // One pass over the answers (the table has no row_id index, so per-audit lookups would
        // scan it thousands of times): group them per audit, keep audits with only status 0.
        $audits = [];
        $lastAt = [];
        $nonDraft = [];
        DB::table('temp_user_activity_answers_data')
            ->whereNull('deleted_at')
            ->select('id', 'row_id', 'activity_id', 'activity_sequence', 'question_id', 'user_answer', 'status', 'updated_at')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$audits, &$lastAt, &$nonDraft) {
                foreach ($rows as $a) {
                    $key = $a->row_id . ':' . $a->activity_id . ':' . (int) $a->activity_sequence;
                    if ((int) $a->status !== 0) { $nonDraft[$key] = true; continue; }
                    $v = trim((string) $a->user_answer);
                    $audits[$key]['answers'] ??= [];
                    $audits[$key]['ids'][] = $a->id;
                    if ($v !== '') $audits[$key]['answers'][(int) $a->question_id] = $v;
                    $audits[$key]['row_id'] = $a->row_id;
                    $audits[$key]['activity_id'] = $a->activity_id;
                    $audits[$key]['seq'] = (int) $a->activity_sequence;
                    $lastAt[$key] = max($lastAt[$key] ?? '', (string) $a->updated_at);
                }
            });
        $audits = array_diff_key($audits, $nonDraft);
        if ($before) {
            $audits = array_filter($audits, fn($k) => $lastAt[$k] < $before . ' 00:00:00', ARRAY_FILTER_USE_KEY);
        }

        $this->info("Audits with only status 0 answers" . ($before ? " (last saved before {$before})" : '') . ': ' . count($audits));

        $questionsByActivity = [];
        $complete = $incomplete = $updatedAnswers = 0;

        foreach ($audits as $audit) {
            $p = (object) $audit;
            $questionsByActivity[$p->activity_id] ??= DB::table('questions')
                ->where('activity_id', $p->activity_id)
                ->where('answer_type', 1)
                ->where('question_type', '!=', 'Multi Response')
                ->whereNull('deleted_at')
                ->get(['id', 'parent_question_id', 'parent_value']);

            $answerOf = $p->answers;

            // Required = always-shown required questions, plus conditional ones whose trigger
            // was answered (same rule as the app API's required-question check).
            $missing = $questionsByActivity[$p->activity_id]->contains(function ($q) use ($answerOf) {
                if (!empty($q->parent_question_id)) {
                    $parent = $answerOf[(int) $q->parent_question_id] ?? '';
                    if ($parent === '') return false;
                    if ($q->parent_value !== '__non_empty__'
                        && ($q->parent_value === null || strcasecmp($parent, (string) $q->parent_value) !== 0)) return false;
                }
                return !isset($answerOf[(int) $q->id]);
            });

            if ($missing) {
                $incomplete++;
                continue;
            }
            $complete++;
            if (!$dryRun) {
                foreach (array_chunk($p->ids, 1000) as $ids) {
                    $updatedAnswers += DB::table('temp_user_activity_answers_data')
                        ->whereIn('id', $ids)
                        ->where('status', 0)
                        ->update(['status' => 1]);
                }
            }
        }

        $this->line("Complete (marked submitted): {$complete}");
        $this->line("Required answers missing (left as drafts, not shown to verifiers): {$incomplete}");
        $this->line($dryRun ? 'Dry run: nothing changed.' : "Answers updated to status 1: {$updatedAnswers}");

        return self::SUCCESS;
    }
}
