<?php

namespace App\Listeners;

use App\Events\GenerationRequested;
use App\Services\Pixelforge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Runs synchronously on purpose: a rejection has to reach the user as a form error on the same request.
 */
class SubmitGenerationToPixelforge
{
    public function __construct(private Pixelforge $pixelforge) {}

    public function handle(GenerationRequested $event): void
    {
        $generation = $event->generation;

        try {
            $generation->syncWith($this->pixelforge->createGeneration($generation));
        } catch (RequestException $exception) {
            // Pixelforge answered with an error, so nothing was created or billed.
            $message = $exception->response->json('error.message', 'Pixelforge rejected the request.');
            DB::transaction(fn () => $generation->fail($message)->save());

            throw ValidationException::withMessages(['prompt' => $message]);
        } catch (ConnectionException) {
            // It may or may not have been created; pixelforge:sync resubmits it with the same Idempotency-Key.
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Pixelforge is slow to respond. We will keep trying.')]);
        }
    }
}
