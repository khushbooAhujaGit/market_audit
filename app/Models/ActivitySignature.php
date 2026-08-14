<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySignature extends Model
{
    use HasFactory;

    protected $fillable = ['row_id', 'activity_id', 'user_id', 'signature_path'];

    protected $casts = [
        'row_id'      => 'integer',
        'activity_id' => 'integer',
        'user_id'     => 'integer',
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
