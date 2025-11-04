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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->foreignId('project_type_id')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('zone_id')->nullable();
            $table->foreignId('unit_id')->nullable();
            $table->boolean('with_data')->default(0);
            $table->boolean('is_application_applicable')->default(0);
            $table->boolean('recurring')->default(0);
            $table->boolean('data_add_on')->default(0);


            $table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
