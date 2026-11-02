<?php

namespace App\Services;

use App\Models\Question;
use App\Models\TempUserActivityAnswersData;
use Illuminate\Support\Facades\DB;

/**
 * Which conditional questions of an activity are shown for a set of answers, at every level
 * (a hidden parent hides its whole branch). Shared by the web form and the app API so both
 * drop the same hidden answers on submit and demand the same required questions.
 *
 * A conditional question (questions.parent_question_id) is shown when its parent's answer
 * matches parent_value (case-insensitive), or is non-empty for "__non_empty__" / no value,
 * and the parent itself is shown. Answers are matched per question, ignoring the context.
 */
class ConditionalAnswers
{
    /**
     * Removes saved answers of conditional questions that are no longer shown. Only Yes / No and
     * Dropdown parents (or "any answer") are judged; anything else is left as it is.
     */
    public static function removeHidden(int $rowId, int $activityId, int $activitySequence): void
    {
        $answers = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $activityId)
            ->where(self::sequenceFilter($activitySequence))
            ->orderBy('id')
            ->get(['question_id', 'user_answer']);

        $hiddenIds = self::hiddenIds($activityId, $answers, false);
        if ($hiddenIds) {
            TempUserActivityAnswersData::where('row_id', $rowId)
                ->where('activity_id', $activityId)
                ->where(self::sequenceFilter($activitySequence))
                ->whereIn('question_id', $hiddenIds)
                ->delete();
        }
    }

    /**
     * Required (answer_type = 1) questions an instance must answer for these answers: root
     * questions, Multi Response sub-questions at every level, and conditional questions only
     * while they are shown. Multi Response groups themselves have no answer.
     *
     * @param iterable $answers models/rows with question_id + user_answer (latest last)
     */
    public static function requiredIds(int $activityId, iterable $answers): array
    {
        $questions = Question::where('activity_id', $activityId)
            ->get(['id', 'is_parent', 'parent_question_id', 'question_type', 'answer_type']);
        $linkedIds = DB::table('question_sub_questions')
            ->whereIn('parent_question_id', $questions->pluck('id'))
            ->pluck('child_question_id')->map(fn($id) => (int) $id)->flip();
        $hidden = array_flip(self::hiddenIds($activityId, $answers, true));

        return $questions->filter(function ($q) use ($linkedIds, $hidden) {
            if ((int) $q->answer_type !== 1 || $q->question_type === 'Multi Response') return false;
            if (isset($hidden[$q->id])) return false;
            // Questions placed nowhere (no parent, no group, not a root) are never shown
            return $q->is_parent == 1 || $q->parent_question_id || isset($linkedIds[(int) $q->id]);
        })->pluck('id')->map(fn($id) => (int) $id)->values()->all();
    }

    /**
     * Conditional question IDs not shown for these answers. $strict = false keeps a question
     * whose trigger can't be judged (a specific value on a parent that isn't Yes / No or
     * Dropdown), so cleanup never deletes what it doesn't understand; $strict = true treats it
     * as shown only when the parent's answer equals the value (used for required checks).
     */
    public static function hiddenIds(int $activityId, iterable $answers, bool $strict): array
    {
        $conditional = Question::where('activity_id', $activityId)
            ->whereNotNull('parent_question_id')
            ->get(['id', 'parent_question_id', 'parent_value'])
            ->keyBy('id');
        if ($conditional->isEmpty()) return [];

        $parentTypes = Question::whereIn('id', $conditional->pluck('parent_question_id')->unique())
            ->pluck('question_type', 'id');
        // A question linked into the Multi Response it is "conditional" on is a plain member of
        // that group (it has no answer to match): always shown
        $groupMembers = DB::table('question_sub_questions')
            ->whereIn('child_question_id', $conditional->keys())
            ->get(['parent_question_id', 'child_question_id'])
            ->mapWithKeys(fn($l) => [$l->child_question_id . '|' . $l->parent_question_id => true]);

        $answerOf = [];
        foreach ($answers as $a) {
            $answerOf[(int) $a->question_id] = trim((string) $a->user_answer); // latest per question
        }

        $shown = [];
        $isShown = function ($questionId) use (&$isShown, &$shown, $conditional, $parentTypes, $groupMembers, $answerOf, $strict) {
            if (array_key_exists($questionId, $shown)) return $shown[$questionId];
            $question = $conditional[$questionId] ?? null;
            if (!$question) return $shown[$questionId] = true;    // not conditional: always shown
            $shown[$questionId] = true;                             // guards a parent loop

            $parentId   = (int) $question->parent_question_id;
            $parentType = $parentTypes[$parentId] ?? null;
            if ($parentType === 'Multi Response' && isset($groupMembers[$questionId . '|' . $parentId])) {
                return $shown[$questionId] = $isShown($parentId);
            }

            $trigger      = trim((string) $question->parent_value);
            $parentAnswer = $answerOf[$parentId] ?? '';
            $anyAnswer    = $trigger === '' || $trigger === '__non_empty__';
            if (!$anyAnswer && !$strict && !in_array($parentType, ['Yes / No', 'Dropdown'], true)) {
                return $shown[$questionId] = true;                  // can't judge: keep
            }
            $matches = $anyAnswer ? $parentAnswer !== '' : strcasecmp($parentAnswer, $trigger) === 0;

            return $shown[$questionId] = $matches && $isShown($parentId);
        };

        return $conditional->keys()->reject(fn($id) => $isShown($id))->map(fn($id) => (int) $id)->values()->all();
    }

    /** 0 (original submission) also matches the NULL activity_sequence of older rows. */
    private static function sequenceFilter(int $activitySequence): \Closure
    {
        return function ($q) use ($activitySequence) {
            if ($activitySequence > 0) {
                $q->where('activity_sequence', $activitySequence);
            } else {
                $q->where(fn($q2) => $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0));
            }
        };
    }
}
