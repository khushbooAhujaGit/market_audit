<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiErrorLog extends Model
{
    protected $fillable = [
        'method',
        'endpoint',
        'full_url',
        'request_payload',
        'error_message',
        'stack_trace',
        'status_code',
        'user_id',
        'ip_address',
        'user_agent',
    ];
}
