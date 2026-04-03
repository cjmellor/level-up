<?php

declare(strict_types=1);

namespace LevelUp\Experience\Enums;

enum AuditType: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Reset = 'reset';
    case LevelUp = 'level_up';
    case TierUp = 'tier_up';
    case TierDown = 'tier_down';
}
