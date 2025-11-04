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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->unique()->after('name');
            $table->string('address1')->nullable()->after('password');
            $table->string('address2')->nullable()->after('address1');
            $table->string('city')->nullable()->after('address2');
            $table->string('district')->nullable()->after('city');
            $table->string('state')->nullable()->after('district');
            $table->string('pincode')->nullable()->after('state');
            $table->string('password_info')->nullable()->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
