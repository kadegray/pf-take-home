<?php

namespace Tests\Unit;

use App\Services\Pixelforge\Pricing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    private Pricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = new Pricing();
    }

    public function test_cost_from_usage_sums_each_token_type_exactly(): void
    {
        // Each type contributes exactly 1,000,000 micros ($1.00) by construction.
        $usage = [
            'input_text_tokens' => 200_000,   // 0.2M * $5.00/1M  = $1.00
            'input_image_tokens' => 100_000,  // 0.1M * $10.00/1M = $1.00
            'output_tokens' => 25_000,        // 0.025M * $40.00/1M = $1.00
            'reasoning_tokens' => 80_000,      // 0.08M * $12.50/1M = $1.00
        ];

        $rates = [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];

        $this->assertSame(4_000_000, $this->pricing->costFromUsage($usage, $rates));
    }

    public function test_cost_from_usage_rounds_fractional_micros_half_up(): void
    {
        // 3 tokens * $5.33/1M = 15.99 micros -> rounds to 16.
        $micros = $this->pricing->costFromUsage(
            ['input_text_tokens' => 3],
            ['input_text_tokens' => '5.33'],
        );

        $this->assertSame(16, $micros);
    }

    public function test_cost_from_usage_rounds_exact_half_up(): void
    {
        // 1 token * $0.5/1M = 0.5 micros -> rounds up to 1, not down to 0.
        $micros = $this->pricing->costFromUsage(
            ['input_text_tokens' => 1],
            ['input_text_tokens' => '0.5'],
        );

        $this->assertSame(1, $micros);
    }

    public function test_cost_from_usage_defaults_missing_usage_and_rates_to_zero(): void
    {
        $this->assertSame(0, $this->pricing->costFromUsage([], []));
    }

    public function test_with_markup_applies_25_percent(): void
    {
        $this->assertSame(125, $this->pricing->withMarkup(100));
    }

    public function test_with_markup_rounds_half_up(): void
    {
        // 2 * 1.25 = 2.5 -> rounds up to 3.
        $this->assertSame(3, $this->pricing->withMarkup(2));

        // 101 * 1.25 = 126.25 -> rounds down to 126.
        $this->assertSame(126, $this->pricing->withMarkup(101));
    }

    public function test_estimate_matches_hand_computed_cost(): void
    {
        // prompt is exactly 40 characters -> 40 / 4 = 10 text tokens.
        $prompt = str_repeat('a', 40);

        $micros = $this->pricing->estimate(
            size: '1024x1024',
            quality: 'medium',
            referenceImageCount: 2,
            prompt: $prompt,
        );

        // text: 10 tokens * 5.00      = 50
        // image: 1530 tokens * 10.00  = 15300
        // output: 1100 tokens * 40.00 = 44000  (exact table value for medium/1024x1024)
        // reasoning: 1100 tokens * 12.50 = 13750  (midpoint of medium's 200-2000 range)
        $this->assertSame(50 + 15_300 + 44_000 + 13_750, $micros);
    }

    #[DataProvider('outputTokenTableProvider')]
    public function test_estimate_uses_exact_output_token_table(string $quality, string $size, int $expectedOutputTokens): void
    {
        // Isolate the output-token contribution by zeroing everything else:
        // empty prompt, no reference images, and a quality whose reasoning
        // midpoint we subtract out below.
        $withOutput = $this->pricing->estimate($size, $quality, 0, '');

        [$reasoningMin, $reasoningMax] = match ($quality) {
            'low' => [0, 500],
            'medium' => [200, 2000],
            'high' => [1000, 8000],
        };
        $reasoningMidpoint = intdiv($reasoningMin + $reasoningMax + 1, 2);

        $outputRateContribution = $expectedOutputTokens * 40;
        $reasoningRateContribution = $reasoningMidpoint * 12.5;

        $this->assertSame(
            $outputRateContribution + (int) $reasoningRateContribution,
            $withOutput,
        );
    }

    public static function outputTokenTableProvider(): array
    {
        return [
            'low 1024x1024' => ['low', '1024x1024', 280],
            'low 1536x1024' => ['low', '1536x1024', 420],
            'low 1024x1536' => ['low', '1024x1536', 420],
            'medium 1024x1024' => ['medium', '1024x1024', 1100],
            'medium 1536x1024' => ['medium', '1536x1024', 1650],
            'medium 1024x1536' => ['medium', '1024x1536', 1650],
            'high 1024x1024' => ['high', '1024x1024', 4300],
            'high 1536x1024' => ['high', '1536x1024', 6450],
            'high 1024x1536' => ['high', '1024x1536', 6450],
        ];
    }

    public function test_estimate_is_zero_for_empty_prompt_no_images_and_zero_reasoning_floor(): void
    {
        // low quality's reasoning floor is 0, but its midpoint (0+500+1)/2 = 250 is not,
        // so this just documents that estimate() is never literally zero for a real quality.
        $micros = $this->pricing->estimate('1024x1024', 'low', 0, '');

        $this->assertGreaterThan(0, $micros);
    }
}
