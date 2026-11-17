<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Whether this status comes later in the lifecycle, so late or replayed events never move a generation backwards.
     */
    public function isAfter(self $other): bool
    {
        return $this->step() > $other->step();
    }

    private function step(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }
}
