<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No FK constraint on user_id: the `users` table is MyISAM in this database,
        // and MySQL foreign keys require InnoDB on both sides — matches how other
        // tables in this schema already reference users without a DB-level constraint.
        Schema::create('one_drive_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_drive_tokens');
    }
};
