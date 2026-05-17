<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests;

use LevelUp\Experience\Tests\Fixtures\UlidUser;

class UlidUserTestCase extends TestCase
{
    protected function defineUserConfig(): void
    {
        config()->set('level-up.user.model', UlidUser::class);
        config()->set('level-up.user.foreign_key_type', 'ulid');
    }
}
