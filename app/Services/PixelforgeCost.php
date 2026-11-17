<?php

namespace App\Services;

/**
 * Pixelforge pricing math. All amounts are integer nanodollars (1 USD = 1,000,000,000).
 */
class PixelforgeCost
{
    public const MARKUP = 1.25;

    /**
     * The most a generation can cost us, assuming the most text and reasoning tokens Pixelforge allows.
     * Using the upper bound means the amount reserved from the user's balance always covers the real price.
     *
     * @param  array<string, mixed>  $pricing  The response of GET /v1/pricing.
     */
    public static function estimate(array $pricing, string $prompt, string $size, string $quality, int $referenceImageCount): int
    {
        $tokens = $pricing['tokens'];
        $textTokens = (int) ceil(mb_strlen($prompt) / $tokens['characters_per_text_token']);

        return self::fromUsage([
            'input_text_tokens' => (int) ceil($textTokens * (1 + $tokens['text_token_variance'])),
            'input_image_tokens' => $referenceImageCount * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality][$size],
            'reasoning_tokens' => $tokens['reasoning'][$quality]['max'],
        ], $pricing['rates']);
    }

    /**
     * What Pixelforge charges for the given token usage.
     *
     * @param  array<string, int>  $usage
     * @param  array<string, string>  $rates  US dollars per 1M tokens, e.g. ['output_tokens' => '40.00'].
     */
    public static function fromUsage(array $usage, array $rates): int
    {
        $total = 0;

        foreach ($rates as $tokenType => $dollarsPerMillionTokens) {
            $total += $usage[$tokenType] * self::toNanodollars($dollarsPerMillionTokens);
        }

        return intdiv($total, 1_000_000);
    }

    /**
     * What the user pays: our cost plus markup, rounded up to the next nanodollar.
     */
    public static function withMarkup(int $providerCost): int
    {
        return (int) ceil($providerCost * self::MARKUP);
    }

    /**
     * Parse an exact decimal string such as "0.046125000" without going through floats.
     */
    public static function toNanodollars(string $dollars): int
    {
        [$whole, $fraction] = array_pad(explode('.', $dollars, 2), 2, '');

        return (int) $whole * 1_000_000_000 + (int) str_pad(substr($fraction, 0, 9), 9, '0');
    }
}
