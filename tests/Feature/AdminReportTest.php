<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeGeneration(User $user, array $overrides = []): Generation
    {
        return Generation::create(array_merge([
            'user_id' => $user->id,
            'pixelforge_id' => 'gen_'.uniqid(),
            'idempotency_key' => (string) str()->uuid(),
            'prompt' => 'a test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => GenerationStatus::Succeeded,
            'estimated_cost_micros' => 1000,
            'actual_cost_micros' => 1250,
            'provider_cost_micros' => 1000,
            'completed_at' => now(),
        ], $overrides));
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/report')->assertForbidden();
    }

    public function test_admin_can_view_the_report(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/report')->assertOk();
    }

    public function test_report_aggregates_spend_and_provider_cost_per_day_and_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $today = now('UTC')->startOfDay();

        $this->makeGeneration($alice, ['actual_cost_micros' => 1000, 'provider_cost_micros' => 800, 'completed_at' => $today]);
        $this->makeGeneration($alice, ['actual_cost_micros' => 2000, 'provider_cost_micros' => 1600, 'completed_at' => $today]);
        $this->makeGeneration($bob, ['actual_cost_micros' => 500, 'provider_cost_micros' => 400, 'completed_at' => $today]);

        $response = $this->actingAs($admin)->get('/admin/report');
        $response->assertOk();

        $rows = $response->viewData('page')['props']['rows'];
        $totals = $response->viewData('page')['props']['totals'];

        $aliceRow = collect($rows)->firstWhere('user_id', $alice->id);
        $bobRow = collect($rows)->firstWhere('user_id', $bob->id);

        $this->assertSame(2, $aliceRow['generation_count']);
        $this->assertSame(3000, $aliceRow['spend_micros']);
        $this->assertSame(2400, $aliceRow['provider_cost_micros']);
        $this->assertSame(600, $aliceRow['profit_micros']);

        $this->assertSame(1, $bobRow['generation_count']);
        $this->assertSame(500, $bobRow['spend_micros']);

        $this->assertSame(3500, $totals['spend_micros']);
        $this->assertSame(2800, $totals['provider_cost_micros']);
        $this->assertSame(700, $totals['profit_micros']);
    }

    public function test_failed_generations_are_excluded_from_the_report(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->makeGeneration($user, [
            'status' => GenerationStatus::Failed,
            'actual_cost_micros' => 0,
            'provider_cost_micros' => 0,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/report');
        $rows = $response->viewData('page')['props']['rows'];

        $this->assertCount(0, $rows);
    }

    public function test_date_range_filters_out_generations_outside_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->makeGeneration($user, ['completed_at' => now('UTC')->subDays(10)]);

        $response = $this->actingAs($admin)->get('/admin/report?'.http_build_query([
            'start_date' => now('UTC')->subDays(2)->toDateString(),
            'end_date' => now('UTC')->toDateString(),
        ]));

        $rows = $response->viewData('page')['props']['rows'];

        $this->assertCount(0, $rows);
    }
}
