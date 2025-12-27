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
            $table->decimal('trust_score', 3, 1)->nullable()->after('postcode');
            $table->integer('hauls_hosted')->default(0)->after('trust_score');
            $table->integer('hauls_joined')->default(0)->after('hauls_hosted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trust_score', 'hauls_hosted', 'hauls_joined']);
        });
    }
};
