<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

it('returns null for non-list string arrays', function (): void {
    $call = new ColumnCall(method: 'index', arguments: [
        'columns' => ['email' => 'primary'],
    ]);

    expect($call->stringArrayArgument('columns'))->toBeNull();
});

it('returns null for mixed string and non-string list values', function (): void {
    $call = new ColumnCall(method: 'foreign', arguments: [
        'columns' => ['user_id', 123],
    ]);

    expect($call->stringOrArrayArgument('columns'))->toBeNull();
});

it('normalises string list arguments from a scalar default', function (): void {
    $call = new ColumnCall(method: 'dropColumn', arguments: []);

    expect($call->stringListArgument('columns', 'deleted_at'))->toBe(['deleted_at']);
});

it('throws when a migration file cannot be read', function (): void {
    $path = sys_get_temp_dir().'/schema-audit-unreadable-'.uniqid('', true).'.php';

    file_put_contents($path, '<?php');

    chmod($path, 0000);

    try {
        expect(fn () => app(MigrationParser::class)->parseFile($path))
            ->toThrow(RuntimeException::class, 'Unable to read migration file');
    } finally {
        chmod($path, 0644);
        @unlink($path);
    }
});

it('exposes source locations on parsed column calls', function (): void {
    $operations = app(MigrationParser::class)->parseFile(
        __DIR__.'/../../Fixtures/Migrations/SchemaBuilder/Columns/2024_01_01_000000_create_posts_table.php',
    );

    $titleCall = collect($operations)
        ->flatMap(fn ($operation) => $operation->chains)
        ->flatMap(fn ($chain): array => [$chain->root(), ...$chain->modifiers()])
        ->firstWhere('method', 'string');

    expect($titleCall)->toBeInstanceOf(ColumnCall::class)
        ->and($titleCall?->location)->toBeInstanceOf(SourceLocation::class);
});
