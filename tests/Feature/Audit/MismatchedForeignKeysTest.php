<?php

use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAuditor;

it('detects mismatched fks from migrations', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/MismatchedForeignKeys';

    $files = app(MigrationLocator::class)
        ->files([$directory]);

    $schema = app(SchemaBuilder::class)
        ->build($files);

    $audit = app(SchemaAuditor::class)
        ->audit($schema);

    expect($audit->findings[0]->code)
        ->toBe('mismatched_foreign_key');
});
