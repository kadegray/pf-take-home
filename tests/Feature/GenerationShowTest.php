<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeGeneration(User $user, array $overrides = []): Generation
    {
        return Generation::create(array_merge([
            'user_id' => $user->id,
            'idempotency_key' => (string) str()->uuid(),
            'prompt' => 'a test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => GenerationStatus::Succeeded,
            'estimated_cost_micros' => 1000,
            'actual_cost_micros' => 1000,
            'image_url' => 'https://pixelforge.example/image.png',
        ], $overrides));
    }

    public function test_owner_can_view_their_generation(): void
    {
        $user = User::factory()->create();
        $generation = $this->makeGeneration($user);

        $response = $this->actingAs($user)->get(route('history.show', $generation));

        $response->assertSuccessful();
    }

    public function test_viewing_another_users_generation_is_not_found(): void
    {
        $owner = User::factory()->create();
        $generation = $this->makeGeneration($owner);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->get(route('history.show', $generation));

        $response->assertNotFound();
    }
}
