<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\InvalidReferencedKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new InvalidReferencedKeyRule)->handle(
        new AuditContext($schema),
        function (AuditContext $context) use (&$called): AuditContext {
            $called = true;

            return $context;
        },
    );

    expect($called)->toBeTrue();
});

it('does nothing when there are no tables', function (): void {
    $schema = new Schema([]);

    $result = (new InvalidReferencedKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports an invalid foreign key refernce whose referenced column has no unique constraint on the parent table', function (): void {
    $users = TableSchema::make('users');
    $users->addColumn(new Column('id', ColumnMethod::Id));
    $users->addIndex(new Index(name: null, columns: ['id'], unique: false));

    $transactions = TableSchema::make('transactions');
    $transactions->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $transactions->addForeignKey(new ForeignKey('user_id', 'users', 'id'));

    $schema = new Schema([
        'users'        => $users,
        'transactions' => $transactions,
    ]);

    $result = (new InvalidReferencedKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->code)->toBe('invalid_referenced_key')
        ->and($result->audit->findings[0]->table)->toBe('transactions')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error);
});

it('does not report when referenced column has a unique constraint on the parent table', function (): void {
    $users = TableSchema::make('users');
    $users->addColumn(new Column('id', ColumnMethod::Id));
    $users->addIndex(new Index(name: null, columns: ['id'], unique: true));

    $transactions = TableSchema::make('transactions');
    $transactions->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $transactions->addForeignKey(new ForeignKey('user_id', 'users', 'id'));

    $schema = new Schema([
        'users'        => $users,
        'transactions' => $transactions,
    ]);

    $result = (new InvalidReferencedKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});
