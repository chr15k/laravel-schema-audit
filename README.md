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

Laravel Schema Audit statically reconstructs your application's schema from its migration history and audits it for structural problems before they reach production.

Unlike runtime tools, it doesn't need a database connection or application traffic. It analyzes your migrations, builds the final schema they describe, and reports structural issues including duplicate indexes, redundant indexes, invalid foreign keys, missing primary keys, mismatched foreign key types, and other schema inconsistencies before they reach production.

---

## Why use Schema Audit?

Schema Audit reconstructs your application's schema from its migration history
and checks it for structural problems before they reach production.

Unlike tools that observe executed queries, Schema Audit works entirely from
your migrations, making it suitable for CI pipelines and projects where
production traffic may never exercise every code path.

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

By default this reads `database/migrations` and prints a styled report
of any findings, exiting non-zero if issues were found (CI-friendly).

```bash
# scan a different directory
php artisan schema:audit --path=/path/to/migrations

# machine-readable output
php artisan schema:audit --json

# print the raw folded schema instead of running rules — useful for
# debugging what the tool actually thinks your schema looks like
php artisan schema:audit --schema-only
```

> [!IMPORTANT]
> Schema Audit treats all provided migration paths as belonging to a single database schema.
> Use separate audit runs for applications or connections with independent databases.

---

## What this package checks

| Rule | What it flags |
|---|---|
| `UnindexedForeignKeyRule` | A foreign key column with no covering index. Driver-aware — MySQL/MariaDB auto-index FK columns, PostgreSQL/SQLite/SQL Server do not, so this only fires where it's actually true for your configured driver. |
| `DuplicateIndexRule` | The same index (same columns, same uniqueness) declared more than once. |
| `DuplicateForeignKeyRule` | The same foreign key (same column, same referenced table) declared more than once. |
| `RedundantIndexRule` | A single-column index already covered by a composite index's leading column. |
| `DanglingForeignKeyRule` | A foreign key referencing a table that doesn't exist anywhere in the folded schema — a typo, or a table renamed/dropped without updating the reference. |
| `MismatchedForeignKeyRule` | A foreign key whose column type doesn't match the type family of the referenced table's primary key (e.g. `foreignId()` pointing at a plain `increments()` primary key). |
| `MissingPrimaryKeyRule` | A table with no identifiable primary key — no `id()`/`increments()`-style column and no explicit `primary()` call. |
| `InvalidReferenceKeyRule` | A foreign key referencing a column that is not protected by a primary or unique key on the parent table. |

> [!NOTE]
> Rules are evaluated against the schema reconstructed from your migration history. For ordinary migrations,
> findings represent concrete inconsistencies. When runtime conditionals influence schema changes, affected
> findings are marked as conditional because the final schema cannot be determined statically.

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
> `paths` — migration directories to analyse. Use --path to override the configured paths for a single audit.
>
> `driver` — driver — target database driver. Some rules are database-specific, such as whether foreign keys automatically create indexes.
>
> `report_conditional_findings` — when enabled, findings originating from tables modified inside runtime conditionals are
> reported with a note explaining that they may be false positives. Disable to suppress those findings entirely.
>
> `rules` — enable, disable or replace audit rules.

---

## Limitations

Schema Audit statically analyzes Laravel migrations to reconstruct your application's schema. It does not connect to your database or execute migration code.

It understands schema declarations made through Laravel's Schema Builder, including:

* `Schema::create()`
* `Schema::table()`
* Blueprint column definitions
* Primary keys
* Indexes
* Foreign keys

Because migrations are not executed, Schema Audit cannot reliably evaluate arbitrary PHP logic, runtime conditions, dynamically generated schema definitions, or raw SQL schema changes. In these situations, findings may be marked as **conditional**, indicating they could represent false positives depending on the code path taken at runtime.

Schema Audit focuses on schema correctness rather than runtime behavior. It does not analyze query performance, execution plans, or N+1 queries. For runtime diagnostics, tools such as Laravel Telescope, Debugbar, or query detectors are more appropriate.

### Unsupported Operations

Unsupported operations include:

- Raw SQL schema changes (`DB::statement()`, `DB::unprepared()`)
- Dynamically generated schema changes
- Schema changes hidden behind application logic

For example:

```php
DB::statement('ALTER TABLE users MODIFY COLUMN name TEXT');
```

cannot be reliably analyzed without implementing a database-specific SQL parser.

### Conditional Migrations

Schema Audit understands Laravel's common schema guards such as:

```php
if (! Schema::hasColumn('users', 'email')) {
    Schema::table('users', function (Blueprint $table) {
        $table->string('email');
    });
}
```

Operations guarded by conditions that cannot be evaluated statically (for example DB::getDriverName(), config(), or application-specific logic) are still folded into the schema so that analysis remains useful.

Tables affected by these runtime conditionals are marked as conditionally modified. Findings involving those tables may represent false positives because the exact schema depends on runtime execution.

Conditional findings can be suppressed entirely using the report_conditional_findings configuration option.

### Multiple Database Connections

Schema Audit assumes all provided migration paths belong to the same database schema.

If your application manages multiple databases or connections, run separate audits for each schema.

---

## Writing custom rules

Any class implementing `Chr15k\SchemaAudit\Contracts\AuditRule` can be added to
`config('schema-audit.rules')` alongside the built-in rules. For convenience,
extend the abstract `Rule` class, which provides `makeFinding()` method:

### Step 1 - Create custom rule

#### Audit Context

Each rule receives an instance of `AuditContext`, which contains the current
schema being analyzed and the accumulated audit results.

Rules should treat the context as immutable. To add findings, return a new
context instance using `withFindings()` and pass it to the next rule in the
pipeline.

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

The context allows rules to run independently while sharing the same schema
state and progressively building the final SchemaAudit result.

### Sample custom rule

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

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
```

### Step 2 - Publish config file

```bash
php artisan vendor:publish --tag=schema-audit-config
```

### Step 3 - Add custom rule to rules array

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
