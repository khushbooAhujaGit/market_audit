<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class QuestionDropdown extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['question_id', 'option', 'subject_id' , 'subject_dropdown_id'];

    public function subjectDropdown() {
        return $this->belongsTo(SubjectDropdown::class, 'subject_dropdown_id', 'id');
    }
}
