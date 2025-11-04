<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class SubjectDropdown extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['subject_id','option'];

    public function SubjectiveQuestion(){
        return $this->belongsTo(SubjectQuestion::class,'subject_id','id');
    }

    public function subjectiveQuestionDropdowns() {
        return $this->hasMany(QuestionDropdown::class, 'subject_dropdown_id', 'id');
    }

}
