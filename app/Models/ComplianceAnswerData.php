<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ComplianceAnswerData extends Model
{
    use HasFactory, HasEncryptedId;
    protected $table = "compliance_answer_data";
    protected $fillable = ['user_id', 'activity_id', 'question_id', 'user_answer', 'compliance_threshold', 'compliance_remark', 'project_id', 'project_template_id', 'threshold_type', 'template_name_value'];

    public function projectData(){
        return $this->belongsTo(Project::class, 'project_id');
    }
    
    public function activityData(){
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function questionData(){
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function getUserData(){
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function templateData(){
        return $this->belongsTo(TemplateName::class, 'project_template_id');
    }
    
}
