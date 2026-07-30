<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Schema\TableSchema;

describe('column resolution', function (): void {
    it('resolves basic column types and tracks their Blueprint method', function (): void {
        $schema = buildSchemaFromBuilderFixtures('Columns');

        $posts = $schema->table('posts');

        assert($posts instanceof TableSchema);

        $columns = $posts->columns();

        expect($columns)->toHaveKeys(['id', 'title', 'body', 'published'])
            ->and($columns['title']->method)->toBe(ColumnMethod::String)
            ->and($columns['body']->method)->toBe(ColumnMethod::Text)
            ->and($columns['published']->method)->toBe(ColumnMethod::Boolean);
    });

    it('defaults id() with no explicit name to a column named id', function (): void {
        $posts = buildSchemaFromBuilderFixtures('Columns')->table('posts');

        expect($posts?->hasColumn('id'))->toBeTrue()
            ->and($posts?->columns()['id']->method)->toBe(ColumnMethod::Id);
    });

    it('does not track column-less structural calls like timestamps() as a column', function (): void {
        $posts = buildSchemaFromBuilderFixtures('Columns')->table('posts');

        expect($posts?->hasColumn('created_at'))->toBeFalse()
            ->and($posts?->hasColumn('updated_at'))->toBeFalse();
    });

    it('treats id()/increments()-style columns as an implicit primary key', function (): void {
        $posts = buildSchemaFromBuilderFixtures('Columns')->table('posts');

        expect($posts?->hasPrimaryKey())->toBeTrue()
            ->and($posts?->primaryKeyColumnMethod())->toBe(ColumnMethod::Id);
    });
});

describe('foreign key resolution', function (): void {
    it('infers the referenced table from the column name by convention', function (): void {
        $posts = buildSchemaFromBuilderFixtures('ForeignKeys')->table('posts');

        $fk = collect($posts?->foreignKeys())->firstWhere('column', 'user_id');

        expect($fk)->not->toBeNull()
            ->and($fk?->referencesTable)->toBe('users')
            ->and($fk?->name)->toBe('posts_user_id_foreign');
    });

    it('honours an explicit table name passed positionally to constrained()', function (): void {
        $posts = buildSchemaFromBuilderFixtures('ForeignKeys')->table('posts');

        $fk = collect($posts?->foreignKeys())->firstWhere('column', 'editor_id');

        expect($fk?->referencesTable)->toBe('users');
    });

    it('honours an explicit table name passed as a named argument to constrained()', function (): void {
        $posts = buildSchemaFromBuilderFixtures('ForeignKeys')->table('posts');

        $fk = collect($posts?->foreignKeys())->firstWhere('column', 'category_id');

        expect($fk?->referencesTable)->toBe('categories');
    });

    it('resolves old-style foreign()->references()->on() chains', function (): void {
        $posts = buildSchemaFromBuilderFixtures('ForeignKeys')->table('posts');

        $fk = collect($posts?->foreignKeys())->firstWhere('column', 'legacy_owner_id');

        expect($fk)->not->toBeNull()
            ->and($fk?->referencesTable)->toBe('users');
    });

    it('does NOT auto-generate a constraint name for old-style foreign() — unlike constrained()', function (): void {
        // Documents a real, current asymmetry: the modern constrained()
        // path always resolves a conventional index/constraint name, but
        // the legacy foreign()->references()->on() chain does not. If
        // this ever changes deliberately, update this test rather than
        // being surprised by it.
        $posts = buildSchemaFromBuilderFixtures('ForeignKeys')->table('posts');

        $fk = collect($posts?->foreignKeys())->firstWhere('column', 'legacy_owner_id');

        expect($fk?->name)->toBeNull();
    });

    it('resolves the referenced table from a model class passed to foreignIdFor()', function (): void {
        $comments = buildSchemaFromBuilderFixtures('ForeignKeys')->table('comments');

        expect($comments?->hasColumn('post_id'))->toBeTrue();

        $fk = collect($comments?->foreignKeys())->firstWhere('column', 'post_id');

        expect($fk)->not->toBeNull()
            ->and($fk?->referencesTable)->toBe('posts');
    });

    it('keeps the CHILD table column name even when constrained() is given an explicit referenced column', function (): void {
        // Regression test: constrained()'s $column argument is the column
        // on the REFERENCED table (defaults to 'id'), not an override for
        // this table's own FK column name. An earlier version of
        // ForeignKeyResolver incorrectly overwrote ForeignKey::$column
        // with that referenced-column value instead of leaving it as the
        // actual child column ('assigned_to').
        $comments = buildSchemaFromBuilderFixtures('ForeignKeys')->table('comments');

        $fk = collect($comments?->foreignKeys())->firstWhere('referencesTable', 'users');

        expect($fk)->not->toBeNull()
            ->and($fk?->column)->toBe('assigned_to')
            ->and($fk?->name)->toBe('comments_assigned_to_custom_fk');
    });

    it('tracks the referenced column when a foreign key targets a non-primary unique column', function (): void {
        $table = buildSchemaFromBuilderFixtures('ForeignKeys')
            ->table('telescope_entries_tags');

        $fk = collect($table->foreignKeys())
            ->firstWhere('column', 'entry_uuid');

        expect($fk)->not->toBeNull()
            ->and($fk->referencesTable)->toBe('telescope_entries')
            ->and($fk->referencesColumn)->toBe('uuid');
    });
});

