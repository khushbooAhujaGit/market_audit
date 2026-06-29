<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_sub_questions', function (Blueprint $table) {
            // Which answer value of the parent question makes this child visible.
            // NULL = always visible regardless of parent answer.
            $table->string('trigger_value')->nullable()->after('sequence');
        });
    }

    public function down(): void
    {
        Schema::table('question_sub_questions', function (Blueprint $table) {
            $table->dropColumn('trigger_value');
        });
    }
};
