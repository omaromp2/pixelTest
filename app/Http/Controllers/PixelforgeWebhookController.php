<?php

namespace App\Http\Controllers;

use App\Events\PixelforgeWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PixelforgeWebhookController extends Controller
{
    /**
     * Handle a signed Pixelforge event (signature checked by VerifyPixelforgeSignature).
     *
     * Each event id is stored once, in the same transaction as its listeners' effects, so a redelivered event is a
     * no-op and a failed listener rolls the id back for Pixelforge's retry. Listeners must therefore stay synchronous.
     */
    public function __invoke(Request $request): Response
    {
        DB::transaction(function () use ($request) {
            $isFirstDelivery = DB::table('webhook_events')->insertOrIgnore([
                'id' => $request->input('id'),
                'type' => $request->input('type'),
                'created_at' => now(),
            ]) === 1;

            if ($isFirstDelivery) {
                PixelforgeWebhookReceived::dispatch($request->json()->all());
            }
        });

        return response()->noContent();
    }
}
