<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Schema\Collections\ColumnCollection;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;

it('returns the method of the first column that implies a primary key', function (): void {
    $columns = new ColumnCollection([
        'title' => new Column(name: 'title', method: ColumnMethod::String),
        'id'    => new Column(name: 'id', method: ColumnMethod::Id),
    ]);

    expect($columns->primaryKeyColumnMethod())->toBe(ColumnMethod::Id);
});

it('renames a column key without changing the column object', function (): void {
    $column = new Column(name: 'legacy_handle', method: ColumnMethod::String);

    $columns = new ColumnCollection([
        'legacy_handle' => $column,
    ]);

    $columns->rename('legacy_handle', 'handle');

    expect($columns->has('handle'))->toBeTrue()
        ->and($columns->has('legacy_handle'))->toBeFalse()
        ->and($columns->get('handle'))->toBe($column);
});

it('ignores rename requests for columns that do not exist', function (): void {
    $columns = new ColumnCollection([
        'name' => new Column(name: 'name', method: ColumnMethod::String),
    ]);

    $columns->rename('missing', 'also_missing');

    expect($columns->keys()->all())->toBe(['name']);
});
