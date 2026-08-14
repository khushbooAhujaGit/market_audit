<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Project extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_name', 'project_type_id', 'company_id', 'zone_id', 'unit_id', 'with_data', 'is_application_applicable', 'recurring', 'data_add_on', 'activity_group_name_id_or_activity_id', 'activityType', 'is_agency_required', 'agency_id', 'is_otp_required', 'required_otp', 'isComplianceApplicable', 'isCompanyApplicable', 'companyVerificationRequired', 'is_otp_duplication_allowed', 'complianceRepeationStartDate', 'complianceRepeationEndDate', 'is_infiltration_report_applicable'];

    protected $casts = [
        'id'             => 'integer',
        'project_type_id'=> 'integer',
        'company_id'     => 'integer',
        'zone_id'        => 'integer',
        'unit_id'        => 'integer',
        'with_data'      => 'integer',
        'is_application_applicable' => 'integer',
        'recurring'      => 'integer',
        'data_add_on'    => 'integer',
        'is_agency_required' => 'integer',
        'agency_id'      => 'integer',
        'is_otp_required'=> 'integer',
        'is_infiltration_report_applicable' => 'integer',
    ];

    public function getUnit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function getZone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }


    public function getCompanyInfo()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getProjectType()
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function getProjectTemplates()
    {
        return $this->hasMany(ProjectTemplate::class, 'project_id');
    }

    public function common_heads()
    {
        return $this->hasMany(ProjectTemplatesCommonHeads::class, 'project_id');
    }

    //khushboo 04-05-2025
    public function dataAssigns()
    {
        return $this->hasMany(DataAssign::class, 'project_id');
    }
    //khushboo 04-05-2025

}
