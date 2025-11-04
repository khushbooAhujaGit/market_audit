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
        Schema::create('project_master_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_template_id');
            $table->integer('row_id');
            $table->string('completion_type')->nullable();
            $table->string('min_completion')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_master_templates');
    }
};
