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
            $table->boolean('is_master')->default(0)->nullable()->after('template_name_id');
            $table->unsignedInteger('master_head_id')->nullable()->after('is_master');
            $table->unsignedInteger('own_reference_head_id')->nullable()->after('master_head_id');
            $table->boolean('with_data')->default(0)->nullable()->after('data_add_on');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('is_master');
            $table->dropColumn('master_head_id');
            $table->dropColumn('own_reference_head_id');
            $table->dropColumn('with_data');
        });
    }
};
