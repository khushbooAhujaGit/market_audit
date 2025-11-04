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
            $table->bigInteger('sub_header')->nullable()->after('activityType');
            $table->bigInteger('main_header')->nullable()->after('activityType');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('sub_header');
            $table->dropColumn('main_header');
        });
    }
};
