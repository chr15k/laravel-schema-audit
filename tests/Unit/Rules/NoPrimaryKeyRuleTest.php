<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\MissingPrimaryKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new MissingPrimaryKeyRule)->handle(
        new AuditContext($schema),
        function (AuditContext $context) use (&$called): AuditContext {
            $called = true;

            return $context;
        },
    );

    expect($called)->toBeTrue();
});

it('does nothing when there are no tables', function (): void {
    $schema = new Schema([]);

    $result = (new MissingPrimaryKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports a missing primary key', function (): void {
    $users = new TableSchema('users');

    $schema = new Schema([
        'users' => $users,
    ]);

    $result = (new MissingPrimaryKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('users')
        ->and($result->audit->findings[0]->code)->toBe('missing_primary_key')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error);
});

it('does not report tables with a primary key', function (): void {
    $users = new TableSchema('users');
    $users->setPrimaryKey(new PrimaryKey(['id']));

    $schema = new Schema([
        'users' => $users,
    ]);

    $result = (new MissingPrimaryKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports every table missing a primary key', function (): void {
    $tableWithPrimaryKey = new TableSchema('comments');
    $tableWithPrimaryKey->setPrimaryKey(new PrimaryKey(['id']));

    $schema = new Schema([
        'users'    => new TableSchema('users'),
        'posts'    => new TableSchema('posts'),
        'comments' => $tableWithPrimaryKey,
    ]);

    $result = (new MissingPrimaryKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(2)
        ->and($result->audit->findings[0]->table)->toBe('users')
        ->and($result->audit->findings[0]->code)->toBe('missing_primary_key')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error)
        ->and($result->audit->findings[1]->table)->toBe('posts')
        ->and($result->audit->findings[1]->code)->toBe('missing_primary_key')
        ->and($result->audit->findings[1]->severity)->toBe(Severity::Error);
});
