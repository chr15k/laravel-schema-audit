<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\DanglingForeignKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new DanglingForeignKeyRule)->handle(
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

    $result = (new DanglingForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('detects a dangling foreign key when the referenced table does not exist', function (): void {
    $table = TableSchema::make('transactions');
    $table->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $table->addForeignKey(new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id'));

    $schema = new Schema([
        'transactions' => $table,
    ]);

    $result = (new DanglingForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->code)->toBe('dangling_foreign_key')
        ->and($result->audit->findings[0]->table)->toBe('transactions')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error);
});

it('does not report dangling foreign key when the referenced table exists', function (): void {
    $table = TableSchema::make('transactions');
    $table->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $table->addForeignKey(new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id'));

    $schema = new Schema([
        'transactions' => $table,
        'users'        => TableSchema::make('users'),
    ]);

    $result = (new DanglingForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports every table with a dandling foreign key', function (): void {
    $transactions = TableSchema::make('transactions');
    $transactions->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $transactions->addForeignKey(new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id'));

    $orders = TableSchema::make('orders');
    $orders->addColumn(new Column('user_id', ColumnMethod::ForeignId));
    $orders->addForeignKey(new ForeignKey(columns: 'user_id', referencesTable: 'users', referencesColumn: 'id'));

    $items = TableSchema::make('order_items');
    $items->addColumn(new Column('order_id', ColumnMethod::ForeignId));
    $items->addForeignKey(new ForeignKey(columns: 'id', referencesTable: 'orders', referencesColumn: 'id'));

    $schema = new Schema([
        'transactions' => $transactions,
        'orders'       => $orders,
        'order_items'  => $items,
    ]);

    $result = (new DanglingForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(2)
        ->and($result->audit->findings[0]->code)->toBe('dangling_foreign_key')
        ->and($result->audit->findings[0]->table)->toBe('transactions')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error)
        ->and($result->audit->findings[1]->code)->toBe('dangling_foreign_key')
        ->and($result->audit->findings[1]->table)->toBe('orders')
        ->and($result->audit->findings[1]->severity)->toBe(Severity::Error);
});
