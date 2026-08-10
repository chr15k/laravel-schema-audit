<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\RedundantIndexRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new RedundantIndexRule)->handle(
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

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('flags a non-unique index as redundant when an equivalent unique index exists', function (): void {
    $users = TableSchema::make('users');
    $users->addIndex(new Index(name: 'users_email_index', columns: ['email'], unique: false));
    $users->addIndex(new Index(name: 'users_email_unique', columns: ['email'], unique: true));

    $schema = new Schema(['users' => $users]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('users')
        ->and($result->audit->findings[0]->code)->toBe('redundant_index')
        ->and($result->audit->findings[0]->columns)->toBe(['email'])
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Warning)
        ->and($result->audit->findings[0]->message)->toContain('users_email_index')
        ->and($result->audit->findings[0]->message)->toContain('users_email_unique');
});

it('does NOT flag a unique index as redundant just because a non-unique one exists on the same columns', function (): void {
    // A unique constraint can't be safely replaced by a non-unique index
    // — the uniqueness guarantee would be lost — so this direction must
    // never be flagged, only the reverse.
    $users = TableSchema::make('users');
    $users->addIndex(new Index(name: 'users_email_unique', columns: ['email'], unique: true));
    $users->addIndex(new Index(name: 'users_email_index', columns: ['email'], unique: false));

    $schema = new Schema(['users' => $users]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->columns)->toBe(['email']);

    // Confirm it's specifically the non-unique one flagged, not the unique one.
    expect($result->audit->findings[0]->message)
        ->toContain('Index users_email_index is covered by users_email_unique');
});

it('flags a single-column index covered by the leading column of a composite index', function (): void {
    $articles = TableSchema::make('articles');
    $articles->addIndex(new Index(name: 'articles_category_id_index', columns: ['category_id'], unique: false));
    $articles->addIndex(new Index(name: 'articles_category_id_author_id_index', columns: ['category_id', 'author_id'], unique: false));

    $schema = new Schema(['articles' => $articles]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->columns)->toBe(['category_id']);
});

it('does not flag two composite indexes of equal length as redundant, even with overlapping columns', function (): void {
    $articles = TableSchema::make('articles');
    $articles->addIndex(new Index(name: 'articles_a_b_index', columns: ['a', 'b'], unique: false));
    $articles->addIndex(new Index(name: 'articles_b_a_index', columns: ['b', 'a'], unique: false));

    $schema = new Schema(['articles' => $articles]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not flag unrelated indexes on different columns', function (): void {
    $articles = TableSchema::make('articles');
    $articles->addIndex(new Index(name: 'articles_a_index', columns: ['a'], unique: false));
    $articles->addIndex(new Index(name: 'articles_b_index', columns: ['b'], unique: false));

    $schema = new Schema(['articles' => $articles]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not treat an index as redundant with itself', function (): void {
    $users = TableSchema::make('users');
    $users->addIndex(new Index(name: 'users_email_index', columns: ['email'], unique: false));

    $schema = new Schema(['users' => $users]);

    $result = (new RedundantIndexRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});
