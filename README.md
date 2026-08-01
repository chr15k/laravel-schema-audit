<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header.webp">
    <img alt="Logo for Laravel Schema Audit package" src="art/header.webp">
</picture>

<p></p>

<p align="center">
    <a href="https://github.com/chr15k/laravel-schema-audit/actions"><img alt="GitHub Workflow Status (master)" src="https://img.shields.io/github/actions/workflow/status/chr15k/laravel-schema-audit/main.yml"></a>
    <a href="https://packagist.org/packages/chr15k/laravel-schema-audit"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/chr15k/laravel-schema-audit"></a>
    <a href="https://packagist.org/packages/chr15k/laravel-schema-audit"><img alt="Latest Version" src="https://img.shields.io/packagist/v/chr15k/laravel-schema-audit"></a>
    <a href="https://packagist.org/packages/chr15k/laravel-schema-audit"><img alt="License" src="https://img.shields.io/github/license/chr15k/laravel-schema-audit"></a>
</p>

------

# Laravel Schema Audit

Laravel Schema Audit reconstructs your application's database schema from its migration history and checks it for structural problems — before they reach production.

It doesn't touch your database or run any traffic. Instead it reads your migrations, folds them into a final schema, and flags issues like duplicate indexes, redundant indexes, invalid or dangling foreign keys, mismatched foreign key types, and missing primary keys.

---

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13

---

## Installation

```bash
composer require chr15k/laravel-schema-audit --dev
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=schema-audit-config
```

---

## Usage

```bash
php artisan schema:audit
```

By default this reads `database/migrations` and prints a styled report of any findings, exiting non-zero if issues were found (CI-friendly).

```bash
# scan a different directory
php artisan schema:audit --path=/path/to/migrations

# machine-readable output
php artisan schema:audit --json

# print the raw folded schema instead of running rules — useful for
# debugging what the tool thinks your schema looks like
php artisan schema:audit --schema-only
```

> [!IMPORTANT]
> Schema Audit treats all provided migration paths as one database schema.
> Run separate audits for applications or connections with independent databases.

---

## What gets checked

| Rule | What it flags |
|---|---|
| `UnindexedForeignKeyRule` | A foreign key with no covering index. Driver-aware: MySQL/MariaDB auto-index FK columns, so this only fires on drivers where it's actually a problem (PostgreSQL, SQLite, SQL Server). |
| `DuplicateIndexRule` | The same index (same columns, same uniqueness) declared more than once. |
| `DuplicateForeignKeyRule` | The same foreign key (same column, same referenced table) declared more than once. |
| `RedundantIndexRule` | A single-column index already covered by a composite index's leading column. |
| `DanglingForeignKeyRule` | A foreign key referencing a table that doesn't exist anywhere in the schema — a typo, or a table renamed/dropped without updating the reference. |
| `MismatchedForeignKeyRule` | A foreign key whose column type doesn't match the referenced table's primary key type (e.g. `foreignId()` pointing at a plain `increments()` primary key). |
| `MissingPrimaryKeyRule` | A table with no identifiable primary key — no `id()`/`increments()`-style column and no explicit `primary()` call. |
| `InvalidReferenceKeyRule` | A foreign key referencing a column with no primary or unique key on the parent table. |

> [!NOTE]
> Rules run against the schema reconstructed from your migration history. For ordinary migrations, findings are concrete. Where runtime conditionals affect schema changes, affected findings are marked conditional, since the final schema can't be determined statically.

## Configuration

```php
// config/schema-audit.php
use Chr15k\SchemaAudit\Rules;

return [
    'paths' => [
        database_path('migrations'),
    ],
    'driver' => env('DB_CONNECTION', 'mysql'),
    'rules' => [
        Rules\UnindexedForeignKeyRule::class,
        Rules\DuplicateIndexRule::class,
        Rules\DuplicateForeignKeyRule::class,
        Rules\RedundantIndexRule::class,
        Rules\DanglingForeignKeyRule::class,
        Rules\MissingPrimaryKeyRule::class,
        Rules\MismatchedForeignKeyRule::class,
        Rules\InvalidReferencedKeyRule::class,
    ],
    'report_conditional_findings' => true,
];
```

