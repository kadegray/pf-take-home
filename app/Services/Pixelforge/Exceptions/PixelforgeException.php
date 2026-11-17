<?php

namespace App\Services\Pixelforge\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class PixelforgeException extends Exception
{
    public function __construct(
        public readonly int $status,
        public readonly string $type,
        public readonly string $pixelforgeCode,
        string $message,
        public readonly ?string $param = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error', []);

        return new self(
            status: $response->status(),
            type: $error['type'] ?? 'unknown_error',
            pixelforgeCode: $error['code'] ?? 'unknown_error',
            message: $error['message'] ?? 'Pixelforge request failed.',
            param: $error['param'] ?? null,
        );
    }

    public function isRateLimited(): bool
    {
        return in_array($this->pixelforgeCode, [
            'rate_limit_exceeded',
            'concurrency_limit_exceeded',
            'daily_limit_exceeded',
        ], true);
    }

    public function isOverloaded(): bool
    {
        return $this->pixelforgeCode === 'overloaded';
    }

    public function isContentPolicyViolation(): bool
    {
        return $this->pixelforgeCode === 'content_policy_violation';
    }
}
