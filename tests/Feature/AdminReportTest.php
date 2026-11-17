<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('only admins can see the report', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.report'))
        ->assertForbidden();
});

test('the report shows spend, provider cost and pixelforge usage per day and user', function () {
    Http::fake(['*/usage*' => Http::response(['data' => [['date' => now()->toDateString(), 'cost_usd' => '0.050000000']]])]);
    Generation::factory()->count(2)->create([
        'status' => GenerationStatus::Succeeded,
        'provider_cost' => 25_000_000,
        'charged_cost' => 31_250_000,
        'completed_at' => now(),
    ]);
    Generation::factory()->create(['status' => GenerationStatus::Failed, 'provider_cost' => 0, 'charged_cost' => 0, 'completed_at' => now()]);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.report'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Report')
            ->has('days', 1)
            ->where('days.0.charged', 62_500_000)
            ->where('days.0.provider_cost', 50_000_000)
            ->where('days.0.pixelforge_reported_cost', 50_000_000)
            ->has('days.0.users', 2));
});
