<?php

namespace App\Models;

use App\Traits\HasEncryptedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_id', 'template_name_id', 'is_master', 'master_head_id', 'own_reference_head_id' , 'activity_group_name_id_or_activity_id', 'activityType', 'main_header', 'sub_header',  'completion_type', 'min_completion', 'data_add_on', 'with_data', 'activity_otp_required_ids', 'required_otp', 'company_user_id', 'compliance_column_id', 'can_edit_data'];

    public function getProject(){
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function getTemplate(){
        return $this->belongsTo(TemplateName::class, 'template_name_id');
    }
    public function getProjectTemplateData(){
        return $this->hasMany(ProjectTemplateNameValuesNew::class, 'project_template_id')
            ->orderBy('id');
    }
    // Define the relationship with Activity model
    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_group_name_id_or_activity_id');
    }

    // Define the relationship with ActivityGroup model
    public function activityGroup()
    {
        return $this->belongsTo(ActivityGroup::class, 'activity_group_name_id_or_activity_id');
    }
    public function getRelatedActivityAttribute()
    {
        if ($this->activityType == 0) {
            return $this->activity;
        } else {
            return $this->activityGroup;
        }
    }

    public function projectTemplateValues()
    {
        return $this->hasMany(ProjectTemplateNameValuesNew::class, 'project_template_id', 'id');
    }

    public function getMainHeader(){
        return $this->belongsTo(TemplateNameHead::class, 'main_header', 'id');
    }
    public function getSubHeader(){
        return $this->belongsTo(TemplateNameHead::class, 'sub_header', 'id');
    }

}
