<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivityDataAssign extends Model
{
    use HasFactory;

    protected $table = 'user_activity_data_assigns';

    protected $fillable = ['data_assign_id', 'user_id', 'project_template_id', 'activity_id', 'activity_sequence_id', 'common_id'];

    public function dataAssign(){
        return $this->belongsTo(DataAssign::class, 'data_assign_id');
    }

    public function projectTemplateData(){
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function activityInfo(){
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function get_user_info(){
        return $this->belongsTo(User::class, 'user_id');
    }


}
