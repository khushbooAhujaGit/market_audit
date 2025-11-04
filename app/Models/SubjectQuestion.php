<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class SubjectQuestion extends Model
{
    use HasFactory, HasEncryptedId;

    protected $fillable = ['question_id', 'subject', 'answer_type'];
    public function questionInfo(){
        return $this->belongsTo(Question::class,'question_id');
    }

    public function getOptions(){
        return $this->hasMany(SubjectDropdown::class, 'subject_id');
    }

    // Set up the deleting event to handle related records
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($subjectQuestion) {
            // Delete related SubjectDropdown records
            foreach ($subjectQuestion->getOptions as $option) {
                $option->delete();
            }
        });
    }


}
