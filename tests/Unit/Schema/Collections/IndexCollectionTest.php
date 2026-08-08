<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Schema\Collections\IndexCollection;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

it('detects indexes by leading column or exact composite columns', function (): void {
    $indexes = new IndexCollection([
        new Index(name: 'articles_category_id_author_id_index', columns: ['category_id', 'author_id']),
        new Index(name: 'articles_slug_unique', columns: ['slug'], unique: true),
    ]);

    expect($indexes->hasIndexFor('category_id'))->toBeTrue()
        ->and($indexes->hasIndexFor(['category_id', 'author_id']))->toBeTrue()
        ->and($indexes->hasIndexFor('slug'))->toBeTrue()
        ->and($indexes->hasIndexFor('missing'))->toBeFalse()
        ->and($indexes->hasExactColumns(['category_id', 'author_id']))->toBeTrue()
        ->and($indexes->hasExactColumns(['category_id']))->toBeFalse();
});

it('filters and renames indexes by column or name', function (): void {
    $indexes = new IndexCollection([
        new Index(name: 'users_email_index', columns: ['email']),
        new Index(name: 'users_slug_unique', columns: ['slug'], unique: true),
    ]);

    expect($indexes->withoutColumn('email')->pluck('name')->all())
        ->toBe(['users_slug_unique'])
        ->and($indexes->withoutName('users_slug_unique')->pluck('name')->all())
        ->toBe(['users_email_index'])
        ->and($indexes->renameColumn('email', 'mail')->firstWhere('name', 'users_email_index')?->columns)
        ->toBe(['mail'])
        ->and($indexes->rename('users_slug_unique', 'users_slug_lookup')->firstWhere('columns', ['slug'])?->name)
        ->toBe('users_slug_lookup');
});

it('groups duplicate indexes by signature', function (): void {
    $indexes = new IndexCollection([
        new Index(name: 'users_email_index', columns: ['email']),
        new Index(name: 'users_email_index_copy', columns: ['email']),
        new Index(name: 'users_slug_unique', columns: ['slug'], unique: true),
    ]);

    $groups = $indexes->duplicateGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->indexes)->toHaveCount(2);
});

it('finds redundant indexes using left-prefix coverage', function (): void {
    $indexes = new IndexCollection([
        new Index(name: 'articles_category_id_index', columns: ['category_id']),
        new Index(name: 'articles_category_id_author_id_index', columns: ['category_id', 'author_id']),
    ]);

    $redundant = $indexes->redundant();

    expect($redundant)->toHaveCount(1)
        ->and($redundant->first()?->index->name)->toBe('articles_category_id_index')
        ->and($redundant->first()?->coveredBy->name)->toBe('articles_category_id_author_id_index');
});
