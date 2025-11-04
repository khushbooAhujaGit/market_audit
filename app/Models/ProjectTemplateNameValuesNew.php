<?php

namespace App\Models;

use App\Traits\HasEncryptedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTemplateNameValuesNew extends Model
{
    use HasFactory, HasEncryptedId;

    protected $table = 'project_template_name_values_new';

    protected $fillable = ['project_template_id', 'template_data_json', 'company_user_id', 'verified_by_company', 'distributor_id'];

    public function getProjectTemplateData(){
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function userActivityDataAssigns()
    {
        return $this->hasMany(UserActivityDataAssign::class, 'row_id', 'id');
    }


}
