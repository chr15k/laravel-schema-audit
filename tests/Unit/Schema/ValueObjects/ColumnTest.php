<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('returns a copy with an updated guard', function (): void {
    $column = new Column(name: 'email', method: ColumnMethod::String);

    $guarded = $column->withGuard(SchemaGuard::MissingColumn);

    expect($guarded->guard)->toBe(SchemaGuard::MissingColumn)
        ->and($column->guard)->toBeNull();
});

it('serialises to an array', function (): void {
    $location = new SourceLocation('/path/to/migration.php', 8);

    $column = new Column(
        name: 'title',
        method: ColumnMethod::String,
        location: $location,
    );

    expect($column->toArray())->toBe([
        'name'     => 'title',
        'method'   => 'string',
        'location' => $location,
    ])->and($column->jsonSerialize())->toBe($column->toArray());
});
