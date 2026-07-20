<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Rules\DanglingForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateIndexRule;
use Chr15k\SchemaAudit\Rules\MismatchedForeignKeyRule;
use Chr15k\SchemaAudit\Rules\NoPrimaryKeyRule;
use Chr15k\SchemaAudit\Rules\RedundantSingleColumnIndexRule;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\TableSchema;
use Chr15k\SchemaAudit\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Index;

it('runs the full schema auditor rule set and returns every configured rule once', function (): void {
    $users = new TableSchema('users');
    $users->addColumn('id', 'id');

    $posts = new TableSchema('posts');
    $posts->addColumn('id', 'id');
    $posts->addColumn('user_id', 'unsignedInteger');
    $posts->addForeignKey(new ForeignKey(column: 'user_id', referencesTable: 'users'));
    $posts->addIndex(new Index(name: 'posts_user_id_index', columns: ['user_id']));

    $comments = new TableSchema('comments');
    $comments->addColumn('id', 'id');
    $comments->addColumn('post_id', 'foreignId');
    $comments->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts'));
    $comments->addForeignKey(new ForeignKey(column: 'post_id', referencesTable: 'posts', name: 'comments_post_id_foreign_duplicate'));
    $comments->addIndex(new Index(name: 'comments_post_id_index', columns: ['post_id']));
    $comments->addIndex(new Index(name: 'comments_post_id_duplicate_index', columns: ['post_id']));
    $comments->addIndex(new Index(name: 'comments_post_id_created_at_index', columns: ['post_id', 'created_at']));

    $orders = new TableSchema('orders');
    $orders->addColumn('id', 'id');

    $payments = new TableSchema('payments');
    $payments->addColumn('id', 'id');
    $payments->addColumn('order_id', 'foreignId');
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

    $findings = $auditor->audit($tables);

    $rules = collect($findings)->pluck('rule')->unique()->values()->all();

    expect($rules)->toContain('duplicate_foreign_key')
        ->and($rules)->toContain('duplicate_index')
        ->and($rules)->toContain('mismatched_foreign_key')
        ->and($rules)->toContain('redundant_single_column_index')
        ->and($rules)->toContain('unindexed_foreign_key');

    expect($findings)->not->toContain(fn ($finding): bool => $finding->rule === 'dangling_foreign_key');
    expect($findings)->not->toContain(fn ($finding): bool => $finding->rule === 'no_primary_key');
});
