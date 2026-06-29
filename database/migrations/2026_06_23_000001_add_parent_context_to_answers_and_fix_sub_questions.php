<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow the same child to be linked to multiple parents.
        // Unique pair = (parent_question_id, child_question_id).
        Schema::table('question_sub_questions', function (Blueprint $table) {
            $table->unique(['parent_question_id', 'child_question_id'], 'uq_parent_child');
        });

        // Track which parent triggered a shared sub-question answer.
        Schema::table('temp_user_activity_answers_data', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_context_id')->nullable()->after('question_id')
                  ->comment('For shared sub-questions: the parent question that triggered this answer');
        });
    }

    public function down(): void
    {
        Schema::table('question_sub_questions', function (Blueprint $table) {
            $table->dropUnique('uq_parent_child');
        });
        Schema::table('temp_user_activity_answers_data', function (Blueprint $table) {
            $table->dropColumn('parent_context_id');
        });
    }
};
