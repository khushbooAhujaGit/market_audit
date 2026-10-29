<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which verifiers hold which data assignment. An admin can assign data to a verifier
        // without auditors; the verifier then assigns their own auditors to it.
        Schema::create('verifier_data_assigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('data_assign_id');
            $table->unsignedBigInteger('verifier_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['data_assign_id', 'verifier_id'], 'verifier_data_assigns_unique');
            $table->index('verifier_id', 'verifier_data_assigns_verifier');
        });

        // Existing assignments: every verifier linked to an auditor of the assignment holds it.
        DB::statement("
            INSERT IGNORE INTO verifier_data_assigns (data_assign_id, verifier_id, assigned_by, created_at, updated_at)
            SELECT DISTINCT u.data_assign_id, l.verifier_id, NULL, NOW(), NOW()
            FROM user_activity_data_assigns u
            JOIN verifier_auditor_links l
                ON l.auditor_id = u.user_id
               AND l.project_template_id = u.project_template_id
               AND l.activity_id = u.activity_id
            JOIN data_assigns d ON d.id = u.data_assign_id
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('verifier_data_assigns');
    }
};
