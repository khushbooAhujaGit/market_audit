<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class AuditorAssignedData extends Model
{
    use HasFactory, HasEncryptedId;
    
    protected $fillable = ['user_id', 'data_assign_id', 'project_id', 'template_name_id', 'template_name_head_value', 'audit_closed','otp_value'];

    public function dataAssign(){
        return $this->belongsTo(DataAssign::class, 'data_assign_id');
    }
    public function get_user_info(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function templatedata(){

        return $this->hasOne(TemplateName::class, 'id', 'template_name_id');
    }
    public function project(){

        return $this->hasOne(Project::class, 'id', 'project_id');
    }

}
