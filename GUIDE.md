# Laravel Schema Audit Guide

This guide covers how Schema Audit reconstructs migrations, handles conditional schema logic, and can be extended with custom rules.

## Supported Schema Operations

Schema Audit understands Laravel's Schema Builder, including:

* `Schema::create()`
* `Schema::table()`
* table renames
* Blueprint column definitions
* primary keys
* indexes and unique indexes
* foreign keys
* column renames and drops
* index and foreign-key drops
* common Laravel helpers such as `foreignId()`, `constrained()`, and related drop methods
* common conditional schema guards

Schema Audit folds these operations into a final in-memory representation of each table before running audit rules.

## Static Value Resolution

Migration code often uses values that are not literal strings. Where values can be determined statically, Schema Audit resolves common Laravel conventions such as model methods, configuration values, helper methods, and simple variables.

For example:

```php
Schema::create((new User())->getTable(), function (Blueprint $table) {
    //
});

Schema::create(config('permission.table_names.roles'), function (Blueprint $table) {
    //
});

$tableName = 'users';

Schema::create($tableName, function (Blueprint $table) {
    //
});
```

The goal is to support common migration patterns without requiring table names and other schema values to be hard-coded string literals.

## Conditional Migrations

Laravel migrations are executable PHP rather than declarative schema definitions. Some schema changes therefore depend on runtime conditions that cannot be determined statically.

Schema Audit understands common schema guards such as:

```php
if (! Schema::hasColumn('users', 'email')) {
    Schema::table('users', function (Blueprint $table) {
        $table->string('email');
    });
}
```

When a guard can be resolved statically, Schema Audit models the resulting schema accordingly.

When a condition cannot be resolved, Schema Audit uses a conservative best-effort approach. It may preserve destructive operations while avoiding structures whose existence cannot be established, such as conditionally-created indexes or foreign keys.

For example:

```php
if (someApplicationSpecificCheck()) {
    Schema::table('users', function (Blueprint $table) {
        $table->string('email')->index();
    });
}
```

The column can still be represented in the reconstructed schema, while the conditional index is not assumed to exist unconditionally.

Findings affected by conditional schema changes are marked **conditional** so they can be distinguished from findings based on a deterministic schema.

Set:

```php
'report_conditional_findings' => false,
```

to suppress conditional findings entirely.

## Unsupported Operations

Some schema changes cannot be reconstructed safely without executing application code or parsing database-specific SQL.

Examples include:

* raw SQL schema changes
* database-specific DDL
* arbitrary PHP execution
* runtime-generated SQL
* values that cannot be statically resolved

For example:

```php
DB::statement('ALTER TABLE users MODIFY COLUMN name TEXT');
```

or:

```php
Schema::create(generateTableName(), function (Blueprint $table) {
    //
});
```

These operations are intentionally ignored rather than guessed.

## Multiple Database Connections

Schema Audit assumes all supplied migration paths belong to the same logical database schema.

If your application manages multiple databases or independent connections, run a separate audit for each schema.

For example:

```bash
php artisan schema:audit --path=database/migrations
php artisan schema:audit --path=database/migrations/tenant
```

Treat each audit as a separate schema boundary rather than combining migrations belonging to independent databases.

## Writing Custom Rules

Custom rules can be added to `config('schema-audit.rules')`.

### Creating a Rule

A rule receives an `AuditContext` containing the reconstructed schema and findings accumulated by previous rules.

Rules implement `check()` and return an `iterable` of findings:

```php
<?php

declare(strict_types=1);

namespace App\SchemaRules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Rules\Rule;

final readonly class NoTextColumnsOnHighTrafficTablesRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            if (! in_array($table->name, ['orders', 'events', 'sessions'], true)) {
                continue;
            }

            foreach ($table->columns() as $column) {
                if ($column->method === ColumnMethod::Text) {
                    yield $this->warning(
                        table: $table,
                        columns: $column->name,
                        message: "Avoid TEXT columns on high-traffic tables.",
                        location: $column->location,
                        guard: $column->guard,
                    );
                }
            }
        }
    }
}
```

Use `yield` to produce findings as they are discovered. This allows a rule to stream findings without first building and returning a findings array.

The rule should not modify the `AuditContext`. The audit pipeline collects the findings returned by `check()` and adds them to the context before passing it to the next rule.

### Registering a Rule

Add the rule to the `rules` array in `config/schema-audit.php`:

```php
return [
    // ...

    'rules' => [
        // ...
        App\SchemaRules\NoTextColumnsOnHighTrafficTablesRule::class,
    ],
];
```

Once registered, the rule runs alongside the built-in audit rules.

### Rule Context

The `AuditContext` provides access to the reconstructed schema and findings accumulated by previous rules.

A rule should generally:

* inspect $context->schema
* yield findings as they are discovered
* avoid modifying the context directly

The audit pipeline is responsible for collecting the findings and passing the updated context to the next rule. This keeps individual rules focused on detecting schema issues rather than managing pipeline state.

## Configuration Reference

The complete configuration is available in `config/schema-audit.php`.

### `paths`

Migration directories to analyze.

```php
'paths' => [
    database_path('migrations'),
],
```

Multiple paths can be supplied when they belong to the same logical database schema.

The `--path` CLI option overrides the configured paths for a single audit.

### `driver`

The database driver the reconstructed schema targets.

```php
'driver' => env('DB_CONNECTION', 'mysql'),
```

Some rules are driver-aware. For example, whether an unindexed foreign key is considered a problem depends on the database engine's indexing behaviour.

### `rules`

The rules executed during an audit.

```php
'rules' => [
    Rules\UnindexedForeignKeyRule::class,
    Rules\DuplicateIndexRule::class,
    // ...
],
```

Rules can be removed, reordered, or replaced with custom implementations.

### `report_conditional_findings`

Controls whether findings affected by conditional schema logic are reported.

```php
'report_conditional_findings' => true,
```

Set this to `false` to suppress conditional findings while continuing to analyze the rest of the schema.
