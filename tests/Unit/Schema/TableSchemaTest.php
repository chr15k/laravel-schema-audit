<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('renames columns and keeps indexes and foreign keys in sync', function (): void {
    $table = TableSchema::make('posts');
    $table->addColumn(new Column(name: 'author_id', method: ColumnMethod::ForeignId));
    $table->addIndex(new Index(name: 'posts_author_id_index', columns: ['author_id']));
    $table->addForeignKey(new ForeignKey(
        columns: 'author_id',
        referencesTable: 'users',
        referencesColumn: 'id',
        name: 'posts_author_id_foreign',
    ));

    $table->renameColumn('author_id', 'writer_id');

    expect($table->hasColumn('writer_id'))->toBeTrue()
        ->and($table->hasColumn('author_id'))->toBeFalse()
        ->and($table->hasIndexFor('writer_id'))->toBeTrue()
        ->and($table->foreignKeys()->first()?->columns)->toBe('writer_id');
});

it('renames an index by name', function (): void {
    $table = TableSchema::make('posts');
    $table->addIndex(new Index(name: 'posts_slug_index', columns: ['slug']));

    $table->renameIndex('posts_slug_index', 'posts_slug_lookup');

    expect($table->indexes()->pluck('name')->all())->toBe(['posts_slug_lookup']);
});

it('detects valid referenced keys from primary keys and unique indexes', function (): void {
    $users = TableSchema::make('users');
    $users->setPrimaryKey(new PrimaryKey(columns: ['id']));
    $users->addIndex(new Index(name: 'users_uuid_unique', columns: ['uuid'], unique: true));

    expect($users->hasValidReferencedKey('id'))->toBeTrue()
        ->and($users->hasValidReferencedKey(['uuid']))->toBeTrue()
        ->and($users->hasValidReferencedKey('email'))->toBeFalse();
});

it('carries schema state forward when a table is renamed', function (): void {
    $users = TableSchema::make('users');
    $users->addColumn(new Column(name: 'email', method: ColumnMethod::String));
    $users->addIndex(new Index(name: 'users_email_unique', columns: ['email'], unique: true));
    $users->setPrimaryKey(new PrimaryKey(columns: ['id']));

    $members = $users->renamedTo('members');

    expect($members->name)->toBe('members')
        ->and($members->hasColumn('email'))->toBeTrue()
        ->and($members->hasPrimaryKey())->toBeTrue()
        ->and($members->indexes())->toHaveCount(1);
});

it('finds dangling foreign keys against the folded schema', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(
        columns: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign',
    ));

    $schema = new Schema(['orders' => $orders]);

    expect($orders->danglingForeignKeys($schema))->toHaveCount(1);
});

it('serialises table schema state', function (): void {
    $location = new SourceLocation('/path/to/migration.php', 4);

    $table = TableSchema::make('users', location: $location, guard: SchemaGuard::Unknown);
    $table->addColumn(new Column(name: 'email', method: ColumnMethod::String));

    expect($table->toArray()['table'])->toBe('users')
        ->and($table->jsonSerialize())->toEqual($table->toArray());
});
