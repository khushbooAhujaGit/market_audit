<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the public Support page. */
class SupportRequest extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'message', 'status', 'thank_you_sent_at',
        'resolved_by', 'resolved_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'thank_you_sent_at' => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
