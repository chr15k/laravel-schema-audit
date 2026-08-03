<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\DuplicateForeignKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new DuplicateForeignKeyRule)->handle(
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

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report a single foreign key on a column', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign'
    ));

    $schema = new Schema(['orders' => $orders]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports a foreign key declared more than once on the same column', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign'));
    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign_2'));

    $schema = new Schema(['orders' => $orders]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('orders')
        ->and($result->audit->findings[0]->code)->toBe('duplicate_foreign_key')
        ->and($result->audit->findings[0]->column)->toBe('customer_id')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Warning);
});

it('reports duplicates independently across multiple tables', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'a'));
    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'b'));

    $invoices = TableSchema::make('invoices');
    $invoices->addForeignKey(new ForeignKey(
        column: 'order_id',
        referencesTable: 'orders',
        referencesColumn: 'id',
        name: 'c'));

    $schema = new Schema(['orders' => $orders, 'invoices' => $invoices]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('orders');
});

it('marks the finding as conditional when the duplicate foreign key is conditional', function (): void {
    $orders = TableSchema::make('orders');

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign',
    ));

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign_2',
        conditional: true,
    ));

    $schema = new Schema(['orders' => $orders]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->conditional)->toBeTrue();
});

it('does not mark the finding as conditional when neither foreign key is conditional', function (): void {
    $orders = TableSchema::make('orders');

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign',
    ));

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign_2',
    ));

    $schema = new Schema(['orders' => $orders]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->conditional)->toBeFalse();
});

it('marks the finding as conditional when all duplicate foreign keys are conditional', function (): void {
    $orders = TableSchema::make('orders');

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign',
        conditional: true,
    ));

    $orders->addForeignKey(new ForeignKey(
        column: 'customer_id',
        referencesTable: 'customers',
        referencesColumn: 'id',
        name: 'orders_customer_id_foreign_2',
        conditional: true,
    ));

    $schema = new Schema(['orders' => $orders]);

    $result = (new DuplicateForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->conditional)->toBeTrue();
});
