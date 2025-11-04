<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ActivityGroupPivot extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['activity_group_id', 'activity_id', 'sequence'];

    public function getActivityInfo(){
        return $this->belongsTo(Activity::class, 'activity_id');
    }
    public function activityName(){
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
