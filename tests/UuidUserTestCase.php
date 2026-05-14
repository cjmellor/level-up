<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests;

use LevelUp\Experience\Tests\Fixtures\UuidUser;

class UuidUserTestCase extends TestCase
{
    protected function defineUserConfig(): void
    {
        config()->set('level-up.user.model', UuidUser::class);
        config()->set('level-up.user.foreign_key_type', 'uuid');
    }
}
