<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class TemplateNameHeadOption extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['template_name_head_id', 'option'];

    public function templateNameHead()
    {
        return $this->belongsTo(TemplateNameHead::class, 'template_name_head_id');
    }
}
