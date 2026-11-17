<?php

namespace App\Events;

use App\Models\Generation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The generation was saved and its estimated price reserved from the user's balance; it still has to be sent to Pixelforge.
 */
class GenerationRequested
{
    use Dispatchable;

    public function __construct(public Generation $generation) {}
}
