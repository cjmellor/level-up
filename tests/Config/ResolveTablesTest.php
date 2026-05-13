<?php

declare(strict_types=1);

use LevelUp\Experience\LevelUpServiceProvider;

it('returns defaults when no prefix or overrides are set', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: '',
        overrides: [],
        legacyName: null,
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
        'multiplier_scopes' => 'multiplier_scopes',
        'challenges' => 'challenges',
        'challenge_user' => 'challenge_user',
    ]);
});

it('applies the prefix to every default table', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: [],
        legacyName: null,
    );

    expect($resolved['experiences'])->toBe('lvl_experiences')
        ->and($resolved['levels'])->toBe('lvl_levels')
        ->and($resolved['streak_activities'])->toBe('lvl_streak_activities')
        ->and($resolved['multiplier_scopes'])->toBe('lvl_multiplier_scopes')
        ->and($resolved['challenge_user'])->toBe('lvl_challenge_user');
});

it('uses an explicit override verbatim, ignoring the prefix', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: ['experiences' => 'xp_log', 'tiers' => 'rank_brackets'],
        legacyName: null,
    );

    expect($resolved['experiences'])->toBe('xp_log')
        ->and($resolved['tiers'])->toBe('rank_brackets')
        ->and($resolved['levels'])->toBe('lvl_levels');
});

it('falls back to the legacy table key for experiences when it differs from the default', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: '',
        overrides: [],
        legacyName: 'custom_xp',
    );

    expect($resolved['experiences'])->toBe('custom_xp');
});

it('ignores the legacy table key when it equals the default', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: [],
        legacyName: 'experiences',
    );

    expect($resolved['experiences'])->toBe('lvl_experiences');
});

it('prefers tables.experiences over the legacy table key when both are set', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: '',
        overrides: ['experiences' => 'new_xp'],
        legacyName: 'old_xp',
    );

    expect($resolved['experiences'])->toBe('new_xp');
});

it('treats an empty-string override as unset and falls through to the prefix', function (): void {
    $resolved = LevelUpServiceProvider::resolveTables(
        prefix: 'lvl_',
        overrides: ['experiences' => '', 'tiers' => ''],
        legacyName: '',
    );

    expect($resolved['experiences'])->toBe('lvl_experiences')
        ->and($resolved['tiers'])->toBe('lvl_tiers');
});
