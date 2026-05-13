<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Migrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\TestCase;

class TablePrefixIntegrationTest extends TestCase
{
    public function test_prefixed_tables_exist_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('pfx_experiences'));
        $this->assertTrue(Schema::hasTable('pfx_experience_audits'));
        $this->assertTrue(Schema::hasTable('pfx_levels'));
        $this->assertTrue(Schema::hasTable('pfx_achievement_user'));
        $this->assertTrue(Schema::hasTable('pfx_streaks'));
        $this->assertTrue(Schema::hasTable('pfx_streak_histories'));
        $this->assertTrue(Schema::hasTable('pfx_streak_activities'));
        $this->assertTrue(Schema::hasTable('pfx_tiers'));
        $this->assertTrue(Schema::hasTable('pfx_multipliers'));
        $this->assertTrue(Schema::hasTable('pfx_multiplier_scopes'));
        $this->assertTrue(Schema::hasTable('pfx_challenges'));
        $this->assertTrue(Schema::hasTable('pfx_challenge_user'));
    }

    public function test_explicit_override_escapes_the_prefix(): void
    {
        $this->assertTrue(Schema::hasTable('custom_achievements'));
        $this->assertFalse(Schema::hasTable('pfx_achievements'));
        $this->assertFalse(Schema::hasTable('pfx_custom_achievements'));
    }

    public function test_models_write_to_prefixed_tables_with_working_foreign_keys(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ])->save();

        Level::add(
            ['level' => 1, 'next_level_experience' => null],
            ['level' => 2, 'next_level_experience' => 100],
        );

        Experience::create([
            'user_id' => $user->id,
            'level_id' => 1,
            'experience_points' => 50,
        ]);

        $this->assertSame(1, DB::table('pfx_experiences')->count());
        $this->assertFalse(Schema::hasTable('experiences'));
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('level-up.table_prefix', 'pfx_');
        $app['config']->set('level-up.tables.achievements', 'custom_achievements');

        $app['config']->set('level-up.tables', \LevelUp\Experience\LevelUpServiceProvider::resolveTables(
            prefix: 'pfx_',
            overrides: $app['config']->get('level-up.tables', []),
            legacyName: $app['config']->get('level-up.table'),
        ));

        parent::getEnvironmentSetUp($app);
    }
}
