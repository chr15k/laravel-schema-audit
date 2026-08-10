<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\SchemaAudit;
use Chr15k\SchemaAudit\ValueObjects\Finding;

it('merges additional findings immutably', function (): void {
    $audit = new SchemaAudit([
        new Finding(code: 'duplicate_index', table: 'users', message: 'first'),
    ]);

    $merged = $audit->withFindings([
        new Finding(code: 'missing_primary_key', table: 'events', message: 'second'),
    ]);

    expect($audit->count())->toBe(1)
        ->and($merged->count())->toBe(2)
        ->and($merged->findings[0]->code)->toBe('duplicate_index')
        ->and($merged->findings[1]->code)->toBe('missing_primary_key');
});

it('filters out conditional findings', function (): void {
    $audit = new SchemaAudit([
        new Finding(code: 'duplicate_index', table: 'users', message: 'stable'),
        new Finding(
            code: 'duplicate_foreign_key',
            table: 'posts',
            message: 'conditional',
            guard: SchemaGuard::Unknown,
        ),
    ]);

    $filtered = $audit->withoutConditionalFindings();

    expect($filtered->count())->toBe(1)
        ->and($filtered->findings[0]->code)->toBe('duplicate_index');
});

it('serialises findings to json', function (): void {
    $audit = new SchemaAudit([
        new Finding(
            code: 'unindexed_foreign_key',
            table: 'orders',
            message: 'missing index',
            columns: ['customer_id'],
            severity: Severity::Warning,
        ),
    ]);

    /** @var array<int, array<string, mixed>> $json */
    $json = json_decode($audit->toJson(), true);

    /** @var array<int, array<string, mixed>> $prettyJson */
    $prettyJson = json_decode($audit->toPrettyJson(), true);

    expect($audit->hasIssues())->toBeTrue()
        ->and($audit->toArray())->toHaveCount(1)
        ->and($json[0]['code'] ?? null)->toBe('unindexed_foreign_key')
        ->and($prettyJson[0]['table'] ?? null)->toBe('orders')
        ->and($audit->jsonSerialize())->toBe($audit->findings);
});
