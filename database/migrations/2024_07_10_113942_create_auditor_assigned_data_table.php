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
        Schema::create('auditor_assigned_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_assign_id');
            $table->foreignId('user_id');
            $table->string('template_name_head_value');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditor_assigned_data');
    }
};
