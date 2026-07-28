<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Migrations\MigrationPathResolver;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAuditor;

it('detects duplicate indexes from migrations', function (): void {
    $directory = __DIR__.'/../../Fixtures/Migrations/Audit/DuplicateIndexes';

    $paths = app(MigrationPathResolver::class)->resolve([
        $directory,
    ]);

    $files = app(MigrationLocator::class)->files($paths);

    $schema = app(SchemaBuilder::class)->build($files);

    $audit = app(SchemaAuditor::class)->audit($schema);

    expect($audit->findings)
        ->toHaveCount(1)
        ->and($audit->findings[0]->code)
        ->toBe('duplicate_index');
});
