<?php

namespace App\Models;

use App\Traits\HasEncryptedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataAssign extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['company_id', 'zone_id', 'unit_id', 'project_id', 'activity_id', 'template_name_id', 'activity_group_id', 'project_template_id', 'template_name_head_id', 'is_outlet_assigned', 'status', 'company_user_id', 'agency_id'];

    public function getProjectTemplate()
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }
    public function activityName()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function templateName()
    {
        return $this->belongsTo(TemplateName::class, 'template_name_id');
    }

    public function TemplateHeadName()
    {
        return $this->belongsTo(TemplateNameHead::class, 'template_name_head_id');
    }

//    public function getAutiors()
//    {
//        return $this->hasMany(AuditorAssignedData::class, 'data_assign_id')->distinct()->count('user_id');
//    }
//
    public function getAutiors()
    {
        return $this->hasMany(UserActivityDataAssign::class, 'data_assign_id')->distinct()->count('user_id');
    }

    public function getAuditorIds()
    {
        return $this->hasMany(AuditorAssignedData::class, 'data_assign_id');
    }

    public function getActivityGroup()
    {
        return $this->belongsTo(ActivityGroup::class, 'activity_group_id');
    }

    public function getProjectInfo()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // the below not used yet
    public function  question()
    {

        return $this->hasMany(Question::class, 'activity_id', 'activity_id');
    }
    public function getAuditorValue()
    {
        return $this->hasOne(AuditorAssignedData::class, 'data_assign_id', 'id');
    }
}
