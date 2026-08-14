<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop any index on 'question' before changing to text (text columns cannot be fully indexed)
        DB::statement('ALTER TABLE questions MODIFY question TEXT NOT NULL');
        DB::statement('ALTER TABLE questions MODIFY help_text TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE questions MODIFY question VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE questions MODIFY help_text VARCHAR(255) NULL');
    }
};
