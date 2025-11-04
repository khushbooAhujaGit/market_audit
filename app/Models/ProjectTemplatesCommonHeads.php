<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ProjectTemplatesCommonHeads extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_id', 'head_value'];
    public function project(){
        return $this->belongsTo(Project::class, 'project_id');
    }
}
