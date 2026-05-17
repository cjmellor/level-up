<?php

declare(strict_types=1);

use LevelUp\Experience\Tests\Fixtures\ConfiguredTableModel;

it('sets the table from config at instance init', function (): void {
    config()->set('level-up.tables.experiences', 'resolved_xp_table');

    $model = new ConfiguredTableModel;

    expect($model->getTable())->toBe('resolved_xp_table');
});

it('reflects later changes to the config when a new instance is built', function (): void {
    config()->set('level-up.tables.experiences', 'first');
    $first = new ConfiguredTableModel;

    config()->set('level-up.tables.experiences', 'second');
    $second = new ConfiguredTableModel;

    expect($first->getTable())->toBe('first')
        ->and($second->getTable())->toBe('second');
});
