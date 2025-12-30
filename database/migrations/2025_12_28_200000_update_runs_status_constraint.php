<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old CHECK constraint and add a new one with all statuses
        DB::statement('ALTER TABLE runs DROP CONSTRAINT IF EXISTS runs_status_check');
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_status_check CHECK (status IN ('prepping', 'live', 'heading_back', 'arrived', 'completed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE runs DROP CONSTRAINT IF EXISTS runs_status_check');
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_status_check CHECK (status IN ('prepping', 'live', 'completed'))");
    }
};
