<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // Pre-markup provider cost, set alongside actual_cost_micros on
            // success. Stored directly (not derived by dividing actual_cost_micros
            // back by 1.25) so it can be reconciled exactly against Pixelforge's
            // own GET /v1/usage, which reports pre-markup cost.
            $table->unsignedBigInteger('provider_cost_micros')->nullable()->after('actual_cost_micros');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn('provider_cost_micros');
        });
    }
};
