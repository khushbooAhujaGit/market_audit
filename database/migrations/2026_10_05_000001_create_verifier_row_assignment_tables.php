<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Row-level verifier assignment, mirroring user_audit_assigns for auditors.
        Schema::create('verifier_row_assigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_template_id');
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('row_id');
            $table->timestamps();

            $table->unique(['user_id', 'project_template_id', 'activity_id', 'row_id'], 'verifier_row_assigns_unique');
            $table->index(['project_template_id', 'activity_id', 'row_id'], 'verifier_row_assigns_lookup');
        });

        // Which verifier checks which auditor's work on a template/activity. Rows an
        // auditor creates later are handed to the auditor's linked verifiers.
        Schema::create('verifier_auditor_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verifier_id');
            $table->unsignedBigInteger('auditor_id');
            $table->unsignedBigInteger('project_template_id');
            $table->unsignedBigInteger('activity_id');
            $table->timestamps();

            $table->unique(['verifier_id', 'auditor_id', 'project_template_id', 'activity_id'], 'verifier_auditor_links_unique');
            $table->index(['auditor_id', 'project_template_id', 'activity_id'], 'verifier_auditor_links_auditor');
        });

        // Set only on assignments made before row-wise assignment existed: the verifier
        // keeps seeing every row of the template up to this id (all data at deploy time).
        Schema::table('verifiers', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_max_row_id')->nullable()->after('status');
        });

        DB::statement("
            UPDATE verifiers v
            SET v.legacy_max_row_id = COALESCE(
                (SELECT MAX(r.id) FROM project_template_name_values_new r
                 WHERE r.project_template_id = v.project_template_name_id), 0)
        ");

        // Old verifiers keep receiving rows created later by the auditors already
        // assigned on their templates, as they do today.
        DB::statement("
            INSERT IGNORE INTO verifier_auditor_links
                (verifier_id, auditor_id, project_template_id, activity_id, created_at, updated_at)
            SELECT DISTINCT v.user_id, u.user_id, v.project_template_name_id, u.activity_id, NOW(), NOW()
            FROM verifiers v
            JOIN user_activity_data_assigns u ON u.project_template_id = v.project_template_name_id
            WHERE u.user_id IS NOT NULL AND u.activity_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('verifiers', function (Blueprint $table) {
            $table->dropColumn('legacy_max_row_id');
        });
        Schema::dropIfExists('verifier_auditor_links');
        Schema::dropIfExists('verifier_row_assigns');
    }
};
