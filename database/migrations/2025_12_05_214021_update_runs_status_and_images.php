<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            // Add pickup image path
            $table->string('pickup_image_path')->nullable()->after('location');

            // Change status from enum to string for flexibility
            // This allows adding new statuses like 'heading_back', 'arrived' without enum migration issues
            $table->string('status')->default('prepping')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('pickup_image_path');
            // Note: Reverting to enum would require recreating the column
        });
    }
};
