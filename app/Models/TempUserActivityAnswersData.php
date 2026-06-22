<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class TempUserActivityAnswersData extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['user_id', 'row_id', 'activity_group_name_id', 'activity_sequence', 'activity_id', 'question_id', 'user_answer', 'same_answer_id', 'status', 'is_draft', 'remark', 'verified_by', 'edited_by_verifier', 'mobile_no', 'mobile_otp','otp_verified_status', 'latitude', 'longitude', 'subjective_parent'];

    public function getUser(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function getVerifier(){
        return $this->belongsTo(User::class, 'verified_by');
    }
    public function getQuestionInfo(){
        return $this->belongsTo(Question::class, 'question_id');
    }
    public function projectTemplateNameValue()
    {
        return $this->belongsTo(ProjectTemplateNameValue::class, 'row_id', 'id');
    }
    public function get_activity_info(){
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function get_remark_info(){
        return $this->belongsTo(RemarkMaster::class, 'remark');
    }
}
