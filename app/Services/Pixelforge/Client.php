<?php

namespace App\Services\Pixelforge;

use App\Services\Pixelforge\Exceptions\PixelforgeException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    private readonly string $baseUrl;

    private readonly ?string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = $baseUrl ?? config('pixelforge.base_url');
        $this->apiKey = $apiKey ?? config('pixelforge.api_key');
    }

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: array<int, string>, metadata?: array<string, string>}  $params
     * @return array<string, mixed>
     */
    public function createGeneration(array $params, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', $params);

        return $this->decode($response, 202);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGeneration(string $id): array
    {
        $response = $this->request()->get("/generations/{$id}");

        return $this->decode($response, 200);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPricing(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/pricing');

        return $this->decode($response, 200);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, int $expectedStatus): array
    {
        if ($response->status() !== $expectedStatus) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }
}
