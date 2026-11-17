<?php

namespace App\Listeners;

use App\Events\GenerationSucceeded;

class ChargeForGeneration
{
    /**
     * The estimate was reserved on submit; keep the actual price and give back the rest.
     */
    public function handle(GenerationSucceeded $event): void
    {
        $generation = $event->generation;

        $generation->user()->increment('balance', $generation->estimated_cost - $generation->charged_cost);
    }
}
