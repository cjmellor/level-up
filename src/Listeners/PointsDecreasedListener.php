<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\PointsDecreased;

class PointsDecreasedListener
{
    public function __invoke(PointsDecreased $event): void
    {
        if ($event->pointsDecreasedBy === 0) {
            return;
        }

        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create(attributes: [
                'points' => $event->pointsDecreasedBy,
                'type' => AuditType::Remove->value,
                'reason' => $event->reason,
            ]);
        }
    }
}
