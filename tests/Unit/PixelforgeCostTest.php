<?php

use App\Services\PixelforgeCost;

test('estimate matches the most a generation can cost in the pixelforge docs', function () {
    $estimate = PixelforgeCost::estimate(pixelforgePricing(), str_repeat('a', 400), '1536x1024', 'high', 2);

    expect($estimate)->toBe(373_875_000);
});

test('provider cost is exact to the nanodollar', function () {
    $cost = PixelforgeCost::fromUsage(
        ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
        pixelforgePricing()['rates'],
    );

    expect($cost)->toBe(PixelforgeCost::toNanodollars('0.169080000'));
});

test('markup is 25 percent rounded up to the next nanodollar', function () {
    expect(PixelforgeCost::withMarkup(400))->toBe(500)
        ->and(PixelforgeCost::withMarkup(1))->toBe(2);
});
