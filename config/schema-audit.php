<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Rules\DanglingForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateForeignKeyRule;
use Chr15k\SchemaAudit\Rules\DuplicateIndexRule;
use Chr15k\SchemaAudit\Rules\NoPrimaryKeyRule;
use Chr15k\SchemaAudit\Rules\RedundantSingleColumnIndexRule;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;

return [

    /*
    |--------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------
    |
    | Default directory schema:audit scans when --path isn't passed on
    | the command line. Relative to the application base path.
    |
    */
    'path' => 'database/migrations',

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
    | Chr15k\SchemaAudit\Contracts\Rule.
    |
    */
    'rules' => [
        UnindexedForeignKeyRule::class,
        DuplicateIndexRule::class,
        DuplicateForeignKeyRule::class,
        RedundantSingleColumnIndexRule::class,
        DanglingForeignKeyRule::class,
        NoPrimaryKeyRule::class,
    ],

];
