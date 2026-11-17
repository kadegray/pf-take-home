<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Services\CreditLedger;
use App\Services\Pixelforge\Client;
use App\Services\Pixelforge\Exceptions\PixelforgeException;
use App\Services\Pixelforge\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly Client $pixelforge,
        private readonly CreditLedger $credits,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Generate', [
            'sizes' => Pricing::sizes(),
            'qualities' => Pricing::qualities(),
            'maxReferenceImages' => Pricing::maxReferenceImages(),
        ]);
    }

    /**
     * Server-computed live estimate, so the UI never has its own copy of
     * the pricing formula to drift out of sync with Pricing itself.
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['nullable', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::in(Pricing::sizes())],
            'quality' => ['required', 'string', Rule::in(Pricing::qualities())],
            'reference_images' => ['nullable', 'array', 'max:'.Pricing::maxReferenceImages()],
            'reference_images.*' => ['nullable', 'string'],
        ]);

        $referenceImageCount = count(array_filter($data['reference_images'] ?? []));

        $estimateMicros = $this->pricing->withMarkup(
            $this->pricing->estimate(
                size: $data['size'],
                quality: $data['quality'],
                referenceImageCount: $referenceImageCount,
                prompt: $data['prompt'] ?? '',
            )
        );

        return response()->json(['estimated_cost_micros' => $estimateMicros]);
    }

    public function store(StoreGenerationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $referenceImages = array_values(array_filter($data['reference_images'] ?? []));

        $estimateMicros = $this->pricing->withMarkup(
            $this->pricing->estimate(
                size: $data['size'],
                quality: $data['quality'],
                referenceImageCount: count($referenceImages),
                prompt: $data['prompt'],
            )
        );

        // Creating the row and holding the estimated cost happen in one
        // transaction, so a declined hold rolls the row back too - never
        // leaves an orphaned, un-held generation behind. The Pixelforge
        // HTTP call happens strictly outside this transaction - never hold
        // a row lock across an external network call.
        try {
            $generation = DB::transaction(function () use ($request, $data, $referenceImages, $estimateMicros) {
                $generation = $request->user()->generations()->create([
                    'idempotency_key' => (string) Str::uuid(),
                    'prompt' => $data['prompt'],
                    'size' => $data['size'],
                    'quality' => $data['quality'],
                    'reference_images' => $referenceImages,
                    'status' => GenerationStatus::Queued,
                    'estimated_cost_micros' => $estimateMicros,
                ]);

                if (! $this->credits->tryHold($generation)) {
                    throw new InsufficientBalanceException();
                }

                return $generation;
            });
        } catch (InsufficientBalanceException) {
            return back()->withErrors(['prompt' => 'Insufficient balance for this generation. Buy more credits and try again.']);
        }

        try {
            $response = $this->pixelforge->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $referenceImages,
            ], $generation->idempotency_key);

            $generation->update([
                'pixelforge_id' => $response['id'],
                'status' => GenerationStatus::from($response['status']),
                'pixelforge_created_at' => $response['created_at'] ?? null,
            ]);
        } catch (PixelforgeException $e) {
            $generation->update([
                'status' => GenerationStatus::Failed,
                'actual_cost_micros' => 0,
                'provider_cost_micros' => 0,
                'error_code' => $e->pixelforgeCode,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Pixelforge never accepted this one - no webhook will ever
            // arrive for it, so refund the hold right here instead of
            // waiting for a resolution that will never come.
            $this->credits->settle($generation, actualCostMicros: 0);

            return back()->withErrors(['prompt' => $e->getMessage()]);
        }

        return redirect()->route('history.index');
    }

    public function index(Request $request): Response
    {
        $generations = $request->user()
            ->generations()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('History', [
            'generations' => $generations,
        ]);
    }

    /**
     * Lightweight poll target for History.vue: only the fields needed to
     * update a row in place, only for generations still in flight.
     */
    public function poll(Request $request): JsonResponse
    {
        $generations = $request->user()
            ->generations()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->get(['id', 'status', 'actual_cost_micros', 'image_url', 'error_code', 'error_message']);

        return response()->json(['generations' => $generations]);
    }

    public function show(Request $request, Generation $generation): Response
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        return Inertia::render('GenerationShow', ['generation' => $generation]);
    }

    public function dashboard(Request $request): Response
    {
        $generations = $request->user()
            ->generations()
            ->whereNotNull('image_url')
            ->latest()
            ->take(12)
            ->get(['id', 'image_url', 'image_url_expires_at', 'prompt', 'status']);

        return Inertia::render('Dashboard', ['generations' => $generations]);
    }
}
