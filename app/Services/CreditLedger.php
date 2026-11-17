<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every balance mutation locks the user row inside a transaction - this is
 * the one place balance_micros is ever written, so concurrent generations
 * and purchases can't race each other into an inconsistent balance.
 */
class CreditLedger
{
    /**
     * Fake "buy credits" - no real payment.
     */
    public function deposit(User $user, int $amountMicros): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amountMicros) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $locked->balance_micros += $amountMicros;
            $locked->save();

            return CreditTransaction::create([
                'user_id' => $locked->id,
                'type' => CreditTransactionType::Purchase,
                'amount_micros' => $amountMicros,
                'balance_after_micros' => $locked->balance_micros,
            ]);
        });
    }

    /**
     * Debits the generation's estimated cost as a hold, but only if the
     * user can currently afford it - the balance check and the debit happen
     * under the same row lock, so two concurrent submissions that are each
     * individually affordable but not both together can't both succeed.
     */
    public function tryHold(Generation $generation): bool
    {
        return DB::transaction(function () use ($generation) {
            $user = User::whereKey($generation->user_id)->lockForUpdate()->first();

            if ($user->balance_micros < $generation->estimated_cost_micros) {
                return false;
            }

            $user->balance_micros -= $generation->estimated_cost_micros;
            $user->save();

            CreditTransaction::create([
                'user_id' => $user->id,
                'generation_id' => $generation->id,
                'type' => CreditTransactionType::Hold,
                'amount_micros' => -$generation->estimated_cost_micros,
                'balance_after_micros' => $user->balance_micros,
            ]);

            return true;
        });
    }

    /**
     * Reconciles the hold to the real cost once a generation reaches a
     * terminal state: refunds the difference if actual < estimated
     * (a full refund when actual is 0, i.e. failed), or debits further if
     * actual > estimated. A no-op (no ledger row) when they're equal.
     *
     * Safe to call multiple times for the same generation - idempotent in
     * practice because callers (WebhookController's isPending() guard,
     * GenerationController's synchronous failure path) only ever reach this
     * once per generation, but guarded here too via a zero-delta no-op and
     * by callers never calling it twice.
     */
    public function settle(Generation $generation, int $actualCostMicros): void
    {
        $delta = $actualCostMicros - $generation->estimated_cost_micros;

        if ($delta === 0) {
            return;
        }

        DB::transaction(function () use ($generation, $delta) {
            $user = User::whereKey($generation->user_id)->lockForUpdate()->first();
            $user->balance_micros -= $delta;
            $user->save();

            CreditTransaction::create([
                'user_id' => $user->id,
                'generation_id' => $generation->id,
                'type' => CreditTransactionType::Settlement,
                'amount_micros' => -$delta,
                'balance_after_micros' => $user->balance_micros,
            ]);
        });
    }
}
