<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class UserActivityAnswersData extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['user_id', 'row_id', 'activity_group_name_id', 'activity_sequence', 'activity_id', 'question_id', 'user_answer', 'same_answer_id', 'status', 'remark', 'verified_by'];


}
