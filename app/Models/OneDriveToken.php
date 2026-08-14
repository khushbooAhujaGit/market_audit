<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OneDriveToken extends Model
{
    protected $fillable = ['user_id', 'access_token', 'refresh_token', 'expires_at'];

    protected $casts = [
        'user_id'      => 'integer',
        'access_token' => 'encrypted',
        'refresh_token'=> 'encrypted',
        'expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
