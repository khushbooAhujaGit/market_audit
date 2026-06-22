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
        Schema::create('activity_repeat_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('row_id');
            $table->unsignedBigInteger('activity_id');
            $table->unsignedInteger('activity_sequence');
            $table->string('instance_label');
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('status')->default(0); // 0 = pending, 1 = submitted
            $table->timestamps();

            $table->index(['row_id', 'activity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_repeat_instances');
    }
};
