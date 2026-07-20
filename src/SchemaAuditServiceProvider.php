<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Console\Commands\AuditSchemaCommand;
use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Rules\UnindexedForeignKeyRule;
use Chr15k\SchemaAudit\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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

        $this->app->singleton(Config::class, fn (Container $app): Config => new Config($app->make(Repository::class)));

        $this->app->bind(UnindexedForeignKeyRule::class,
            fn (Container $app): UnindexedForeignKeyRule => new UnindexedForeignKeyRule($app->make(Config::class)->driver())
        );

        $this->app->bind(SchemaAuditor::class, function (Container $app): SchemaAuditor {
            $ruleClasses = $app->make(Config::class)->rules();
            $rules = [];

            foreach ($ruleClasses as $ruleClass) {
                if (! is_string($ruleClass)) {
                    throw new InvalidArgumentException(
                        sprintf('Configuration value for key [%s.rules] must be a string, %s given.', Config::KEY, gettype($ruleClass))
                    );
                }

                if (! class_exists($ruleClass)) {
                    throw new InvalidArgumentException(
                        sprintf('Configuration value for key [%s.rules] must be a defined class, %s given.', Config::KEY, $ruleClass)
                    );
                }

                $rule = $app->make($ruleClass);

                if (! $rule instanceof Rule) {
                    throw new InvalidArgumentException(
                        sprintf('Configuration value for key [%s.rules] does not implement %s, %s given.', Config::KEY, Rule::class, $ruleClass)
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
