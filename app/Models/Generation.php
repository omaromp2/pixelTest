<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Events\GenerationFailed;
use App\Events\GenerationSucceeded;
use App\Services\PixelforgeCost;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Money columns are integer nanodollars (1 USD = 1,000,000,000) so fractions of a cent stay exact.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property list<string> $reference_images
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $error_message
 * @property int $estimated_cost
 * @property int|null $provider_cost
 * @property int|null $charged_cost
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['status', 'prompt', 'size', 'quality', 'reference_images', 'estimated_cost'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    /**
     * Announce every move to a final status, whichever path saved it (webhook, polling or a rejected submit).
     */
    protected static function booted(): void
    {
        static::updated(function (Generation $generation) {
            if (! $generation->wasChanged('status')) {
                return;
            }

            match ($generation->status) {
                GenerationStatus::Succeeded => GenerationSucceeded::dispatch($generation),
                GenerationStatus::Failed => GenerationFailed::dispatch($generation),
                default => null,
            };
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'image_url_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generations Pixelforge hasn't finished yet.
     *
     * @param  Builder<Generation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    /**
     * Move to the state Pixelforge reports, ignoring anything older than what we already have.
     * Safe to call with the same or out-of-order data any number of times.
     *
     * @param  array<string, mixed>  $remote  A Pixelforge generation object.
     */
    public function syncWith(array $remote): void
    {
        DB::transaction(function () use ($remote) {
            $generation = static::query()->lockForUpdate()->findOrFail($this->id);
            $generation->pixelforge_id ??= $remote['id'];
            $status = GenerationStatus::from($remote['status']);

            if ($status->isAfter($generation->status)) {
                match ($status) {
                    GenerationStatus::Succeeded => $generation->succeed($remote),
                    GenerationStatus::Failed => $generation->fail($remote['error']['message'] ?? 'Generation failed.'),
                    default => $generation->status = $status,
                };
            }

            $generation->save();
        });

        $this->refresh();
    }

    /**
     * Mark as failed; saving it fires GenerationFailed, which refunds the reservation.
     */
    public function fail(string $message): static
    {
        $this->status = GenerationStatus::Failed;
        $this->error_message = $message;
        $this->provider_cost = 0;
        $this->charged_cost = 0;
        $this->completed_at = now();

        return $this;
    }

    /**
     * Record the real cost; saving it fires GenerationSucceeded, which settles the user's balance.
     *
     * @param  array<string, mixed>  $remote
     */
    private function succeed(array $remote): void
    {
        $this->status = GenerationStatus::Succeeded;
        $this->image_url = $remote['image_url'];
        $this->image_url_expires_at = CarbonImmutable::parse($remote['image_url_expires_at']);
        $this->completed_at = CarbonImmutable::parse($remote['completed_at']);
        $this->provider_cost = PixelforgeCost::fromUsage($remote['usage'], $remote['rates']);
        $this->charged_cost = PixelforgeCost::withMarkup($this->provider_cost);
    }
}
