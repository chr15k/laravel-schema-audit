<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Schema\Resolvers\ColumnResolver;
use Chr15k\SchemaAudit\Schema\Resolvers\ForeignKeyResolver;
use Chr15k\SchemaAudit\Schema\Resolvers\IndexResolver;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/


/*
|--------------------------------------------------------------------------
| SchemaBuilder test helpers
|--------------------------------------------------------------------------
|
| SchemaBuilder and everything it depends on (MigrationParser, the three
| Resolvers, LaravelConventions) are plain PHP with no container/framework
| dependency — deliberately constructed by hand here rather than resolved
| through Testbench, so this whole suite stays fast and Laravel-app-free.
| Only reach for Testbench in tests that actually exercise the Artisan
| command, service provider, or config binding.
|
*/

/**
 * @return SchemaBuilder
 */
function schemaBuilder(): SchemaBuilder
{
    $conventions = new LaravelConventions;

    return new SchemaBuilder(
        new MigrationParser,
        new IndexResolver($conventions),
        new ForeignKeyResolver($conventions),
        new ColumnResolver($conventions),
        $conventions,
    );
}

/**
 * Build a folded Schema from every .php file in a fixture directory,
 * relative to tests/Fixtures/Migrations, sorted the same way real
 * migration filenames sort (timestamp-prefixed => chronological).
 */
function buildSchemaFromFixtures(string $relativeDir): Schema
{
    $path = __DIR__.'/Fixtures/Migrations/'.trim($relativeDir, '/');
    $files = glob(rtrim($path, '/').'/*.php') ?: [];
    sort($files);

    return schemaBuilder()->build($files);
}