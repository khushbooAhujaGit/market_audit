<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityRepeatInstance extends Model
{
    use HasFactory;

    protected $table = 'activity_repeat_instances';

    protected $fillable = ['row_id', 'activity_id', 'activity_sequence', 'instance_label', 'user_id', 'status'];

    protected $casts = [
        'id'                => 'integer',
        'row_id'            => 'integer',
        'activity_id'       => 'integer',
        'activity_sequence' => 'integer',
        'user_id'           => 'integer',
        'status'            => 'integer',
    ];

    public function getUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function activityInfo()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
