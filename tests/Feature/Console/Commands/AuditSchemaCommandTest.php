<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

it('runs successfully against a valid migrations path', function (): void {
    $exitCode = Artisan::call('schema:audit');

    expect($exitCode)->toBe(Command::SUCCESS);
});

it('outputs the folded schema as json', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/SchemaBuilder/Columns',
    ]);

    Artisan::call('schema:audit', [
        '--schema-only' => true,
    ]);

    $schema = json_decode(Artisan::output(), true);

    expect($schema)->toBeArray();
});

it('outputs findings as json', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/Audit/DuplicateIndexes',
    ]);

    Artisan::call('schema:audit', [
        '--json' => true,
    ]);

    /** @var array<int, array{code: string}> $audit */
    $audit = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($audit[0]['code'])->toBe('duplicate_index');
});

it('returns failure when schema issues are found', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/Audit/DuplicateIndexes',
    ]);

    $exitCode = Artisan::call('schema:audit');

    expect($exitCode)->toBe(Command::FAILURE);
});

it('accepts additional migration paths', function (): void {
    $exitCode = Artisan::call('schema:audit', [
        '--path' => [
            __DIR__.'/../../../Fixtures/Migrations/SchemaBuilder/Columns',
        ],
    ]);

    expect($exitCode)->toBe(Command::SUCCESS);
});

it('renders a styled report with finding details', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/Audit/DuplicateIndexes',
    ]);

    $exitCode = Artisan::call('schema:audit');

    $output = Artisan::output();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('Schema Audit Results')
        ->and($output)->toContain('Duplicate Index')
        ->and($output)->toContain('users.email')
        ->and($output)->toContain('Location:');
});

it('renders a pass message when no findings are reported', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/SchemaBuilder/Columns',
    ]);

    $exitCode = Artisan::call('schema:audit');

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('PASS');
});

it('renders conditional finding notes when enabled', function (): void {
    config()->set('schema-audit.paths', [
        __DIR__.'/../../../Fixtures/Migrations/SchemaBuilder/ConditionalUnknowns',
    ]);
    config()->set('schema-audit.driver', 'pgsql');
    config()->set('schema-audit.report_conditional_findings', true);

    $exitCode = Artisan::call('schema:audit');
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('Note:')
        ->and($output)->toContain('false positive')
        ->and($output)->toContain('conditional')
        ->and($output)->toContain('migration logic');
});

it('throws when a migration path does not exist', function (): void {
    expect(fn () => Artisan::call('schema:audit', [
        '--path' => [__DIR__.'/../../../Fixtures/Migrations/does-not-exist'],
    ]))->toThrow(DirectoryNotFoundException::class);
});
