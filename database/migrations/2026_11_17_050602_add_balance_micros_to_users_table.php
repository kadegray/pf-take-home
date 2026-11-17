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
        Schema::table('users', function (Blueprint $table) {
            // Signed (not unsigned): a balance should never go negative in
            // practice (see CreditLedger), but using a signed column means
            // a bug surfaces as a visibly wrong number instead of a DB
            // constraint violation masking what actually happened.
            // 5_000_000 micros ($5.00) starting grant so new accounts can
            // try the app immediately without buying credits first.
            $table->bigInteger('balance_micros')->default(5_000_000)->after('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance_micros');
        });
    }
};
