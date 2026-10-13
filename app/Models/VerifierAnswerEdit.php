<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An answer changed by a verifier: keeps the auditor's original value. */
class VerifierAnswerEdit extends Model
{
    protected $fillable = [
        'answer_id', 'row_id', 'activity_id', 'activity_sequence', 'question_id',
        'old_answer', 'new_answer', 'edited_by',
    ];

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
