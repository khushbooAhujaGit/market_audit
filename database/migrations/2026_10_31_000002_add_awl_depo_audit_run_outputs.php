<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_report_runs', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('zip_path');
            $table->string('styled_path')->nullable()->after('pdf_path');
            // Per-run choices that aren't columns of their own (outputs wanted, uploaded stock files).
            $table->text('options')->nullable()->after('styled_path');
        });
    }

    public function down(): void
    {
        Schema::table('custom_report_runs', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'styled_path', 'options']);
        });
    }
};
