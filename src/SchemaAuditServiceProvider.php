<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
use Chr15k\SchemaAudit\Contracts\Rule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class SchemaAuditServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'schema-audit';

    public function register(): void
    {
        $this->mergeConfigFrom($this->configDirectory(), self::CONFIG_KEY);

        $this->app->singleton(MigrationParser::class);
        $this->app->singleton(SchemaBuilder::class);

        $this->app->singleton(Rules\DuplicateIndexRule::class);
        $this->app->singleton(Rules\RedundantSingleColumnIndexRule::class);
        $this->app->singleton(Rules\DanglingForeignKeyRule::class);
        $this->app->singleton(Rules\NoPrimaryKeyRule::class);

        $this->app->bind(Rules\UnindexedForeignKeyRule::class,
            fn (Container $app): Rules\UnindexedForeignKeyRule => new Rules\UnindexedForeignKeyRule(
                driver: $app->make('config')->get('schema-audit.driver')
            )
        );

        $this->app->bind(SchemaAuditor::class, function (Container $app): SchemaAuditor {
            $ruleClasses = $app->make('config')->get('schema-audit.rules', []);
            $rules = [];

            foreach ($ruleClasses as $ruleClass) {
                $rule = $app->make($ruleClass);

                if (! $rule instanceof Rule) {
                    throw new RuntimeException(
                        sprintf("config('schema-audit.rules') entry [%s] does not implement %s", $ruleClass, Rule::class)
                    );
                }

                $rules[] = $rule;
            }

            return new SchemaAuditor($rules);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configDirectory() => config_path($this->configFile()),
            ], self::CONFIG_KEY.'-config');

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
