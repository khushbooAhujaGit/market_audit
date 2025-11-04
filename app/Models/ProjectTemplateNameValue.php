<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ProjectTemplateNameValue extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['row_id','project_template_id','template_name_head_id', 'value', 'company_user_id', 'verified_by_company',
        'action_taken_type', 'complianceDocument', 'action_remark', 'compliance_document_number', 'action_taken_amount'];

    public function getHeadName(){
        return $this->belongsTo(TemplateNameHead::class, 'template_name_head_id');
    }

    public function getDataOfRows(){
        return $this->hasMany(ProjectTemplateNameValue::class, 'row_id', 'row_id')->orderBy('id');
    }

    public function getProjectTemplate(){
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

}
