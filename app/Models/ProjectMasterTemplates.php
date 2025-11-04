<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ProjectMasterTemplates extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_template_id', 'row_id', 'completion_type', 'min_completion', 'user_id'];
}
