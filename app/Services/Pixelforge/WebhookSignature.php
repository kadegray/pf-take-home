<?php

namespace App\Services\Pixelforge;

class WebhookSignature
{
    private const MAX_AGE_SECONDS = 300;

    private readonly string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (config('pixelforge.webhook_secret') ?? '');
    }

    /**
     * Verifies a `Pixelforge-Signature: t=<ts>,v1=<hmac>` header against
     * the raw request body. The signature covers "<timestamp>.<raw body>",
     * so this must run against the untouched body, before any JSON parsing.
     */
    public function verify(?string $header, string $rawBody): bool
    {
        if ($this->secret === '' || $header === null) {
            return false;
        }

        [$timestamp, $signature] = $this->parse($header);

        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function parse(string $header): array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

            if ($key === 't' && $value !== null && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signature = $value;
            }
        }

        return [$timestamp, $signature];
    }
}
