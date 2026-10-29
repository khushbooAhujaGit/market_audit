<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A file named (or linked) in a file column of a project template's data. */
class TemplateDocument extends Model
{
    protected $table = 'project_template_documents';

    protected $fillable = [
        'project_id', 'project_template_id', 'file_hash', 'file_name', 'source_url', 'stored_path',
        'original_name', 'mime', 'size', 'status', 'error', 'uploaded_by',
    ];
}
