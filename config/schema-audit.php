<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Rules;

return [

    /*
    |--------------------------------------------------------------------
    | Migrations Paths
    |--------------------------------------------------------------------
    |
    | Migration directories schema:audit scans.
    |
    */
    'paths' => [
        database_path('migrations'),
    ],

    /*
    |--------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------
    |
    | Affects which rules apply — most notably UnindexedForeignKeyRule,
    | since MySQL/MariaDB auto-index foreign key columns and Postgres/
    | SQLite/SQL Server do not. Defaults to the app's configured
    | connection so this stays correct without duplicating it here;
    | override with --driver on the command line for a one-off check
    | against a different target database.
    |
    */
    'driver' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------
    | Enabled Rules
    |--------------------------------------------------------------------
    |
    | Rule classes to run. All five ship enabled by default. Remove an
    | entry to disable that rule without touching the service provider.
    | Custom rules can be added here too, as long as they implement
    | Chr15k\SchemaAudit\Contracts\AuditRule.
    |
    */
    'rules' => [
        Rules\UnindexedForeignKeyRule::class,
        Rules\DuplicateIndexRule::class,
        Rules\DuplicateForeignKeyRule::class,
        Rules\RedundantIndexRule::class,
        Rules\DanglingForeignKeyRule::class,
        Rules\MissingPrimaryKeyRule::class,
        Rules\MismatchedForeignKeyRule::class,
        Rules\MissingReferencedKeyRule::class,
    ],

];
