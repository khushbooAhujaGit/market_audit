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
        Schema::table('data_assigns', function (Blueprint $table) {
            $table->boolean('is_outlet_assigned')->default(0)->after('template_name_head_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_assigns', function (Blueprint $table) {
            $table->dropColumn('is_outlet_assigned');
        });
    }
};
