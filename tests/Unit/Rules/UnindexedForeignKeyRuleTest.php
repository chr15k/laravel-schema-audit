<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\Support\Config;
use Illuminate\Config\Repository;

function configWithDriver(string $driver): Config
{
    return new Config(new Repository(['schema-audit' => ['driver' => $driver]]));
}

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new UnindexedForeignKeyRule(configWithDriver('pgsql')))->handle(
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

    $result = (new UnindexedForeignKeyRule(configWithDriver('pgsql')))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports a foreign key with no covering index on a driver that does not auto-index them', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(columns: 'customer_id', referencesTable: 'customers', referencesColumn: 'id', name: 'orders_customer_id_foreign'));

    $schema = new Schema(['orders' => $orders]);

    $result = (new UnindexedForeignKeyRule(configWithDriver('pgsql')))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('orders')
        ->and($result->audit->findings[0]->code)->toBe('unindexed_foreign_key')
        ->and($result->audit->findings[0]->column)->toBe('customer_id')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Warning)
        ->and($result->audit->findings[0]->message)->toContain('pgsql');
});

it('does not report anything at all on mysql, which auto-indexes foreign key columns', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(columns: 'customer_id', referencesTable: 'customers', referencesColumn: 'id', name: 'orders_customer_id_foreign'));

    $schema = new Schema(['orders' => $orders]);

    $result = (new UnindexedForeignKeyRule(configWithDriver('mysql')))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report anything on mariadb either', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(columns: 'customer_id', referencesTable: 'customers', referencesColumn: 'id', name: 'orders_customer_id_foreign'));

    $schema = new Schema(['orders' => $orders]);

    $result = (new UnindexedForeignKeyRule(configWithDriver('mariadb')))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report a foreign key that already has a covering index, regardless of driver', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(columns: 'customer_id', referencesTable: 'customers', referencesColumn: 'id', name: 'orders_customer_id_foreign'));
    $orders->addIndex(new Index(name: 'orders_customer_id_index', columns: ['customer_id'], unique: false));

    $schema = new Schema(['orders' => $orders]);

    $result = (new UnindexedForeignKeyRule(configWithDriver('pgsql')))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('defaults to mysql (auto-indexing) when no driver is configured', function (): void {
    $orders = TableSchema::make('orders');
    $orders->addForeignKey(new ForeignKey(columns: 'customer_id', referencesTable: 'customers', referencesColumn: 'id', name: 'x'));

    $schema = new Schema(['orders' => $orders]);

    $config = new Config(new Repository([]));

    $result = (new UnindexedForeignKeyRule($config))->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});
