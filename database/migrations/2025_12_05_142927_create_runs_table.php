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
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_name');
            $table->enum('status', ['prepping', 'live', 'completed'])->default('prepping');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Add PostGIS geography column for location
        DB::statement('ALTER TABLE runs ADD COLUMN location GEOGRAPHY(POINT, 4326)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
