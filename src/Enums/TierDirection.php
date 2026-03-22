<?php

declare(strict_types=1);

namespace LevelUp\Experience\Enums;

enum TierDirection: string
{
    case Promoted = 'promoted';
    case Demoted = 'demoted';
}
