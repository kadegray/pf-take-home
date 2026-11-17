<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new CreditLedger();
    }

    private function makeGeneration(User $user, int $estimatedCostMicros): Generation
    {
        return Generation::create([
            'user_id' => $user->id,
            'idempotency_key' => (string) str()->uuid(),
            'prompt' => 'a test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            'estimated_cost_micros' => $estimatedCostMicros,
        ]);
    }

    public function test_deposit_increases_balance_and_records_a_transaction(): void
    {
        $user = User::factory()->create(['balance_micros' => 1_000_000]);

        $this->ledger->deposit($user, 5_000_000);

        $this->assertSame(6_000_000, $user->fresh()->balance_micros);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'amount_micros' => 5_000_000,
            'balance_after_micros' => 6_000_000,
        ]);
    }

    public function test_try_hold_debits_the_estimate_when_affordable(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->assertTrue($this->ledger->tryHold($generation));
        $this->assertSame(6_000, $user->fresh()->balance_micros);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'type' => 'hold',
            'amount_micros' => -4_000,
            'balance_after_micros' => 6_000,
        ]);
    }

    public function test_try_hold_fails_and_does_not_mutate_balance_when_unaffordable(): void
    {
        $user = User::factory()->create(['balance_micros' => 1_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->assertFalse($this->ledger->tryHold($generation));
        $this->assertSame(1_000, $user->fresh()->balance_micros);
        $this->assertSame(0, CreditTransaction::count());
    }

    public function test_settle_fully_refunds_the_hold_when_actual_is_zero(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->ledger->tryHold($generation);
        $this->assertSame(6_000, $user->fresh()->balance_micros);

        $this->ledger->settle($generation, actualCostMicros: 0);

        $this->assertSame(10_000, $user->fresh()->balance_micros);
    }

    public function test_settle_refunds_the_difference_when_actual_is_less_than_estimated(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->ledger->tryHold($generation);
        $this->ledger->settle($generation, actualCostMicros: 2_500);

        // Held 4,000; actual was 2,500 -> balance should be down by exactly 2,500.
        $this->assertSame(7_500, $user->fresh()->balance_micros);
    }

    public function test_settle_charges_the_difference_when_actual_exceeds_estimated(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->ledger->tryHold($generation);
        $this->ledger->settle($generation, actualCostMicros: 6_000);

        // Held 4,000; actual was 6,000 -> balance should be down by exactly 6,000.
        $this->assertSame(4_000, $user->fresh()->balance_micros);
    }

    public function test_settle_is_a_no_op_when_actual_equals_estimated(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generation = $this->makeGeneration($user, 4_000);

        $this->ledger->tryHold($generation);
        $this->ledger->settle($generation, actualCostMicros: 4_000);

        $this->assertSame(6_000, $user->fresh()->balance_micros);
        $this->assertSame(1, CreditTransaction::where('type', 'hold')->count());
        $this->assertSame(0, CreditTransaction::where('type', 'settlement')->count());
    }

    public function test_ledger_sum_always_equals_the_cached_balance(): void
    {
        $user = User::factory()->create(['balance_micros' => 10_000]);
        $generationA = $this->makeGeneration($user, 3_000);
        $generationB = $this->makeGeneration($user, 2_000);

        $this->ledger->deposit($user, 5_000);
        $this->ledger->tryHold($generationA);
        $this->ledger->tryHold($generationB);
        $this->ledger->settle($generationA, actualCostMicros: 1_000);
        $this->ledger->settle($generationB, actualCostMicros: 2_500);

        $sum = CreditTransaction::where('user_id', $user->id)->sum('amount_micros');

        $this->assertSame((int) $sum + 10_000, $user->fresh()->balance_micros);
    }

    public function test_concurrent_affordability_is_checked_against_balance_remaining_after_prior_holds(): void
    {
        // Not literal concurrency (sqlite's single connection in tests can't
        // simulate that), but proves the invariant the row lock exists for:
        // two individually-affordable holds that together exceed the
        // balance can't both succeed.
        $user = User::factory()->create(['balance_micros' => 5_000]);
        $generationA = $this->makeGeneration($user, 3_000);
        $generationB = $this->makeGeneration($user, 3_000);

        $this->assertTrue($this->ledger->tryHold($generationA));
        $this->assertFalse($this->ledger->tryHold($generationB));
        $this->assertSame(2_000, $user->fresh()->balance_micros);
    }
}
