<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_sub_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_question_id')->constrained('questions')->onDelete('cascade');
            $table->foreignId('child_question_id')->constrained('questions')->onDelete('cascade');
            $table->integer('sequence')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_sub_questions');
    }
};
