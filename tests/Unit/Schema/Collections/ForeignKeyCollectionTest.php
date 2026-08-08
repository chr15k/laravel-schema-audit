<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Schema\Collections\ForeignKeyCollection;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;

it('filters foreign keys by name', function (): void {
    $foreignKeys = new ForeignKeyCollection([
        new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id', name: 'a'),
        new ForeignKey(columns: 'editor_id', referencesTable: 'users', referencesColumn: 'id', name: 'b'),
    ]);

    expect($foreignKeys->withoutName('a')->pluck('name')->all())->toBe(['b']);
});

it('renames scalar and composite foreign key columns', function (): void {
    $foreignKeys = new ForeignKeyCollection([
        new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id', name: 'scalar'),
        new ForeignKey(columns: ['tenant_id', 'user_id'], referencesTable: 'users', referencesColumn: ['tenant_id', 'id'], name: 'composite'),
    ]);

    $renamed = $foreignKeys->renameColumn('user_id', 'member_id');

    expect($renamed->firstWhere('name', 'scalar')?->columns)->toBe('member_id')
        ->and($renamed->firstWhere('name', 'composite')?->columns)->toBe(['tenant_id', 'member_id']);
});

it('renames referenced tables', function (): void {
    $foreignKeys = new ForeignKeyCollection([
        new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id', name: 'posts_user_id_foreign'),
    ]);

    $renamed = $foreignKeys->renameReferencedTable('users', 'members');

    expect($renamed->first()?->referencesTable)->toBe('members');
});

it('groups duplicate foreign keys by signature', function (): void {
    $foreignKeys = new ForeignKeyCollection([
        new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id', name: 'a'),
        new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id', name: 'b'),
        new ForeignKey(columns: 'editor_id', referencesTable: 'users', referencesColumn: 'id', name: 'c'),
    ]);

    $groups = $foreignKeys->duplicateGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->fks)->toHaveCount(2);
});
