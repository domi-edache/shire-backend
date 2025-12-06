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
        Schema::table('users', function (Blueprint $table) {
            // Add OAuth provider IDs
            $table->string('google_id')->nullable()->index()->after('email');
            $table->string('apple_id')->nullable()->index()->after('google_id');

            // Make password nullable for OAuth users
            $table->string('password')->nullable()->change();

            // Make handle, address_line_1, and postcode nullable
            $table->string('handle')->nullable()->change();
            $table->string('address_line_1')->nullable()->change();
            $table->string('postcode')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'apple_id']);

            // Note: Reverting nullable changes requires data migration
            // to ensure no null values exist before making NOT NULL
        });
    }
};
