<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class RemarkMaster extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['remark', 'status', 'project_id'];

    public function project(){
        return $this->belongsTo(Project::class,'project_id');
    }
    
}
