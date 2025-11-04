<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class ClosedAudits extends Model
{
    use HasFactory, HasEncryptedId;

    protected $table = "closed_audits";

    protected $fillable = ['project_template_id', 'main_header', 'sub_header', 'activity_id', 'distributor_value', 'row_id', 'user_id', 'audit_closed_status'];

}
