<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

final class SchemaAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configDirectory(), Config::KEY);

        $this->app->singleton(Config::class,
            fn (Container $app): Config => new Config($app->make(Repository::class))
        );

        $this->app->singleton(
            LaravelConventions::class,
            function (Container $app): LaravelConventions {
                $config = $app->make(Config::class);
                $connection = $config->connection();

                return new LaravelConventions(
                    prefixIndexes: (bool) ($connection['prefix_indexes'] ?? true),
                    tablePrefix: is_string($prefix = $connection['prefix'] ?? '') ? $prefix : ''
                );
            }
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
