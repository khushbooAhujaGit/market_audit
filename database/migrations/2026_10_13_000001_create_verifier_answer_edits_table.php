<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of answers changed by a verifier on the verify page (saved on Approve):
 * the auditor's original answer, the new answer, who changed it and when.
 * old_answer is NULL when the verifier filled a question the auditor left blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifier_answer_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('answer_id');            // temp_user_activity_answers_data.id
            $table->unsignedBigInteger('row_id');
            $table->unsignedBigInteger('activity_id');
            $table->integer('activity_sequence')->default(0);
            $table->unsignedBigInteger('question_id');
            $table->longText('old_answer')->nullable();
            $table->longText('new_answer')->nullable();
            $table->unsignedBigInteger('edited_by');            // verifier user id
            $table->timestamps();

            $table->index(['row_id', 'activity_id', 'activity_sequence'], 'vae_row_activity_seq');
            $table->index('answer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifier_answer_edits');
    }
};
