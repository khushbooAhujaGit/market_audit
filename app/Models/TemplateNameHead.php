<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class TemplateNameHead extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['template_name_id', 'template_head_name', 'value_type', 'is_required'];

     public function templatevalue(){

        return $this->hasOne(ProjectTemplateNameValue::class, 'id' ,'template_name_head_id');
    }

    public function getOptions()
    {
        return $this->hasMany(TemplateNameHeadOption::class, 'template_name_head_id');
    }

    public function scopeGetColume($query, $templateNameId, $keyName){

        return $query->where('template_name_id', $templateNameId)->where('template_head_name', $keyName);
    }
}
