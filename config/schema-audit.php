<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Rules;

return [

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    |
    | Directories containing migration files to analyse. The audit command
    | will scan these paths and build the final schema state before running
    | the configured rules.
    |
    */
    'paths' => [
        database_path('migrations'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------------
    |
    | The target database driver affects database-specific behaviour and
    | which rules are applicable. Defaults to the application's configured
    | connection driver.
    |
    */
    'driver' => env('SCHEMA_AUDIT_DRIVER', env('DB_CONNECTION', 'mysql')),

    /*
    |--------------------------------------------------------------------------
    | Enabled Rules
    |--------------------------------------------------------------------------
    |
    | Rule classes that will be executed during schema audits. Remove a rule
    | to disable it, or add custom rules that implement the AuditRule contract.
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
        Rules\InvalidReferencedKeyRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Conditional Findings
    |--------------------------------------------------------------------------
    |
    | Some migration logic cannot be resolved statically, such as schema
    | changes inside runtime conditionals. Findings affected by these cases
    | are marked as conditional and may represent false positives.
    |
    | Set this option to true to include conditional findings in the report.
    | These will be marked with a yellow "Note" message explaining the uncertainty.
    |
    */
    'report_conditional_findings' => env('SCHEMA_AUDIT_REPORT_CONDITIONAL_FINDINGS', false),

];
