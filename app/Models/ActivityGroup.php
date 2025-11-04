<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ActivityGroup extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['activity_group_name'];
public function get_group_activities(){
    return $this->hasMany(ActivityGroupPivot::class, 'activity_group_id')->orderBy('sequence');
}
}
