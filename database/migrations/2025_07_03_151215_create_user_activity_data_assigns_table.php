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
        Schema::create('user_activity_data_assigns', function (Blueprint $table) {
            $table->id();
            $table->integer('data_assign_id');
            $table->integer('user_id');
            $table->integer('project_template_id');
            $table->integer('activity_id');
            $table->integer('activity_sequence_id');
            $table->integer('common_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_data_assigns');
    }
};
