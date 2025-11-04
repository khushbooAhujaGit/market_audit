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
        Schema::create('project_template_name_values_new', function (Blueprint $table) {
            $table->id();
            $table->integer('project_template_id');
            $table->json('template_data_json');
            $table->integer('verified_by_company')->default(0);
            $table->integer('company_user_id')->nullable();
            $table->integer('audit_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_template_name_values_new');
    }
};
