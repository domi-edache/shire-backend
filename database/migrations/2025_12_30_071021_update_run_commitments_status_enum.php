<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing constraint if it exists (Laravel naming convention)
        DB::statement("ALTER TABLE run_commitments DROP CONSTRAINT IF EXISTS run_commitments_status_check");

        // Add new constraint with allowed values
        DB::statement("ALTER TABLE run_commitments ADD CONSTRAINT run_commitments_status_check 
            CHECK (status::text = ANY (ARRAY['pending', 'confirmed', 'rejected', 'paid_marked', 'pending_payment']::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original allowed values (be careful if data exists)
        DB::statement("ALTER TABLE run_commitments DROP CONSTRAINT IF EXISTS run_commitments_status_check");
        DB::statement("ALTER TABLE run_commitments ADD CONSTRAINT run_commitments_status_check 
            CHECK (status::text = ANY (ARRAY['pending', 'confirmed', 'rejected']::text[]))");
    }
};