> [!NOTE]
> `paths` — migration directories to analyze. Use `--path` to override for a single run.
>
> `driver` — target database driver. Some rules are driver-specific, such as whether foreign keys automatically create indexes.
>
> `report_conditional_findings` — when enabled, findings from tables modified inside runtime conditionals are reported with a note that they may be false positives. Disable to suppress them entirely.
>
> `rules` — enable, disable, or replace audit rules.

---

## Limitations

Schema Audit statically analyzes Laravel migrations — it doesn't connect to your database or execute migration code.

It understands schema declarations made through Laravel's Schema Builder, including:

* `Schema::create()`
* `Schema::table()`
* Blueprint column definitions
* Primary keys, indexes, and foreign keys

Because migrations aren't executed, it can't reliably evaluate arbitrary PHP logic, runtime conditions, dynamically generated schema definitions, or raw SQL schema changes. In these cases, findings may be marked **conditional**, meaning they could be false positives depending on the code path taken at runtime.

Schema Audit checks schema correctness, not runtime behavior — it doesn't analyze query performance, execution plans, or N+1 queries. For that, tools like Laravel Telescope, Debugbar, or query detectors are a better fit.

### Unsupported operations

- Raw SQL schema changes (`DB::statement()`, `DB::unprepared()`)
- Dynamically generated schema changes
- Schema changes hidden behind application logic

For example, this can't be reliably analyzed without a database-specific SQL parser:

```php
DB::statement('ALTER TABLE users MODIFY COLUMN name TEXT');
```

### Conditional migrations

Schema Audit understands common schema guards such as:

```php
if (! Schema::hasColumn('users', 'email')) {
    Schema::table('users', function (Blueprint $table) {
        $table->string('email');
    });
}
```

Operations guarded by conditions that can't be evaluated statically (e.g. `DB::getDriverName()`, `config()`, or application-specific logic) are still folded into the schema so analysis stays useful — but the affected tables are marked as conditionally modified, and their findings may be false positives depending on runtime execution.

Set `report_conditional_findings` to `false` to suppress these findings entirely.

### Multiple database connections

Schema Audit assumes all provided migration paths belong to the same database schema. If your application manages multiple databases or connections, run a separate audit for each.

---

## Writing custom rules

Any class implementing `Chr15k\SchemaAudit\Contracts\AuditRule` can be added to `config('schema-audit.rules')` alongside the built-in rules. For convenience, extend the abstract `Rule` class, which provides a `makeFinding()` method.

### Step 1 — create the rule

Each rule receives an `AuditContext`, containing the schema being analyzed and the findings accumulated so far. Treat the context as immutable: to add findings, return a new context via `withFindings()` and pass it to the next rule in the pipeline.

```php
public function handle(AuditContext $context, Closure $next): AuditContext
{
    $findings = [
        $this->makeFinding(
            table: 'users',
            message: 'Example finding.',
        ),
    ];

    return $next($context->withFindings($findings));
}
```

This lets rules run independently while sharing the same schema state and building up the final result.

#### Example

```php
<?php

declare(strict_types=1);

namespace App\SchemaRules;

use Chr15k\SchemaAudit\Rules\Rule;
use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Closure;

final readonly class NoTextColumnsOnHighTrafficTablesRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->columns() as $column) {
                if ($column->method === ColumnMethod::Text /* ...your condition... */) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        column: $column,
                        message: "Column '{$column}' is a text column on a high-traffic table.",
                    );
                }
            }
        }

        return $next($context->withFindings($findings));
    }
}
```

### Step 2 — publish the config file

```bash
php artisan vendor:publish --tag=schema-audit-config
```

### Step 3 — register the rule

```php
// config/schema-audit.php
return [
    // ...
    'rules' => [
        // ...
        \App\SchemaRules\NoTextColumnsOnHighTrafficTablesRule::class
    ],
];
```
