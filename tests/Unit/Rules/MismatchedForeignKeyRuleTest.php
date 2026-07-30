<?php

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Rules\MismatchedForeignKeyRule;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;

it('passes context to the next pipeline stage', function (): void {
    $schema = new Schema([]);

    $called = false;

    (new MismatchedForeignKeyRule)->handle(
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

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('reports a foreignId() foreign key pointing at a plain increments() primary key', function (): void {
    $categories = TableSchema::make('categories');
    $categories->addColumn(new Column('id', ColumnMethod::Increments));

    $posts = TableSchema::make('posts');
    $posts->addColumn(new Column('id', ColumnMethod::Id));
    $posts->addColumn(new Column('category_id', ColumnMethod::ForeignId));
    $posts->addForeignKey(new ForeignKey(
        column: 'category_id',
        referencesTable: 'categories',
        referencesColumn: 'id',
        name: 'posts_category_id_foreign'));

    $schema = new Schema(['categories' => $categories, 'posts' => $posts]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)
        ->toHaveCount(1)
        ->and($result->audit->findings[0]->table)->toBe('posts')
        ->and($result->audit->findings[0]->code)->toBe('mismatched_foreign_key')
        ->and($result->audit->findings[0]->column)->toBe('category_id')
        ->and($result->audit->findings[0]->severity)->toBe(Severity::Error);
});

it('does not report a foreignId() foreign key pointing at an id() primary key of the same family', function (): void {
    $authors = TableSchema::make('authors');
    $authors->addColumn(new Column('id', ColumnMethod::Id));

    $posts = TableSchema::make('posts');
    $posts->addColumn(new Column('id', ColumnMethod::Id));
    $posts->addColumn(new Column('author_id', ColumnMethod::ForeignId));
    $posts->addForeignKey(new ForeignKey(
        column: 'author_id',
        referencesTable: 'authors',
        referencesColumn: 'id',
        name: 'posts_author_id_foreign')
    );

    $schema = new Schema(['authors' => $authors, 'posts' => $posts]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report a foreign key whose referenced table does not exist', function (): void {
    // A dangling reference is DanglingForeignKeyRule's job, not this
    // one's — MismatchedForeignKeyRule must stay silent rather than
    // guess at a type match against a table it can't find.
    $posts = TableSchema::make('posts');
    $posts->addColumn(new Column('id', ColumnMethod::Id));
    $posts->addColumn(new Column('ghost_id', ColumnMethod::ForeignId));
    $posts->addForeignKey(new ForeignKey(
        column: 'ghost_id',
        referencesTable: 'ghosts',
        referencesColumn: 'id',
        name: 'x'
    ));

    $schema = new Schema(['posts' => $posts]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report when the referenced table has no auto-incrementing primary key column to compare against', function (): void {
    // No id()/increments()-style column on the referenced table means
    // primaryKeyColumnMethod() can't resolve — the rule must skip rather
    // than guess, since a wrong guess here is worse than staying silent.
    $noAutoIncrementTable = TableSchema::make('legacy_table');

    $posts = TableSchema::make('posts');
    $posts->addColumn(new Column('id', ColumnMethod::Id));
    $posts->addColumn(new Column('legacy_id', ColumnMethod::ForeignId));
    $posts->addForeignKey(new ForeignKey(
        column: 'legacy_id',
        referencesTable: 'legacy_table',
        referencesColumn: 'id',
        name: 'x'
    ));

    $schema = new Schema(['legacy_table' => $noAutoIncrementTable, 'posts' => $posts]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report a foreign key column that is not tracked on the table at all', function (): void {
    // The FK references a column name that was never added via addColumn()
    // — resolve() can't find its type, so it must skip rather than error.
    $categories = TableSchema::make('categories');
    $categories->addColumn(new Column('id', ColumnMethod::Increments));

    $posts = TableSchema::make('posts');
    $posts->addForeignKey(new ForeignKey(
        column: 'category_id',
        referencesTable: 'categories',
        referencesColumn: 'id',
        name: 'x'
    ));

    $schema = new Schema(['categories' => $categories, 'posts' => $posts]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});

it('does not report a foreign key referencing a compatible non-primary unique column', function (): void {
    // Regression: a foreign key may reference a unique column other than
    // the parent table's primary key. Compare against that referenced
    // column's type rather than assuming the primary key is always the
    // target.
    $countries = TableSchema::make('countries');
    $countries->addColumn(new Column('uuid', ColumnMethod::Uuid));
    $countries->addColumn(new Column('iso_code', ColumnMethod::String));

    $users = TableSchema::make('users');
    $users->addColumn(new Column('id', ColumnMethod::Id));
    $users->addColumn(new Column('country_uuid', ColumnMethod::Uuid));
    $users->addForeignKey(new ForeignKey(
        column: 'country_uuid',
        referencesTable: 'countries',
        referencesColumn: 'uuid',
    ));

    $schema = new Schema([
        'countries' => $countries,
        'users'     => $users,
    ]);

    $result = (new MismatchedForeignKeyRule)->handle(
        new AuditContext($schema),
        fn (AuditContext $context): AuditContext => $context,
    );

    expect($result->audit->findings)->toBeEmpty();
});
