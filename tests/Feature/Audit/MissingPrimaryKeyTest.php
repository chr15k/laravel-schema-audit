<?php

use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Migrations\MigrationPathResolver;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\Sources\MigrationSource;

it('detects missing primary keys from migrations', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/MissingPrimaryKeys';

    $files = app(MigrationPathResolver::class)
        ->resolve([$directory]);

    $source = new MigrationSource(
        app(MigrationLocator::class),
        app(MigrationParser::class),
        $files
    );

    $schema = app(SchemaBuilder::class)
        ->build($source);

    $audit = app(SchemaAuditor::class)
        ->audit($schema);

    expect($audit->findings[0]->code)
        ->toBe('missing_primary_key');
});
