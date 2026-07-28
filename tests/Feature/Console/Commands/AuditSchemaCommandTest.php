<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

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

    $audit = json_decode(Artisan::output(), true);

    expect($audit)->toBeArray()
        ->and($audit[0]['code'])->toBe('duplicate_index');
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
