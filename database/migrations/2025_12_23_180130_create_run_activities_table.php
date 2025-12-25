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
        Schema::create('run_activities', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('run_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $blueprint->string('type'); // 'user_joined', 'payment_marked', 'payment_confirmed', 'status_change', 'comment'
            $blueprint->json('metadata')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_activities');
    }
};
