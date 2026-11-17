<?php

namespace App\Listeners;

use App\Events\GenerationFailed;

class RefundGeneration
{
    /**
     * Failed generations cost nothing: return the whole amount reserved on submit.
     */
    public function handle(GenerationFailed $event): void
    {
        $generation = $event->generation;

        $generation->user()->increment('balance', $generation->estimated_cost);
    }
}
