<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\UserLevelledUp;

class UserLevelledUpListener
{
    public function __invoke(UserLevelledUp $event): void
    {
        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create(attributes: [
                'user_id' => $event->user->id,
                'points' => $event->user->getPoints(),
                'levelled_up' => true,
                'level_to' => $event->level,
                'type' => AuditType::LevelUp->value,
            ]);
            $event->user->experience->update(attributes: [
                'level_id' => $event->level,
            ]);
        }
    }
}
