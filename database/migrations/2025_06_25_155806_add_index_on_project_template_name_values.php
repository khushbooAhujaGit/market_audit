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
        Schema::table('project_template_name_values', function (Blueprint $table) {
            $table->index("row_id");
            $table->index("project_template_id");
            $table->index("template_name_head_id");
            $table->index('value'); // Specify key length
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_template_name_values', function (Blueprint $table) {
            $table->dropIndex(["row_id"]);
            $table->dropIndex(["project_template_id"]);
            $table->dropIndex(["template_name_head_id"]);
            $table->dropIndex(["value"]);
        });
    }
};
