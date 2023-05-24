<?php

namespace LevelUp\Experience\Enums;

enum AuditType: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Reset = 'reset';
    case LevelUp = 'level_up';
}
