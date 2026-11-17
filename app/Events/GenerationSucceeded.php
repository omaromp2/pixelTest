<?php

namespace App\Events;

use App\Models\Generation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The generation just moved to succeeded and its actual cost is recorded.
 */
class GenerationSucceeded
{
    use Dispatchable;

    public function __construct(public Generation $generation) {}
}
