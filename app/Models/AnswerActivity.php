<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class AnswerActivity extends Model
{
    use HasFactory, HasEncryptedId;
    protected $table = 'temp_user_activity_answers_data';

}
