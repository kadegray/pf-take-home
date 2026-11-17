<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerationBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_when_balance_is_insufficient_and_never_calls_pixelforge(): void
    {
        Http::fake();

        $user = User::factory()->create(['balance_micros' => 0]);

        $response = $this->actingAs($user)->post('/generate', [
            'prompt' => 'an expensive generation',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => [],
        ]);

        $response->assertSessionHasErrors('prompt');
        $this->assertSame(0, Generation::count());
        $this->assertSame(0, $user->fresh()->balance_micros);
        Http::assertNothingSent();
    }

    public function test_store_succeeds_and_holds_the_estimate_when_affordable(): void
    {
        Http::fake([
            'pixelforge.promptedit.dev/*' => Http::response([
                'id' => 'gen_fake_123',
                'status' => 'queued',
                'created_at' => now()->toIso8601String(),
            ], 202),
        ]);

        $user = User::factory()->create(['balance_micros' => 1_000_000_000]);

        $response = $this->actingAs($user)->post('/generate', [
            'prompt' => 'a cheap generation',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => [],
        ]);

        $response->assertRedirect(route('history.index'));

        $generation = Generation::sole();
        $this->assertSame('gen_fake_123', $generation->pixelforge_id);

        $expectedBalance = 1_000_000_000 - $generation->estimated_cost_micros;
        $this->assertSame($expectedBalance, $user->fresh()->balance_micros);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'type' => 'hold',
            'amount_micros' => -$generation->estimated_cost_micros,
        ]);
    }
}
