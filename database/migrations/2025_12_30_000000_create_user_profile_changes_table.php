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
        Schema::create('user_profile_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field'); // 'postcode', 'name', 'handle', 'avatar'
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('trigger')->default('user'); // 'onboarding', 'user', 'admin'
            $table->timestamps();

            $table->index(['user_id', 'field']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profile_changes');
    }
};
