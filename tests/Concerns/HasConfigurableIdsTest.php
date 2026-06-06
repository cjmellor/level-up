<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LevelUp\Experience\Models\Achievement;

uses()->group('entities');

test(description: 'entities use auto-incrementing integer keys for the bigint id type', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'bigint');

    $achievement = new Achievement;

    expect($achievement->getKeyType())->toBe(expected: 'int')
        ->and($achievement->getIncrementing())->toBeTrue()
        ->and($achievement->uniqueIds())->toBe(expected: []);
});

test(description: 'entities generate UUID keys when configured', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'uuid');

    $achievement = new Achievement;

    expect($achievement->getKeyType())->toBe(expected: 'string')
        ->and($achievement->getIncrementing())->toBeFalse()
        ->and($achievement->uniqueIds())->toBe(expected: ['id'])
        ->and(Str::isUuid($achievement->newUniqueId()))->toBeTrue();
});

test(description: 'entities generate ULID keys when configured', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'ulid');

    $achievement = new Achievement;

    expect(Str::isUlid($achievement->newUniqueId()))->toBeTrue()
        ->and($achievement->uniqueIds())->toBe(expected: ['id']);
});

test(description: 'generating a key for an unknown id type throws an exception', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'nano');

    (new Achievement)->newUniqueId();
})->throws(exception: InvalidArgumentException::class, exceptionMessage: 'Unknown level-up.entities.id_type [nano]');

test(description: 'the entity key macros create UUID columns when configured', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'uuid');

    Schema::create('uuid_macro_test', callback: function (Blueprint $table): void {
        $table->entityId();
        $table->entityForeignId(column: 'related_id');
    });

    expect(Schema::hasColumns('uuid_macro_test', ['id', 'related_id']))->toBeTrue();

    Schema::drop('uuid_macro_test');
});

test(description: 'the entity key macros create ULID columns when configured', closure: function (): void {
    config()->set(key: 'level-up.entities.id_type', value: 'ulid');

    Schema::create('ulid_macro_test', callback: function (Blueprint $table): void {
        $table->entityId();
        $table->entityForeignId(column: 'related_id');
    });

    expect(Schema::hasColumns('ulid_macro_test', ['id', 'related_id']))->toBeTrue();

    Schema::drop('ulid_macro_test');
});
