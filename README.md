# Laravel Schema Audit

Static analysis that cross-references your Eloquent query usage against
your migration-declared schema — catching unindexed columns, unindexed
foreign keys, and composite-index mismatches before they ship.

No database connection required. No test traffic required. It reads your
migration files and your model/query source, and tells you where they
disagree.

## Why this over a runtime query monitor?

Tools like Laravel Debugbar, Telescope, or `laravel-query-detector` are
genuinely better than this package at one specific job: **catching N+1
query storms as they actually happen.** They watch real query execution,
so when they flag something, it's a real problem with real timing and
real row counts — zero false positives, by construction. If N+1 detection
is what you need, use one of those instead of this.

This package solves a different, narrower problem that runtime tools
structurally can't:

**Runtime monitors only see code that runs.** A rarely-hit admin report,
a seasonal batch job, a conditional branch nobody's exercised in staging
— none of that shows up in a query monitor until someone actually
triggers it, possibly in production, possibly under load. Static
analysis reads the source directly, so it doesn't need the code path to
execute to flag a problem with it.

**Runtime monitors tell you a query was slow. They don't tell you why.**
Debugbar shows you timing. It doesn't cross-reference the column you
filtered on against your migration history to tell you it was never
indexed. You still have to make that connection yourself, usually after
the fact, usually in production.

**This runs in CI, before merge.** Point it at a pull request's changed
models and migrations and it can flag a missing index before the code
ships — not after a slow query shows up in Debugbar three weeks later.

### What this package checks

1. **Unindexed filter/sort columns** — `where()`, `orderBy()`,
   `whereIn()`, `firstWhere()` calls on a column with no index anywhere
   in your folded migration history.
2. **Unindexed foreign keys** — same check, specifically for foreign key
   columns used in `where()`/`whereHas()`. Driver-aware: MySQL/InnoDB
   auto-indexes foreign key columns, Postgres and SQLite do not — this
   package only flags what's actually unindexed on your configured
   driver, not every bare `foreignId()` regardless of database.
3. **Composite-index shape mismatches** — `where('a', ...)->where('b', ...)`
   where no index covers `(a, b)` in that order at all. Deliberately
   narrow: this only flags a genuine absence, not "this index could be
   better," to keep false positives near zero.

### What this package deliberately does NOT check

- **N+1 query patterns.** Existing runtime tools already solve this well
  with real execution data; a static approximation would just be a
  weaker version of a solved problem.
- **Query timing, row counts, or execution plans.** These are runtime
  facts. This tool only knows what your migrations and source code say
  — it has no idea what your data actually looks like.
- **Whether an index is a *good* index** — just whether one exists that
  covers the columns you're filtering on.

### Similar tools, and how this differs

- **[intentphp/guard](https://github.com/drnasin)** and PHPStan/Larastan
  rules already cover mass-assignment (`$fillable`/`$guarded`) auditing
  well — this package doesn't attempt that.
- **Debugbar / Telescope / `laravel-query-detector`** cover runtime N+1
  and slow-query detection well — see above, this package intentionally
  doesn't compete there.
- Nothing found at the time of writing does static, migration-aware
  index checking — that's the actual gap this package fills.

## Installation

```bash
composer require chr15k/laravel-schema-audit --dev
```

## Usage

```bash
php artisan schema:audit
```

By default this reads `database/migrations`. Point it elsewhere with:

```bash
php artisan schema:audit --path=/absolute/or/relative/path
```

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

## Honest limitations

- This tool works entirely from source — it does not run your
  migrations or connect to a database. If your schema was changed
  outside of a migration (a manual `ALTER TABLE`, a seeder, a rogue DBA),
  this tool won't know about it.
- Conditional migration logic (e.g. `if (DB::getDriverName() === 'mysql')`)
  is read as written — the tool can't resolve which branch is "true" for
  your environment, it just sees whatever source is there.
- This is not a replacement for `EXPLAIN`, query profiling, or an actual
  DBA review. It catches a specific, narrow class of static mismatch —
  nothing more.
