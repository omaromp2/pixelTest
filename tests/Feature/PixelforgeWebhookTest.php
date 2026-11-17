<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_test']);

    // The estimate was already reserved from the balance when the generation was submitted.
    $user = User::factory()->create(['balance' => 0]);
    $this->generation = Generation::factory()->for($user)->create(['estimated_cost' => 100_000_000]);
});

function sendWebhook(string $eventId, array $generation, ?string $secret = 'whsec_test'): TestResponse
{
    $body = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'generation.'.$generation['status'],
        'data' => ['object' => $generation],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}

function generationEvent(Generation $generation, array $remote): array
{
    return [...$remote, 'metadata' => ['generation_id' => $generation->id]];
}

test('requests with an invalid signature are rejected', function () {
    sendWebhook('evt_1', generationEvent($this->generation, succeededPixelforgeGeneration()), 'whsec_wrong')
        ->assertBadRequest();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

test('a succeeded event records the actual cost and refunds the rest of the reservation', function () {
    sendWebhook('evt_1', generationEvent($this->generation, succeededPixelforgeGeneration()))->assertNoContent();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost)->toBe(50_000_000)
        ->and($generation->charged_cost)->toBe(62_500_000)
        ->and($generation->user->balance)->toBe(100_000_000 - 62_500_000);
});

test('an event delivered twice is only recorded once', function () {
    $event = generationEvent($this->generation, succeededPixelforgeGeneration());

    sendWebhook('evt_1', $event)->assertNoContent();
    sendWebhook('evt_1', $event)->assertNoContent();

    expect($this->generation->user->fresh()->balance)->toBe(100_000_000 - 62_500_000);
    $this->assertDatabaseCount('webhook_events', 1);
});

test('a late processing event never moves a finished generation backwards', function () {
    sendWebhook('evt_2', generationEvent($this->generation, succeededPixelforgeGeneration()));
    sendWebhook('evt_1', generationEvent($this->generation, pixelforgeGeneration(['status' => 'processing'])));

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

test('a failed event costs nothing and refunds the reservation', function () {
    sendWebhook('evt_1', generationEvent($this->generation, pixelforgeGeneration([
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'Could not draw that.'],
    ])));

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charged_cost)->toBe(0)
        ->and($generation->error_message)->toBe('Could not draw that.')
        ->and($generation->user->balance)->toBe(100_000_000);
});
