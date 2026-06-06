<?php

declare(strict_types=1);

use LevelUp\Experience\LevelUpServiceProvider;

it('returns defaults when no prefix or overrides are set', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: '',
        overrides: [],
    );

    expect($resolved)->toBe([
        'experiences' => 'experiences',
        'experience_audits' => 'experience_audits',
        'levels' => 'levels',
        'achievements' => 'achievements',
        'achievement_user' => 'achievement_user',
        'streaks' => 'streaks',
        'streak_histories' => 'streak_histories',
        'streak_activities' => 'streak_activities',
        'tiers' => 'tiers',
        'multipliers' => 'multipliers',
        'multiplier_user' => 'multiplier_user',
        'multiplier_tier' => 'multiplier_tier',
        'challenges' => 'challenges',
        'challenge_user' => 'challenge_user',
        'leaderboard_snapshots' => 'leaderboard_snapshots',
    ]);
});

it('applies the prefix to every default table', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: [],
    );

    expect($resolved['experiences'])->toBe('lvl_experiences')
        ->and($resolved['levels'])->toBe('lvl_levels')
        ->and($resolved['streak_activities'])->toBe('lvl_streak_activities')
        ->and($resolved['multiplier_user'])->toBe('lvl_multiplier_user')
        ->and($resolved['multiplier_tier'])->toBe('lvl_multiplier_tier')
        ->and($resolved['challenge_user'])->toBe('lvl_challenge_user');
});

it('uses an explicit override verbatim, ignoring the prefix', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: ['experiences' => 'xp_log', 'tiers' => 'rank_brackets'],
    );

    expect($resolved['experiences'])->toBe('xp_log')
        ->and($resolved['tiers'])->toBe('rank_brackets')
        ->and($resolved['levels'])->toBe('lvl_levels');
});

it('treats an empty-string override as unset and falls through to the prefix', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: ['experiences' => '', 'tiers' => ''],
    );

    expect($resolved['experiences'])->toBe('lvl_experiences')
        ->and($resolved['tiers'])->toBe('lvl_tiers');
});
