<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('returns a copy with an updated guard', function (): void {
    $index = new Index(name: 'users_email_index', columns: ['email']);

    $guarded = $index->withGuard(SchemaGuard::Unknown);

    expect($guarded->guard)->toBe(SchemaGuard::Unknown)
        ->and($index->guard)->toBeNull();
});

it('returns a copy with an updated name', function (): void {
    $index = new Index(name: 'users_email_index', columns: ['email']);

    $renamed = $index->withName('users_email_lookup');

    expect($renamed->name)->toBe('users_email_lookup')
        ->and($index->name)->toBe('users_email_index');
});

it('renames a column within a composite index', function (): void {
    $index = new Index(name: 'articles_a_b_index', columns: ['a', 'b']);

    $renamed = $index->renameColumn('a', 'alpha');

    expect($renamed->columns)->toBe(['alpha', 'b'])
        ->and($index->columns)->toBe(['a', 'b']);
});

it('does not treat an index as covered by itself', function (): void {
    $index = new Index(name: 'users_email_index', columns: ['email']);

    expect($index->isCoveredBy($index))->toBeFalse();
});

it('serialises to an array with a stable signature', function (): void {
    $location = new SourceLocation('/path/to/migration.php', 12);

    $index = new Index(
        name: 'users_email_unique',
        columns: ['email'],
        unique: true,
        location: $location,
        guard: SchemaGuard::Unknown,
    );

    expect($index->signature())->toBe('email|unique')
        ->and($index->toArray())->toBe([
            'name'      => 'users_email_unique',
            'columns'   => ['email'],
            'unique'    => true,
            'signature' => 'email|unique',
            'location'  => '/path/to/migration.php:12',
            'guard'     => 'Unknown',
        ])
        ->and($index->jsonSerialize())->toBe($index->toArray());
});
