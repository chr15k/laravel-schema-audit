<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\Schema\NameResolver;
use Chr15k\SchemaAudit\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

final class SchemaAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configDirectory(), Config::KEY);

        $this->app->singleton(MigrationParser::class);
        $this->app->singleton(SchemaBuilder::class);

        $this->app->singleton(Rules\DuplicateIndexRule::class);
        $this->app->singleton(Rules\RedundantSingleColumnIndexRule::class);
        $this->app->singleton(Rules\DanglingForeignKeyRule::class);
        $this->app->singleton(Rules\NoPrimaryKeyRule::class);

        $this->app->singleton(NameResolver::class);

        $this->app->singleton(Config::class,
            fn (Container $app): Config => new Config($app->make(Repository::class))
        );

        $this->app->bind(UnindexedForeignKeyRule::class,
            fn (Container $app): UnindexedForeignKeyRule => new UnindexedForeignKeyRule(
                $app->make(Config::class)->driver()
            )
        );

        $this->app->bind(SchemaAuditor::class,
            fn (Container $app): SchemaAuditor => new SchemaAuditor($app->make(Config::class)->rules())
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configDirectory() => config_path($this->configFile()),
            ], Config::KEY.'-config');

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
        return Config::KEY.'.php';
    }
}
