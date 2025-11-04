<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAuditAssigns extends Model
{
    use HasFactory;

    protected $table = 'user_audit_assigns';

    protected $fillable = ['row_id', 'common_id'];

    public function projectTemplateRowsData()
    {
        return $this->belongsTo(ProjectTemplateNameValuesNew::class, 'row_id', 'id');
    }

}
