<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pixelforge-Signature is "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">".
 */
class VerifyPixelforgeSignature
{
    private const TOLERANCE_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        preg_match('/t=(\d+),v1=([0-9a-f]+)/', (string) $request->header('Pixelforge-Signature'), $matches);
        [, $timestamp, $signature] = $matches + [null, '0', ''];

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", (string) config('services.pixelforge.webhook_secret'));

        $isValid = hash_equals($expected, $signature)
            && abs(time() - (int) $timestamp) <= self::TOLERANCE_SECONDS;

        abort_unless($isValid, Response::HTTP_BAD_REQUEST, 'Invalid Pixelforge signature.');

        return $next($request);
    }
}
