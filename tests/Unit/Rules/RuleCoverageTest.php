<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Rules\DanglingForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateIndexRule;
use Chr15k\SchemaAudit\Rules\MismatchedForeignKeyRule;
use Chr15k\SchemaAudit\Rules\NoPrimaryKeyRule;
use Chr15k\SchemaAudit\Rules\RedundantSingleColumnIndexRule;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\TableSchema;
use Chr15k\SchemaAudit\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Index;

it('reports a missing primary key', function (): void {
    $table = new TableSchema('photos');
    $table->addColumn('path', 'string');

    $findings = (new NoPrimaryKeyRule)->check(['photos' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('no_primary_key');
    expect($findings[0]->table)->toBe('photos');
});

it('detects a duplicate index on the same exact columns', function (): void {
    $table = new TableSchema('users');
    $table->addIndex(new Index(name: 'users_email_index', columns: ['email']));
    $table->addIndex(new Index(name: 'users_email_duplicate_index', columns: ['email']));

    $findings = (new DuplicateIndexRule)->check(['users' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('duplicate_index');
    expect($findings[0]->table)->toBe('users');
});

it('flags a redundant single-column index when a composite index covers the same leading column', function (): void {
    $table = new TableSchema('orders');
    $table->addIndex(new Index(name: 'orders_user_id_index', columns: ['user_id']));
    $table->addIndex(new Index(name: 'orders_user_id_created_at_index', columns: ['user_id', 'created_at']));

    $findings = (new RedundantSingleColumnIndexRule)->check(['orders' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('redundant_single_column_index');
    expect($findings[0]->table)->toBe('orders');
});

it('finds duplicate foreign keys defined on the same column', function (): void {
    $table = new TableSchema('comments');
    $table->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts'));
    $table->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts', name: 'comments_post_id_foreign_duplicate'));

    $findings = (new DuplicateForeignKeyRule)->check(['comments' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('duplicate_foreign_key');
    expect($findings[0]->table)->toBe('comments');
});

it('detects a dangling foreign key when the referenced table does not exist', function (): void {
    $table = new TableSchema('transactions');
    $table->addColumn('user_id', 'foreignId');
    $table->addForeignKey(new ForeignKey(column: 'user_id', referencesTable: 'users'));

    $findings = (new DanglingForeignKeyRule)->check(['transactions' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('dangling_foreign_key');
    expect($findings[0]->table)->toBe('transactions');
});

it('reports a mismatched foreign key type against the referenced table primary key', function (): void {
    $users = new TableSchema('users');
    $users->addColumn('id', 'id');

    $posts = new TableSchema('posts');
    $posts->addColumn('user_id', 'unsignedInteger');
    $posts->addForeignKey(new ForeignKey(column: 'user_id', referencesTable: 'users'));

    $findings = (new MismatchedForeignKeyRule)->check(['users' => $users, 'posts' => $posts]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('mismatched_foreign_key');
    expect($findings[0]->table)->toBe('posts');
});

it('flags an unindexed foreign key when the driver does not auto-index foreign keys', function (): void {
    $table = new TableSchema('orders');
    $table->addColumn('customer_id', 'foreignId');
    $table->addForeignKey(new ForeignKey(column: 'customer_id', referencesTable: 'customers'));

    $findings = (new UnindexedForeignKeyRule(driver: 'sqlite'))->check(['orders' => $table]);

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('unindexed_foreign_key');
    expect($findings[0]->table)->toBe('orders');
});
