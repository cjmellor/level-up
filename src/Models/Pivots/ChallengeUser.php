<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChallengeUser extends Pivot
{
    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function getDecodedProgress(): ?array
    {
        if ($this->progress === null) {
            return null;
        }

        return is_string($this->progress)
            ? json_decode(json: $this->progress, associative: true)
            : $this->progress;
    }
}
