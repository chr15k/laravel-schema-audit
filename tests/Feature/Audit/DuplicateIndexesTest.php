<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Migrations\MigrationPathResolver;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\Sources\MigrationSource;

it('detects duplicate indexes from migrations', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/DuplicateIndexes';

    $files = app(MigrationPathResolver::class)
        ->resolve([$directory]);

    $source = new MigrationSource(
        app(MigrationLocator::class),
        app(MigrationParser::class),
        $files
    );

    $schema = app(SchemaBuilder::class)->build($source);

    $audit = app(SchemaAuditor::class)->audit($schema);

    expect($audit->findings)
        ->toHaveCount(1)
        ->and($audit->findings[0]->code)
        ->toBe('duplicate_index');
});
