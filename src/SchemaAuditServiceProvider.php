<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
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
