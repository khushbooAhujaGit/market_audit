<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionSubQuestion extends Model
{
    protected $fillable = [
        'parent_question_id',
        'child_question_id',
        'sequence',
    ];

    public function parentQuestion()
    {
        return $this->belongsTo(Question::class, 'parent_question_id');
    }

    public function childQuestion()
    {
        return $this->belongsTo(Question::class, 'child_question_id');
    }
}
