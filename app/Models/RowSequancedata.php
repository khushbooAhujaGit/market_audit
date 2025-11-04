<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class RowSequancedata extends Model
{
    use HasFactory, HasEncryptedId;
    protected $table = 'group_rowsequance';
    protected $fillable = [
        'user_id', 'row_id', 'activity_id', 'activity_group_id', 'sequence',
    ];
}
