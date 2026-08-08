<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Parsers\ArgReader;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SchemaOperation;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;

describe('literal arguments', function (): void {
    it('reads a literal table name', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            Schema::create('posts', function (Blueprint $table): void {});
        PHP))->toBe('posts');
    });

    it('reads multiple table names from an array literal', function (): void {
        expect(argReaderStringsArg(<<<'PHP'
            Schema::create(['users', 'members'], function (Blueprint $table): void {});
        PHP))->toBe(['users', 'members']);
    });

    it('returns null for stringArgAt when multiple names are resolved', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            Schema::create(['users', 'members'], function (Blueprint $table): void {});
        PHP))->toBeNull();
    });

    it('ignores non-string array elements', function (): void {
        expect(argReaderStringsArg(<<<'PHP'
            Schema::create(['users', 123, 'members'], function (Blueprint $table): void {});
        PHP))->toBe(['users', 'members']);
    });

    it('returns an empty list when the argument position is missing', function (): void {
        $call = parseSchemaStaticCalls(<<<'PHP'
            Schema::drop('users');
        PHP)[0];

        expect(ArgReader::stringsArgAt($call->args, 99, $call))->toBeEmpty();
    });
});

describe('dynamic helper calls', function (): void {
    it('resolves config() calls to the final key segment', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            Schema::create(config('permission.table_names.roles'), function (Blueprint $table): void {});
        PHP))->toBe('roles');
    });

    it('resolves collect() with a string argument', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            Schema::create(collect('users'), function (Blueprint $table): void {});
        PHP))->toBe('users');
    });

    it('resolves collect() with an array argument', function (): void {
        expect(argReaderStringsArg(<<<'PHP'
            Schema::create(collect(['users', 'members']), function (Blueprint $table): void {});
        PHP))->toBe(['users', 'members']);
    });

    it('resolves custom static table() helpers', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            final class Models
            {
                public static function table(string $name): string
                {
                    return $name;
                }
            }

            Schema::create(Models::table('abilities'), function (Blueprint $table): void {});
        PHP))->toBe('abilities');
    });

    it('derives table names from model classes passed to getTable()', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            final class BlogPost {}

            Schema::create((new BlogPost)->getTable(), function (Blueprint $table): void {});
        PHP))->toBe('blog_posts');
    });

    it('ignores unsupported static and method calls', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            final class Models
            {
                public static function other(string $name): string
                {
                    return $name;
                }
            }

            final class BlogPost
            {
                public function other(): string
                {
                    return 'ignored';
                }
            }

            Schema::create(Models::other('ignored'), function (Blueprint $table): void {});
            Schema::create((new BlogPost)->other(), function (Blueprint $table): void {});
        PHP, callIndex: 0))->toBeNull()
            ->and(argReaderStringArg(<<<'PHP'
            final class BlogPost
            {
                public function other(): string
                {
                    return 'ignored';
                }
            }

            Schema::create((new BlogPost)->other(), function (Blueprint $table): void {});
        PHP))->toBeNull();
    });

    it('ignores dynamic function calls', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            $helper = 'collect';

            Schema::create($helper(['users']), function (Blueprint $table): void {});
        PHP))->toBeNull();
    });
});

describe('variable resolution', function (): void {
    it('resolves a previously assigned variable', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            final class MigrationHarness
            {
                public function up(): void
                {
                    $tableName = 'users';

                    Schema::create($tableName, function (Blueprint $table): void {});
                }
            }
        PHP))->toBe('users');
    });

    it('resolves foreach value variables from the iterated expression', function (): void {
        expect(argReaderStringsArg(<<<'PHP'
            foreach (['users', 'members'] as $table) {
                Schema::create($table, function (Blueprint $table): void {});
            }
        PHP))->toBe(['users', 'members']);
    });

    it('resolves variables passed to collect()->each() callbacks', function (): void {
        expect(argReaderStringsArg(<<<'PHP'
            collect(['users', 'members'])->each(function ($table): void {
                Schema::create($table, function (Blueprint $table): void {});
            });
        PHP))->toBe(['users', 'members']);
    });

    it('resolves variables assigned from collect() before use', function (): void {
        expect(argReaderStringArg(<<<'PHP'
            $tables = collect(['users']);

            Schema::create($tables->first(), function (Blueprint $table): void {});
        PHP))->toBeNull();
    });

    it('returns an empty list when no context node is provided', function (): void {
        $call = parseSchemaStaticCalls(<<<'PHP'
            $tableName = 'users';

            Schema::create($tableName, function (Blueprint $table): void {});
        PHP)[0];

        expect(ArgReader::stringsArgAt($call->args, 0))->toBeEmpty();
    });
});

describe('closure arguments', function (): void {
    it('returns the blueprint closure passed to Schema::create()', function (): void {
        /** @var StaticCall $call */
        $call = parseSchemaStaticCalls(<<<'PHP'
            Schema::create('posts', function (Blueprint $table): void {
                $table->id();
            });
        PHP)[0];

        $closure = ArgReader::closureArgAt($call->args, 1);

        expect($closure)->toBeInstanceOf(Closure::class);
    });

    it('returns null when the second argument is not a closure', function (): void {
        /** @var StaticCall $call */
        $call = parseSchemaStaticCalls(<<<'PHP'
            Schema::create('posts', 'not-a-closure');
        PHP)[0];

        expect(ArgReader::closureArgAt($call->args, 1))->toBeNull();
    });
});

describe('integration through MigrationParser', function (): void {
    it('records dynamically resolved create operations from migration fixtures', function (): void {
        $operations = app(MigrationParser::class)
            ->parseFile(__DIR__.'/../../Fixtures/Migrations/SchemaBuilder/DynamicCreates/2024_01_01_000000_create_dynamic_tables.php');

        $tables = array_map(
            fn (SchemaOperation $operation): string => $operation->tableName,
            array_values(array_filter(
                $operations,
                fn (SchemaOperation $operation): bool => $operation->type->value === 'create',
            )),
        );

        expect($tables)->toContain('abilities', 'users', 'roles');
    });
});
