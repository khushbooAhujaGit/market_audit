<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ProjectType extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['project_type_name', 'project_type_code'];

}
