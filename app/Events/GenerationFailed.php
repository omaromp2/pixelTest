<?php

namespace App\Events;

use App\Models\Generation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The generation just moved to failed (rejected on submit or failed while processing).
 */
class GenerationFailed
{
    use Dispatchable;

    public function __construct(public Generation $generation) {}
}
