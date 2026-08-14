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
        Schema::table('template_name_heads', function (Blueprint $table) {
            // null / 'text' = free text (today's behavior, unchanged for all existing heads).
            // 'dropdown' / 'yes_no' = value must match one of this head's template_name_head_options.
            $table->string('value_type')->nullable()->after('template_head_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_name_heads', function (Blueprint $table) {
            $table->dropColumn('value_type');
        });
    }
};
