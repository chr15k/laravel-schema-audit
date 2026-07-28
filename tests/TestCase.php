<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Tests;

use Chr15k\SchemaAudit\SchemaAuditServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            SchemaAuditServiceProvider::class
        ];
    }
}
