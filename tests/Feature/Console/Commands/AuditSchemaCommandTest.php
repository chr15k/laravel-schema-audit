<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('runs successfully against a valid migrations path', function (): void {
    $exitCode = Artisan::call('schema:audit', ['--path' => migrations_path()]);

    expect($exitCode)->toBe(0);
});

it('outputs the folded schema as JSON including known table and column state', function (): void {
    Artisan::call('schema:audit', ['--path' => migrations_path()]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray();

    $users = collect($decoded)->firstWhere('table', 'users');

    expect($users)->not->toBeNull()
        ->and($users['columns'])->toHaveKey('email')
        ->and($users['columns'])->not->toHaveKey('nickname')
        ->and(collect($users['indexes'])->pluck('columns')->flatten())->toContain('email');
});
