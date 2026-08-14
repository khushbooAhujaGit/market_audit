<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('endpoint', 500);
            $table->string('full_url', 1000);
            $table->longText('request_payload')->nullable();
            $table->text('error_message');
            $table->longText('stack_trace')->nullable();
            $table->integer('status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_error_logs');
    }
};
