<?php

use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAuditor;

it('detects unindexed fks from migrations', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/UnindexedForeignKeys';

    config([
        'schema-audit.driver' => 'pgsql',
    ]);

    $files = app(MigrationLocator::class)
        ->files([$directory]);

    $schema = app(SchemaBuilder::class)
        ->build($files);

    $audit = app(SchemaAuditor::class)
        ->audit($schema);

    expect($audit->findings[0]->code)
        ->toBe('unindexed_foreign_key');
});

it('bypasses unindexed fks check if unsupported db driver', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/UnindexedForeignKeys';

    config([
        'schema-audit.driver' => 'mysql',
    ]);

    $files = app(MigrationLocator::class)
        ->files([$directory]);

    $schema = app(SchemaBuilder::class)
        ->build($files);

    $audit = app(SchemaAuditor::class)
        ->audit($schema);

    expect($audit->findings)->toBeEmpty();
});
