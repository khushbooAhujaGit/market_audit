<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auditor_assigned_data', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('data_assign_id');
            $table->foreignId('template_name_id')->nullable()->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditor_assigned_data', function (Blueprint $table) {
            $table->dropColumn('project_id');
            $table->dropColumn('template_name_id');
        });
    }
};
