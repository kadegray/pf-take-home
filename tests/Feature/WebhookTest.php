<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['pixelforge.webhook_secret' => self::SECRET]);
    }

    private function makeGeneration(array $overrides = []): Generation
    {
        return Generation::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'pixelforge_id' => 'gen_test_'.uniqid(),
            'idempotency_key' => (string) str()->uuid(),
            'prompt' => 'a test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            'estimated_cost_micros' => 1000,
        ], $overrides));
    }

    private function postWebhook(
        array $payload,
        string $eventId,
        string $type,
        ?string $secret = self::SECRET,
        ?int $timestamp = null,
        bool $corruptSignature = false,
        bool $omitSignature = false,
    ) {
        $body = json_encode($payload);
        $timestamp ??= time();

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_EVENT_ID' => $eventId,
            'HTTP_PIXELFORGE_EVENT_TYPE' => $type,
        ];

        if (! $omitSignature) {
            $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret ?? '');

            if ($corruptSignature) {
                $signature = strrev($signature);
            }

            $server['HTTP_PIXELFORGE_SIGNATURE'] = "t={$timestamp},v1={$signature}";
        }

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], $server, $body);
    }

    private function succeededPayload(Generation $generation, array $usageOverrides = []): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => $generation->pixelforge_id,
                    'status' => 'succeeded',
                    'usage' => array_merge([
                        'input_text_tokens' => 10,
                        'input_image_tokens' => 0,
                        'output_tokens' => 100,
                        'reasoning_tokens' => 50,
                    ], $usageOverrides),
                    'rates' => [
                        'input_text_tokens' => '5.00',
                        'input_image_tokens' => '10.00',
                        'output_tokens' => '40.00',
                        'reasoning_tokens' => '12.50',
                    ],
                    'image_url' => 'https://pixelforge.example/image.png',
                    'image_url_expires_at' => now()->addDay()->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    public function test_valid_succeeded_event_updates_the_generation(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $response = $this->postWebhook($payload, 'evt_1', 'generation.succeeded');

        $response->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        // 10*5 + 0*10 + 100*40 + 50*12.5 = 50 + 0 + 4000 + 625 = 4675; *1.25 = 5843.75 -> 5844
        $this->assertSame(5844, $generation->actual_cost_micros);
        $this->assertNotNull($generation->image_url);
        $this->assertSame(1, WebhookEvent::count());
    }

    public function test_duplicate_event_id_is_a_no_op_and_recorded_once(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $this->postWebhook($payload, 'evt_dup', 'generation.succeeded')->assertSuccessful();
        $firstCostMicros = $generation->refresh()->actual_cost_micros;

        // Redeliver the exact same event id, even with a different (bogus) body -
        // dedup is keyed on the event id, not the payload.
        $tamperedPayload = $this->succeededPayload($generation, ['output_tokens' => 999_999]);
        $this->postWebhook($tamperedPayload, 'evt_dup', 'generation.succeeded')->assertSuccessful();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame($firstCostMicros, $generation->refresh()->actual_cost_micros);
    }

    public function test_stale_processing_after_succeeded_does_not_regress_status_or_cost(): void
    {
        $generation = $this->makeGeneration();
        $succeededPayload = $this->succeededPayload($generation);

        $this->postWebhook($succeededPayload, 'evt_succeeded', 'generation.succeeded')->assertSuccessful();
        $generation->refresh();
        $costAfterSuccess = $generation->actual_cost_micros;
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);

        $staleProcessingPayload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'generation.processing',
            'data' => ['object' => ['id' => $generation->pixelforge_id, 'status' => 'processing']],
        ];

        $this->postWebhook($staleProcessingPayload, 'evt_stale_processing', 'generation.processing')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame($costAfterSuccess, $generation->actual_cost_micros);
    }

    public function test_failed_event_sets_zero_cost_and_error_details(): void
    {
        $generation = $this->makeGeneration();

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'generation.failed',
            'data' => [
                'object' => [
                    'id' => $generation->pixelforge_id,
                    'status' => 'failed',
                    'error' => ['code' => 'content_policy_violation', 'message' => 'Rejected.'],
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ];

        $this->postWebhook($payload, 'evt_failed', 'generation.failed')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->actual_cost_micros);
        $this->assertSame('content_policy_violation', $generation->error_code);
    }

    public function test_ping_event_is_acknowledged_without_side_effects(): void
    {
        $this->postWebhook(['id' => 'evt_ping', 'type' => 'ping', 'data' => []], 'evt_ping', 'ping')
            ->assertSuccessful();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(0, Generation::count());
    }

    public function test_bad_signature_is_rejected_with_no_db_writes(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $response = $this->postWebhook($payload, 'evt_bad_sig', 'generation.succeeded', corruptSignature: true);

        $response->assertStatus(400);
        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
    }

    public function test_missing_signature_is_rejected_with_no_db_writes(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $response = $this->postWebhook($payload, 'evt_no_sig', 'generation.succeeded', omitSignature: true);

        $response->assertStatus(400);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $response = $this->postWebhook(
            $payload,
            'evt_stale_ts',
            'generation.succeeded',
            timestamp: time() - 301,
        );

        $response->assertStatus(400);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $generation = $this->makeGeneration();
        $payload = $this->succeededPayload($generation);

        $response = $this->postWebhook($payload, 'evt_wrong_secret', 'generation.succeeded', secret: 'not-the-real-secret');

        $response->assertStatus(400);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_event_for_unknown_generation_is_acknowledged_and_ignored(): void
    {
        $payload = [
            'id' => 'evt_unknown',
            'type' => 'generation.succeeded',
            'data' => ['object' => ['id' => 'gen_does_not_exist', 'status' => 'succeeded', 'usage' => [], 'rates' => []]],
        ];

        $this->postWebhook($payload, 'evt_unknown', 'generation.succeeded')->assertSuccessful();

        $this->assertSame(1, WebhookEvent::count());
    }
}