describe('index resolution', function (): void {
    it('resolves a column-level ->unique() modifier with a conventional name', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')->table('articles');

        $index = collect($articles?->indexes())->firstWhere('columns', ['slug']);

        expect($index)->not->toBeNull()
            ->and($index?->unique)->toBeTrue()
            ->and($index?->name)->toBe('articles_slug_unique');
    });

    it('resolves a column-level ->index() modifier with a conventional name', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')->table('articles');

        $index = collect($articles?->indexes())->firstWhere('columns', ['email']);

        expect($index)->not->toBeNull()
            ->and($index?->unique)->toBeFalse()
            ->and($index?->name)->toBe('articles_email_index');
    });

    it('honours an explicit index name passed to a column-level modifier', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')->table('articles');

        $index = collect($articles?->indexes())->firstWhere('columns', ['reference']);

        expect($index?->name)->toBe('custom_reference_unique');
    });

    it('resolves a table-level composite index with a conventional name', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')->table('articles');

        $index = collect($articles?->indexes())
            ->first(fn ($i): bool => $i->columns === ['category_id', 'author_id'] && ! $i->unique);

        expect($index)->not->toBeNull()
            ->and($index?->name)->toBe('articles_category_id_author_id_index');
    });

    it('honours an explicit name on a table-level composite unique index', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')->table('articles');

        $index = collect($articles?->indexes())
            ->first(fn ($i): bool => $i->columns === ['category_id', 'author_id'] && $i->unique);

        expect($index)->not->toBeNull()
            ->and($index?->name)->toBe('articles_category_author_unique');
    });

    it('does not create a redundant index when unique and index are chained on the same column', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')
            ->table('articles');

        $indexes = collect($articles?->indexes())
            ->where('columns', ['slug']);

        expect($indexes)
            ->toHaveCount(1)
            ->and($indexes->first()?->unique)->toBeTrue();
    });

    it('preserves explicit unique and non-unique indexes on the same column', function (): void {
        $articles = buildSchemaFromBuilderFixtures('Indexes')
            ->table('articles');

        $indexes = collect($articles?->indexes())
            ->where('columns', ['email']);

        expect($indexes)->toHaveCount(2);
    });
});

describe('folding across multiple migration files', function (): void {
    it('adds a column declared in a later Schema::table() alter', function (): void {
        $members = buildSchemaFromBuilderFixtures('Folding')->table('members');

        expect($members?->hasColumn('email'))->toBeTrue();
    });

    it('removes a column dropped in a later migration', function (): void {
        $members = buildSchemaFromBuilderFixtures('Folding')->table('members');

        expect($members?->hasColumn('legacy_handle'))->toBeFalse();
    });

    it('renames the table and carries its columns, indexes, and foreign keys forward', function (): void {
        $schema = buildSchemaFromBuilderFixtures('Folding');

        expect($schema->hasTable('users'))->toBeFalse()
            ->and($schema->hasTable('members'))->toBeTrue();

        $members = $schema->table('members');

        expect($members?->hasColumn('name'))->toBeTrue()
            ->and($members?->hasColumn('email'))->toBeTrue();
    });

    it('does NOT rename an index carried over from before a table rename', function (): void {
        // Documents real, accurate database behaviour: MySQL's
        // `RENAME TABLE users TO members` does not rename an
        // auto-generated index name that was baked in while the table
        // was still called `users`. This is a genuine footgun worth
        // knowing about, not a bug to "fix" — the tool is correctly
        // reflecting what the database actually does.
        $members = buildSchemaFromBuilderFixtures('Folding')->table('members');

        $index = collect($members?->indexes())->firstWhere('columns', ['email']);

        expect($index)->not->toBeNull()
            ->and($index?->name)->toBe('users_email_unique');
    });

    it('does not resurrect a table that was created and dropped within the same migration set', function (): void {
        $schema = buildSchemaFromBuilderFixtures('Folding');

        expect($schema->hasTable('temp_import'))->toBeFalse();
    });

    it('does not manufacture a table out of a conditional alter with no prior create', function (): void {
        // Schema::table('never_declared', ...) appears in the fixture set
        // with no matching Schema::create() anywhere before it — this
        // must not cause the builder to invent a phantom table.
        $schema = buildSchemaFromBuilderFixtures('Folding');

        expect($schema->hasTable('never_declared'))->toBeFalse();
    });

    it("ignores Schema:: calls inside a migration's down() method entirely", function (): void {
        // The Columns fixture's down() calls Schema::dropIfExists('posts')
        // — if that were parsed, 'posts' would never appear in the schema
        // at all. Its presence here proves down() was correctly skipped.
        $schema = buildSchemaFromBuilderFixtures('Columns');

        expect($schema->hasTable('posts'))->toBeTrue();
    });

    it('updates foreign key references when a referenced table is renamed', function (): void {
        $schema = buildSchemaFromBuilderFixtures('Folding');

        $subscriptions = $schema->table('subscriptions');

        $fk = collect($subscriptions?->foreignKeys())
            ->firstWhere('column', 'user_id');

        expect($fk)->not->toBeNull()
            ->and($fk?->referencesTable)->toBe('members'); // users renamed to members
    });
});

describe('dropping constraints across migrations', function (): void {
    it('drops a foreign key by column-array form, matching the conventionally-generated name', function (): void {
        $orders = buildSchemaFromBuilderFixtures('DropForeign')->table('orders');

        expect($orders?->foreignKeys())->toBeEmpty();
    });

    it('drops a unique index by its explicit string name', function (): void {
        $products = buildSchemaFromBuilderFixtures('DropIndex')->table('products');

        expect($products?->indexes())->toBeEmpty();
    });
});
