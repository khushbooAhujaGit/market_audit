<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Verifier extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_template_name_id', 'user_id', 'status'];
    public function projectTemplateInfo(){
        return $this->belongsTo(ProjectTemplate::class, 'project_template_name_id');
    }
}
