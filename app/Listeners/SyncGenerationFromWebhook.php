<?php

namespace App\Listeners;

use App\Events\PixelforgeWebhookReceived;
use App\Models\Generation;

class SyncGenerationFromWebhook
{
    /**
     * Apply a generation.* event to our record. Out-of-order events are safe: Generation::syncWith never moves backwards.
     */
    public function handle(PixelforgeWebhookReceived $event): void
    {
        $remote = $event->payload['data']['object'] ?? null;
        $generationId = $remote['metadata']['generation_id'] ?? null;

        if (! $generationId || ! str_starts_with($event->payload['type'] ?? '', 'generation.')) {
            return;
        }

        Generation::query()->whereKey($generationId)->first()?->syncWith($remote);
    }
}
