<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A signed Pixelforge webhook event, delivered here exactly once per event id.
 */
class PixelforgeWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  The decoded webhook body.
     */
    public function __construct(public array $payload) {}
}
