<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class TemplateName extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['template_name'];
    public function getTemplateHeads()
    {
        return $this->hasMany(TemplateNameHead::class, 'template_name_id')
            ->orderBy('id');
    }
}
