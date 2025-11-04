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
        Schema::create('project_template_name_values', function (Blueprint $table) {
            $table->id();
            $table->integer('row_id')->nullable();
            $table->foreignId('project_template_id');
            $table->foreignId('template_name_head_id');
            $table->string('value')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_template_name_values');
    }
};
