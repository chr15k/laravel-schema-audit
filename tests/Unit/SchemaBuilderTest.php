<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\SchemaBuilder;
use Chr15k\SchemaAudit\ValueObjects\Index;

it('folds create and later alter migrations for the same table', function (): void {
    $tables = app(SchemaBuilder::class)->buildFromDirectory(migrations_path());

    expect($tables)->toHaveKey('users');
    expect($tables)->toHaveCount(1);

    $users = $tables['users'];

    expect($users->hasPrimaryKey())->toBeTrue();

    expect($users->hasColumn('name'))->toBeTrue();
    expect($users->hasColumn('email'))->toBeTrue();
    expect($users->hasColumn('team_id'))->toBeTrue();

    expect($users->hasColumn('nickname'))->toBeFalse();

    expect($users->isIndexed('email'))->toBeTrue();
    expect($users->indexes())->toHaveCount(1);
    expect(collect($users->indexes())->contains(fn (Index $index): bool => $index->columns === ['email'] && $index->unique))->toBeTrue();

    expect($users->isIndexed('name'))->toBeFalse();
    expect($users->isIndexed('team_id'))->toBeFalse();

    expect($users->foreignKeys())->toHaveCount(1);
    expect($users->foreignKeys()[0]->column)->toBe('team_id');
    expect($users->foreignKeys()[0]->referencesTable)->toBeNull();
});

it('returns an empty result set when no migration files exist', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('empty_', true);
    mkdir($path, 0700, true);

    try {
        $tables = app(SchemaBuilder::class)->buildFromDirectory($path);

        expect($tables)->toBeArray();
        expect($tables)->toBeEmpty();
    } finally {
        rmdir($path);
    }
});
