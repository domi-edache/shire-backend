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
        Schema::create('run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->onDelete('cascade');
            $table->foreignId('requester_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['personal_request', 'bulk_split']);
            $table->string('title');
            $table->decimal('cost', 8, 2)->nullable();
            $table->integer('units_total')->nullable();
            $table->integer('units_filled')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_items');
    }
};
