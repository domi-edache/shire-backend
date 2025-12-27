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
        Schema::table('runs', function (Blueprint $table) {
            $table->text('pickup_instructions')->nullable()->after('pickup_image_path');
            $table->decimal('runner_fee', 8, 2)->default(0)->after('pickup_instructions');
            $table->string('runner_fee_type')->default('free')->after('runner_fee');
        });

        // Add GIST spatial index for performance
        DB::statement('CREATE INDEX runs_location_gist ON runs USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS runs_location_gist');

        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn(['pickup_instructions', 'runner_fee', 'runner_fee_type']);
        });
    }
};
