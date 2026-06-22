<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityInstanceClose extends Model
{
    use HasFactory;
    protected $fillable = ['row_id', 'activity_id', 'user_id'];
}
