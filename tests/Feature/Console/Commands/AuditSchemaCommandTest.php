<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('runs successfully against a valid migrations path', function (): void {
    $exitCode = Artisan::call('schema:audit', ['--path' => migrations_path()]);

    expect($exitCode)->toBe(0);
});

it('outputs the folded schema as JSON including known table and column state', function (): void {
    Artisan::call('schema:audit', ['--path' => migrations_path(), '--schema-only' => true]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray();

    $users = collect($decoded)->firstWhere('schema.table', 'users');

    $columns = collect($users['schema']['columns']);

    expect($columns->pluck('name'))
        ->toContain('email')
        ->not->toContain('nickname');

    expect(collect($users['schema']['indexes'])
        ->pluck('columns')->flatten()->first())->toBe('email');
});
