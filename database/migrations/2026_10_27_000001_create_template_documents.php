<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set from Type "File" in the heads sheet when a template is created
        Schema::table('template_name_heads', function (Blueprint $table) {
            $table->boolean('is_file')->default(false)->after('is_required');
        });

        // One record per file name / link used in a project's file columns. A name (or link)
        // belongs to one template of the project; any number of that template's rows can use it.
        Schema::create('project_template_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('project_template_id');
            $table->char('file_hash', 40);              // sha1 of the lower-cased name or link (unique per project)
            $table->string('file_name', 255);           // as written in the sheet (or taken from the link)
            $table->text('source_url')->nullable();     // set when the sheet gave a link
            $table->string('stored_path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 20)->default('awaiting_file');   // awaiting_file | queued | available | failed
            $table->text('error')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'file_hash'], 'ptd_project_file_unique');
            $table->index(['project_template_id', 'status'], 'ptd_template_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_documents');
        Schema::table('template_name_heads', function (Blueprint $table) {
            $table->dropColumn('is_file');
        });
    }
};
