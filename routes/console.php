<?php

use App\Models\Generation;
use App\Services\Pixelforge;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pixelforge:sync', function (Pixelforge $pixelforge) {
    $stuckGenerations = Generation::query()
        ->pending()
        ->where('updated_at', '<', now()->subMinutes(2))
        ->get();

    foreach ($stuckGenerations as $generation) {
        rescue(fn () => $generation->syncWith($generation->pixelforge_id
            ? $pixelforge->generation($generation->pixelforge_id)
            // Never confirmed as created: resubmitting with the same Idempotency-Key returns it or creates it once.
            : $pixelforge->createGeneration($generation)));
    }
})->purpose('Poll Pixelforge for generations whose webhooks never arrived');

Schedule::command('pixelforge:sync')->everyMinute()->withoutOverlapping();
