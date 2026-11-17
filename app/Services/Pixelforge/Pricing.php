<?php

namespace App\Services\Pixelforge;

/**
 * Turns Pixelforge token usage into money, in integer micro-dollars
 * (1 USD = 1,000,000 micros). All arithmetic goes through bcmath so
 * nothing here ever touches a float.
 *
 * Rates (from https://pixelforge.promptedit.dev/docs, GET /v1/pricing)
 * are USD per 1,000,000 tokens, e.g. "5.00".
 */
class Pricing
{
    /**
     * Static copy of GET /v1/pricing's `rates`, used only for the
     * pre-submit estimate. The actual cost always uses the `rates`
     * embedded in the generation's own webhook payload, not this table -
     * deliberately hardcoding here instead of fetching avoids a runtime
     * dependency for a number that only needs to be in the right ballpark.
     */
    private const ESTIMATE_RATES = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    /** Exact output token count per (quality, size) - not a range. */
    private const OUTPUT_TOKENS = [
        'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
        'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
        'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
    ];

    /** [min, max] reasoning token range per quality. */
    private const REASONING_TOKENS = [
        'low' => [0, 500],
        'medium' => [200, 2000],
        'high' => [1000, 8000],
    ];

    private const CHARACTERS_PER_TEXT_TOKEN = 4;

    private const TOKENS_PER_REFERENCE_IMAGE = 765;

    /**
     * Pre-submit estimate, in pre-markup micro-dollars.
     *
     * Text tokens use the midpoint of the documented ±15% variance (i.e.
     * ignore the variance and estimate at strlen/4); output tokens are
     * exact for the given (size, quality); reasoning tokens use the
     * midpoint of the documented range.
     */
    public function estimate(string $size, string $quality, int $referenceImageCount, string $prompt): int
    {
        $usage = [
            'input_text_tokens' => $this->roundHalfUp(mb_strlen($prompt), self::CHARACTERS_PER_TEXT_TOKEN),
            'input_image_tokens' => $referenceImageCount * self::TOKENS_PER_REFERENCE_IMAGE,
            'output_tokens' => self::OUTPUT_TOKENS[$quality][$size] ?? 0,
            'reasoning_tokens' => $this->reasoningMidpoint($quality),
        ];

        return $this->costFromUsage($usage, self::ESTIMATE_RATES);
    }

    /**
     * Real cost from a succeeded generation's usage + its own embedded
     * rates, in pre-markup micro-dollars.
     *
     * @param  array{input_text_tokens?: int, input_image_tokens?: int, output_tokens?: int, reasoning_tokens?: int}  $usage
     * @param  array{input_text_tokens?: string, input_image_tokens?: string, output_tokens?: string, reasoning_tokens?: string}  $rates  USD per 1,000,000 tokens
     */
    public function costFromUsage(array $usage, array $rates): int
    {
        $totalDollars = '0';

        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $type) {
            $tokens = (string) (int) ($usage[$type] ?? 0);
            $ratePerMillion = (string) ($rates[$type] ?? '0');

            $dollars = bcdiv(bcmul($tokens, $ratePerMillion, 10), '1000000', 10);
            $totalDollars = bcadd($totalDollars, $dollars, 10);
        }

        return $this->bcRound(bcmul($totalDollars, '1000000', 10));
    }

    /**
     * Applies the 25% markup. Called explicitly at the point of use so
     * it's visible that markup is applied exactly once.
     */
    public function withMarkup(int $costMicros): int
    {
        return $this->bcRound(bcmul((string) $costMicros, '1.25', 10));
    }

    private function reasoningMidpoint(string $quality): int
    {
        [$min, $max] = self::REASONING_TOKENS[$quality] ?? [0, 0];

        return intdiv($min + $max + 1, 2);
    }

    /**
     * Integer round-half-up of $numerator / $denominator without floats.
     */
    private function roundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    /**
     * Round-half-up a non-negative bcmath decimal string to an int.
     */
    private function bcRound(string $value): int
    {
        return (int) bcadd($value, '0.5', 0);
    }
}
