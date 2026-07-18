<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Commands\AuditSchemaCommand;
use Illuminate\Support\ServiceProvider;

final class SchemaAuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditSchemaCommand::class,
            ]);
        }
    }
}
