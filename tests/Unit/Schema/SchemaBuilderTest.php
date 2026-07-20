<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\SchemaBuilder;

it('folds create and later alter migrations for the same table', function (): void {
    $tables = app(SchemaBuilder::class)->buildFromDirectory(migrations_path());

    expect($tables)->toHaveKey('users');

    $users = $tables['users'];

    // Columns from the original create() are present.
    expect($users->hasColumn('name'))->toBeTrue();
    expect($users->hasColumn('email'))->toBeTrue();
    expect($users->hasColumn('team_id'))->toBeTrue();

    // nickname was added in migration 2, then dropped in migration 3 —
    // folded state should NOT contain it. This is the core "folding
    // across files in order" behaviour the whole tool depends on.
    expect($users->hasColumn('nickname'))->toBeFalse();

    // email had no index at create time, but got one in migration 2.
    expect($users->isIndexed('email'))->toBeTrue();

    // name was never indexed anywhere.
    expect($users->isIndexed('name'))->toBeFalse();

    // team_id is a foreign key but Laravel's foreignId()->constrained()
    // alone does not create an index — confirm we don't claim it does.
    expect($users->isIndexed('team_id'))->toBeFalse();
    expect($users->foreignKeys())->toHaveCount(1);
    expect($users->foreignKeys()[0]->column)->toBe('team_id');
});
