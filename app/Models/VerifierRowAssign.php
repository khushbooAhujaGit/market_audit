<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifierRowAssign extends Model
{
    protected $fillable = ['user_id', 'project_template_id', 'activity_id', 'row_id'];
}
