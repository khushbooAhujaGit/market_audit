<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifierAuditorLink extends Model
{
    protected $fillable = ['verifier_id', 'auditor_id', 'project_template_id', 'activity_id'];
}
