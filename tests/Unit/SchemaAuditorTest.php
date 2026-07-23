<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Rules\DanglingForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateIndexRule;
use Chr15k\SchemaAudit\Rules\MismatchedForeignKeyRule;
use Chr15k\SchemaAudit\Rules\NoPrimaryKeyRule;
use Chr15k\SchemaAudit\Rules\RedundantSingleColumnIndexRule;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\SchemaAuditor;

it('runs the full schema auditor rule set and returns every configured rule once', function (): void {
    $users = new TableSchema('users');
    $users->addColumn(new Column('id', ColumnMethod::Id));

    $posts = new TableSchema('posts');
    $posts->addColumn(new Column('id', ColumnMethod::Id));
    $posts->addColumn(new Column('user_id', ColumnMethod::UnsignedInteger));
    $posts->addForeignKey(new ForeignKey(column: 'user_id', referencesTable: 'users'));
    $posts->addIndex(new Index(name: 'posts_user_id_index', columns: ['user_id']));

    $comments = new TableSchema('comments');
    $comments->addColumn(new Column('id', ColumnMethod::Id));
    $comments->addColumn(new Column('post_id', ColumnMethod::ForeignId));
    $comments->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts'));
    $comments->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts', name: 'comments_post_id_foreign_duplicate'));
    $comments->addIndex(new Index(name: 'comments_post_id_index', columns: ['post_id']));
    $comments->addIndex(new Index(name: 'comments_post_id_duplicate_index', columns: ['post_id']));
    $comments->addIndex(new Index(name: 'comments_post_id_created_at_index', columns: ['post_id', 'created_at']));

    $orders = new TableSchema('orders');
    $orders->addColumn(new Column('id', ColumnMethod::Id));

    $payments = new TableSchema('payments');
    $payments->addColumn(new Column('id', ColumnMethod::Id));
    $payments->addColumn(new Column('order_id', ColumnMethod::ForeignId));
    $payments->addForeignKey(new ForeignKey(column: 'order_id', referencesTable: 'orders'));

    $tables = [
        'users'    => $users,
        'posts'    => $posts,
        'comments' => $comments,
        'orders'   => $orders,
        'payments' => $payments,
    ];

    $auditor = new SchemaAuditor([
        new DanglingForeignKeyRule,
        new DuplicateForeignKeyRule,
        new DuplicateIndexRule,
        new MismatchedForeignKeyRule,
        new NoPrimaryKeyRule,
        new RedundantSingleColumnIndexRule,
        new UnindexedForeignKeyRule(driver: 'sqlite'),
    ]);

    $audit = $auditor->audit(new Schema($tables));

    $rules = collect($audit->findings)->pluck('code')->unique()->values()->all();

    expect($rules)->toContain('duplicate_foreign_key')
        ->and($rules)->toContain('duplicate_index')
        ->and($rules)->toContain('mismatched_foreign_key')
        ->and($rules)->toContain('redundant_single_column_index')
        ->and($rules)->toContain('unindexed_foreign_key');

    expect($audit->findings)->not->toContain(fn ($finding): bool => $finding->rule === 'dangling_foreign_key');
    expect($audit->findings)->not->toContain(fn ($finding): bool => $finding->rule === 'no_primary_key');
});
