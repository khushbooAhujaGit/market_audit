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
        Schema::create('data_assigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('zone_id');
            $table->foreignId('unit_id');
            $table->foreignId('project_id');
            $table->foreignId('activity_id');
            $table->foreignId('template_name_id');
            $table->foreignId('activity_group_id')->nullable();
            $table->foreignId('project_template_id');
            $table->foreignId('template_name_head_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_assigns');
    }
};
