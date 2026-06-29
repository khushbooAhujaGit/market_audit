<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('help_text')->nullable()->after('question_type');
            $table->string('validation_rule')->nullable()->default('none')->after('allow_multiple_images');
            $table->string('validation_min')->nullable()->after('validation_rule');
            $table->string('validation_max')->nullable()->after('validation_min');
            $table->string('validation_regex')->nullable()->after('validation_max');
            $table->string('file_types')->nullable()->after('validation_regex');
            $table->decimal('max_file_size_mb', 8, 2)->nullable()->after('file_types');
            $table->string('date_min')->nullable()->after('max_file_size_mb');
            $table->string('date_max')->nullable()->after('date_min');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn([
                'help_text', 'validation_rule', 'validation_min', 'validation_max',
                'validation_regex', 'file_types', 'max_file_size_mb', 'date_min', 'date_max',
            ]);
        });
    }
};
