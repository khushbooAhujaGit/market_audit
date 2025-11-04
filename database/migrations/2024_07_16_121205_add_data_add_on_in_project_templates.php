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
        Schema::table('project_templates', function (Blueprint $table) {
            $table->string('completion_type')->nullable()->after('activityType');
            $table->string('min_completion')->nullable()->default(0)->after('completion_type');
            $table->boolean('data_add_on')->default(0)->after('min_completion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('completion_type');
            $table->dropColumn('min_completion');
            $table->dropColumn('data_add_on');
        });
    }
};
