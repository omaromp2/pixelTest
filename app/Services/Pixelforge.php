<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the Pixelforge API. Every method throws on a failed response.
 *
 * @see https://pixelforge.promptedit.dev/docs
 */
class Pixelforge
{
    /**
     * Current token rates and counts. Public and rarely changes, so a short cache is enough.
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        return Cache::remember('pixelforge.pricing', now()->addMinutes(10), fn () => $this->client()->get('pricing')->json());
    }

    /**
     * Submit a generation. The local id is the Idempotency-Key, so retries (here or later) never create a duplicate,
     * and it is sent as metadata so webhooks can be matched even if they arrive before this request returns.
     *
     * @return array<string, mixed>
     */
    public function createGeneration(Generation $generation): array
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $generation->id])
            ->retry(3, $this->retryDelay(...), $this->isTransient(...))
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ])
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function generation(string $pixelforgeId): array
    {
        return $this->client()->get("generations/{$pixelforgeId}")->json();
    }

    /**
     * What Pixelforge billed this API key per UTC day (dates are Y-m-d, inclusive).
     *
     * @return array<string, mixed>
     */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->client()->get('usage', ['start_date' => $startDate, 'end_date' => $endDate])->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.pixelforge.url'))
            ->withToken(config('services.pixelforge.key'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->throw();
    }

    /**
     * Network errors and "overloaded" are worth retrying; the Idempotency-Key makes the retry safe.
     */
    private function isTransient(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && $exception->response->status() === 503);
    }

    private function retryDelay(int $attempt, Throwable $exception): int
    {
        $retryAfterSeconds = $exception instanceof RequestException ? (int) $exception->response->header('Retry-After') : 0;

        return max($retryAfterSeconds, $attempt) * 1000;
    }
}
