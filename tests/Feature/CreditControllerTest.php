<?php

namespace Tests\Feature;

use App\Http\Controllers\CreditController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_deposits_a_preset_amount(): void
    {
        $user = User::factory()->create(['balance_micros' => 0]);

        $response = $this->actingAs($user)->post('/credits', [
            'amount_micros' => 10_000_000,
        ]);

        $response->assertRedirect();
        $this->assertSame(10_000_000, $user->fresh()->balance_micros);
    }

    public function test_purchase_rejects_an_amount_outside_the_preset_list(): void
    {
        $user = User::factory()->create(['balance_micros' => 0]);

        $response = $this->actingAs($user)->post('/credits', [
            'amount_micros' => 999,
        ]);

        $response->assertSessionHasErrors('amount_micros');
        $this->assertSame(0, $user->fresh()->balance_micros);
    }

    public function test_preset_amounts_are_exposed_to_the_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/credits');

        $response->assertOk();
        $this->assertSame(
            CreditController::PRESET_AMOUNTS_MICROS,
            $response->viewData('page')['props']['presetAmounts'],
        );
    }
}
