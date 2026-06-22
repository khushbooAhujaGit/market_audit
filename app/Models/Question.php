<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Question extends Model
{
    use HasFactory, HasEncryptedId;

    protected $fillable = [
        'activity_id',
        'question',
        'question_type',
        'answer_type',
        'question_sequence',
        'is_parent',
        'parent_question_id',
        'parent_value',
        'parent_dropdown_id',
        'allow_multiple_images',
    ];


    public function scopeActive($query)
    {

        return $query->where('deleted_at', null);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function getOptions()
    {
        return $this->hasMany(QuestionDropdown::class, 'question_id', 'id');
    }

    public function getSubjects()
    {
        return $this->hasMany(SubjectQuestion::class, 'question_id');
    }

    public function subQuestions()
    {
        return $this->hasMany(QuestionSubQuestion::class, 'parent_question_id')->orderBy('sequence');
    }

    // Set up the deleting event to handle related records
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($question) {
            // Delete related SubjectQuestion records
            foreach ($question->getSubjects as $subjectQuestion) {
                $subjectQuestion->delete();
            }

            // Delete related QuestionDropdown records
            foreach ($question->getOptions as $option) {
                $option->delete();
            }
        });
    }
}
