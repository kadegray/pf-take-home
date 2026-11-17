<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\CreditLedger;
use App\Services\Pixelforge\Pricing;
use App\Services\Pixelforge\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignature $signature,
        private readonly Pricing $pricing,
        private readonly CreditLedger $credits,
    ) {}

    public function handlePixelforge(Request $request): Response
    {
        $rawBody = $request->getContent();

        if (! $this->signature->verify($request->header('Pixelforge-Signature'), $rawBody)) {
            return response('Invalid signature.', 400);
        }

        $eventId = $request->header('Pixelforge-Event-Id');
        $type = $request->header('Pixelforge-Event-Type');

        if (! $eventId || ! $type) {
            return response('Missing event headers.', 400);
        }

        $payload = json_decode($rawBody, true) ?? [];

        DB::transaction(function () use ($eventId, $type, $payload) {
            $event = $this->recordEvent($eventId, $type, $payload);

            if ($event === null) {
                // Already processed this exact event id - duplicate delivery, no-op.
                return;
            }

            $this->applyEvent($type, data_get($payload, 'data.object', []));
        });

        return response()->noContent();
    }

    private function recordEvent(string $eventId, string $type, array $payload): ?WebhookEvent
    {
        $event = WebhookEvent::firstOrCreate(
            ['pixelforge_event_id' => $eventId],
            ['type' => $type, 'payload' => $payload, 'processed_at' => now()],
        );

        return $event->wasRecentlyCreated ? $event : null;
    }

    private function applyEvent(string $type, array $generationPayload): void
    {
        if ($type === 'ping') {
            return;
        }

        $pixelforgeId = $generationPayload['id'] ?? null;

        if ($pixelforgeId === null) {
            return;
        }

        $generation = Generation::where('pixelforge_id', $pixelforgeId)->lockForUpdate()->first();

        if ($generation === null) {
            return;
        }

        match ($type) {
            'generation.processing' => $this->markProcessing($generation),
            'generation.succeeded' => $this->markSucceeded($generation, $generationPayload),
            'generation.failed' => $this->markFailed($generation, $generationPayload),
            default => null,
        };
    }

    private function markProcessing(Generation $generation): void
    {
        if ($generation->status !== GenerationStatus::Queued) {
            return;
        }

        $generation->update([
            'status' => GenerationStatus::Processing,
            'started_at' => now(),
        ]);
    }

    private function markSucceeded(Generation $generation, array $payload): void
    {
        if (! $this->isPending($generation)) {
            return;
        }

        $usage = $payload['usage'] ?? [];
        $rates = $payload['rates'] ?? [];

        // Stored separately (not derived later by dividing actual_cost_micros
        // back by 1.25) so the admin report can reconcile exactly against
        // Pixelforge's own GET /v1/usage, which reports pre-markup cost.
        $providerCostMicros = $this->pricing->costFromUsage($usage, $rates);
        $actualCostMicros = $this->pricing->withMarkup($providerCostMicros);

        $generation->update([
            'status' => GenerationStatus::Succeeded,
            'usage' => $usage,
            'rates' => $rates,
            'provider_cost_micros' => $providerCostMicros,
            'actual_cost_micros' => $actualCostMicros,
            'image_url' => $payload['image_url'] ?? null,
            'image_url_expires_at' => $payload['image_url_expires_at'] ?? null,
            'completed_at' => $payload['completed_at'] ?? now(),
        ]);

        $this->credits->settle($generation, $actualCostMicros);
    }

    private function markFailed(Generation $generation, array $payload): void
    {
        if (! $this->isPending($generation)) {
            return;
        }

        $error = $payload['error'] ?? [];

        $generation->update([
            'status' => GenerationStatus::Failed,
            'error_code' => $error['code'] ?? null,
            'error_message' => $error['message'] ?? null,
            'actual_cost_micros' => 0,
            'provider_cost_micros' => 0,
            'completed_at' => $payload['completed_at'] ?? now(),
        ]);

        $this->credits->settle($generation, actualCostMicros: 0);
    }

    private function isPending(Generation $generation): bool
    {
        return in_array($generation->status, [GenerationStatus::Queued, GenerationStatus::Processing], true);
    }
}
