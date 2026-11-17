<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// "a cat", medium, 1024x1024: (3 text × $5 + 1,100 output × $40 + 2,000 reasoning × $12.50) / 1M = $0.069015, plus 25%.
const A_CAT_ESTIMATE = 86_268_750;

beforeEach(function () {
    $this->user = User::factory()->create(['balance' => 1_000_000_000]);
});

function fakePixelforge(mixed $createResponse): void
{
    Http::fake([
        '*/pricing' => Http::response(pixelforgePricing()),
        '*/generations' => $createResponse,
    ]);
}

test('submitting reserves the estimated price and sends an idempotent request', function () {
    fakePixelforge(Http::response(pixelforgeGeneration(), 202));

    $this->actingAs($this->user)
        ->post(route('generations.store'), ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertRedirect(route('generations.index'));

    $generation = Generation::sole();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_123')
        ->and($generation->estimated_cost)->toBe(A_CAT_ESTIMATE)
        ->and($this->user->fresh()->balance)->toBe(1_000_000_000 - A_CAT_ESTIMATE);

    Http::assertSent(fn (Request $request) => $request->url() === config('services.pixelforge.url').'/generations'
        && $request->header('Idempotency-Key') === [$generation->id]
        && $request['metadata'] === ['generation_id' => $generation->id]);
});

test('users who cannot afford the estimate are blocked', function () {
    fakePixelforge(Http::response(pixelforgeGeneration(), 202));
    $this->user->forceFill(['balance' => A_CAT_ESTIMATE - 1])->save();

    $this->actingAs($this->user)
        ->post(route('generations.store'), ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertSessionHasErrors('prompt');

    expect(Generation::count())->toBe(0)
        ->and($this->user->fresh()->balance)->toBe(A_CAT_ESTIMATE - 1);
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/generations'));
});

test('a request pixelforge rejects is marked failed and refunded', function () {
    fakePixelforge(Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']], 400));

    $this->actingAs($this->user)
        ->post(route('generations.store'), ['prompt' => 'a cat [blocked]', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertSessionHasErrors(['prompt' => 'The prompt was rejected.']);

    expect(Generation::sole()->status)->toBe(GenerationStatus::Failed)
        ->and($this->user->fresh()->balance)->toBe(1_000_000_000);
});

test('viewing history settles pending generations whose webhook never arrived', function () {
    Http::fake(['*/generations/gen_123' => Http::response(succeededPixelforgeGeneration())]);
    $generation = Generation::factory()->for($this->user)->create(['pixelforge_id' => 'gen_123']);

    $this->actingAs($this->user)->get(route('generations.index'))->assertOk();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->charged_cost)->toBe(62_500_000);
});
