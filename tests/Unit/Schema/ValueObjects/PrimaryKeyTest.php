<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('returns a copy with an updated guard', function (): void {
    $primaryKey = new PrimaryKey(columns: ['id']);

    $guarded = $primaryKey->withGuard(SchemaGuard::Unknown);

    expect($guarded->guard)->toBe(SchemaGuard::Unknown)
        ->and($primaryKey->guard)->toBeNull();
});

it('serialises to an array', function (): void {
    $location = new SourceLocation('/path/to/migration.php', 15);

    $primaryKey = new PrimaryKey(
        columns: ['tenant_id', 'id'],
        location: $location,
    );

    expect($primaryKey->toArray())->toBe([
        'columns'  => ['tenant_id', 'id'],
        'location' => $location,
    ])->and($primaryKey->jsonSerialize())->toBe($primaryKey->toArray());
});
