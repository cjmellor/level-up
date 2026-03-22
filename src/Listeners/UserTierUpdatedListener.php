<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Events\UserTierUpdated;

class UserTierUpdatedListener
{
    public function __invoke(UserTierUpdated $event): void
    {
        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create(attributes: [
                'points' => $event->user->getPoints(),
                'type' => $event->direction === TierDirection::Promoted
                    ? AuditType::TierUp->value
                    : AuditType::TierDown->value,
                'reason' => sprintf('%s → %s', $event->previousTier?->name ?? 'None', $event->newTier?->name ?? 'None'),
            ]);
        }
    }
}
