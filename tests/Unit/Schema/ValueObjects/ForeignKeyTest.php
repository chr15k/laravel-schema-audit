<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('returns copies with updated guard, columns, and referenced table', function (): void {
    $fk = new ForeignKey(
        columns: 'user_id',
        referencesTable: 'users',
        referencesColumn: 'id',
        name: 'posts_user_id_foreign',
    );

    $guarded = $fk->withGuard(SchemaGuard::Unknown);
    $renamedColumns = $fk->withColumns(['tenant_id', 'user_id']);
    $renamedTable = $fk->withReferencesTable('members');

    expect($guarded->guard)->toBe(SchemaGuard::Unknown)
        ->and($renamedColumns->columns)->toBe(['tenant_id', 'user_id'])
        ->and($renamedTable->referencesTable)->toBe('members')
        ->and($fk->columns)->toBe('user_id')
        ->and($fk->referencesTable)->toBe('users');
});

it('builds a stable signature for scalar and composite foreign keys', function (): void {
    $scalar = new ForeignKey(
        columns: 'user_id',
        referencesTable: 'users',
        referencesColumn: 'id',
    );

    $composite = new ForeignKey(
        columns: ['tenant_id', 'user_id'],
        referencesTable: 'users',
        referencesColumn: ['tenant_id', 'id'],
    );

    expect($scalar->signature())->toBe('user_id|users|id')
        ->and($composite->signature())->toBe('tenant_id_user_id|users|tenant_id_id');
});

it('serialises to an array', function (): void {
    $location = new SourceLocation('/path/to/migration.php', 20);

    $fk = new ForeignKey(
        columns: 'user_id',
        referencesTable: 'users',
        referencesColumn: 'id',
        name: 'posts_user_id_foreign',
        location: $location,
    );

    expect($fk->toArray())->toBe([
        'columns'           => 'user_id',
        'references_table'  => 'users',
        'references_column' => 'id',
        'name'              => 'posts_user_id_foreign',
        'location'          => $location,
    ])->and($fk->jsonSerialize())->toBe($fk->toArray());
});
