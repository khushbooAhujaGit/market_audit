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
            // Whether this field must be filled when entering/uploading template row data.
            // Independent of main_header/sub_header on project_templates, which are always
            // effectively required — this lets an admin mark ANY other head as required too.
            $table->boolean('is_required')->default(false)->after('value_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_name_heads', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }
};
