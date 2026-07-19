<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
use Chr15k\SchemaAudit\Contracts\Rule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

final class SchemaAuditServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'schema-audit';

    public function register(): void
    {
        $this->app->singleton(MigrationParser::class);
        $this->app->singleton(SchemaBuilder::class);

        $this->app->singleton(Rules\DuplicateIndexRule::class);
        $this->app->singleton(Rules\RedundantSingleColumnIndexRule::class);
        $this->app->singleton(Rules\DanglingForeignKeyRule::class);
        $this->app->singleton(Rules\NoPrimaryKeyRule::class);

        $this->app->bind(Rules\UnindexedForeignKeyRule::class,
            fn (Container $app): Rules\UnindexedForeignKeyRule => new Rules\UnindexedForeignKeyRule(
                driver: $app->make('config')->get('database.default')
            )
        );

        $this->app->tag([
            Rules\DuplicateIndexRule::class,
            Rules\RedundantSingleColumnIndexRule::class,
            Rules\DanglingForeignKeyRule::class,
            Rules\NoPrimaryKeyRule::class,
            Rules\UnindexedForeignKeyRule::class,
        ], Rule::class);

        $this->app->bind(SchemaAuditor::class,
            fn (Container $app): SchemaAuditor => new SchemaAuditor($app->tagged(Rule::class))
        );
    }

    public function boot(): void
    {
        $this->publishes([
            $this->configDirectory() => config_path($this->configFile()),
        ], self::CONFIG_KEY.'-config');

        $this->mergeConfigFrom($this->configDirectory(), self::CONFIG_KEY);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditSchemaCommand::class,
            ]);
        }
    }

    private function configDirectory(): string
    {
        return sprintf('%s/../config/%s', __DIR__, $this->configFile());
    }

    private function configFile(): string
    {
        return self::CONFIG_KEY.'.php';
    }
}
