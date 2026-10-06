<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 1 = admin marked the project completed: hidden from auditors (APK + web) and the
            // verifier queue; still reportable. Admin can reopen it at any time (back to 0).
            $table->boolean('is_completed')->default(0)->after('is_infiltration_report_applicable');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['is_completed', 'completed_at', 'completed_by']);
        });
    }
};
