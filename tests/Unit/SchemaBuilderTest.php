<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

function writeTempMigration(string $directory, string $name, string $content): string
{
    $path = $directory.'/'.$name;
    file_put_contents($path, $content);

    return $path;
}

it('folds create and later alter migrations for the same table', function (): void {
    $schema = app(SchemaBuilder::class)->buildFromDirectory(migrations_path());

    expect($schema->tables())->toHaveKey('users');
    expect($schema->tables())->toHaveCount(1);

    $users = $schema->tables()['users'];

    expect($users->hasPrimaryKey())->toBeTrue();

    expect($users->hasColumn('name'))->toBeTrue();
    expect($users->hasColumn('email'))->toBeTrue();
    expect($users->hasColumn('team_id'))->toBeTrue();

    expect($users->hasColumn('nickname'))->toBeFalse();

    expect($users->isIndexed('email'))->toBeTrue();
    expect($users->indexes())->toHaveCount(1);
    expect(collect($users->indexes())->contains(fn (Index $index): bool => $index->columns === ['email'] && $index->unique))->toBeTrue();

    expect($users->isIndexed('name'))->toBeFalse();
    expect($users->isIndexed('team_id'))->toBeFalse();

    expect($users->foreignKeys())->toHaveCount(1);
    expect($users->foreignKeys()[0]->column)->toBe('team_id');
    expect($users->foreignKeys()[0]->referencesTable)->toBeNull();
});

it('honours chained column modifiers such as primary on a string column', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('chain-primary_', true);
    mkdir($path, 0700, true);

    $file = $path.'/0001_create_keys_table.php';
    file_put_contents($file, <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keys', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->constrained()->index();
        });
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);
        $keys = $schema->tables()['keys'];

        expect($keys->hasPrimaryKey())->toBeTrue();
        expect($keys->hasColumn('key'))->toBeTrue();
        expect($keys->isIndexed('slug'))->toBeTrue();
        expect($keys->indexes())->toHaveCount(2);
        expect($keys->foreignKeys())->toHaveCount(1);
        expect($keys->foreignKeys()[0]->column)->toBe('user_id');
    } finally {
        unlink($file);
        rmdir($path);
    }
});

it('supports old style foreign key definitions and table-level unique indexes', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('table-level_', true);
    mkdir($path, 0700, true);

    $file = $path.'/0001_create_orders_table.php';
    file_put_contents($file, <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->foreign('user_id', 'fk_orders_user_id');
            $table->unique('email', 'orders_email_unique');
        });
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);
        $orders = $schema->tables()['orders'];

        expect($orders->hasPrimaryKey())->toBeTrue();
        expect($orders->isIndexed('email'))->toBeTrue();
        expect(collect($orders->indexes())->contains(fn (Index $index): bool => $index->columns === ['email'] && $index->unique && $index->name === 'orders_email_unique'))->toBeTrue();
        expect($orders->foreignKeys())->toHaveCount(1);
        expect($orders->foreignKeys()[0]->column)->toBe('user_id');
        expect($orders->foreignKeys()[0]->name)->toBe('fk_orders_user_id');
    } finally {
        unlink($file);
        rmdir($path);
    }
});

it('preserves table rename operations while carrying over columns, indexes, and foreign keys', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('rename-table_', true);
    mkdir($path, 0700, true);

    $createFile = writeTempMigration($path, '0001_create_profiles_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('team_id')->constrained();
        });
    }
};
PHP);

    $renameFile = writeTempMigration($path, '0002_rename_profiles_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('profiles', 'accounts');
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);

        expect($schema->tables())->toHaveKey('accounts');
        expect($schema->tables())->not->toHaveKey('profiles');

        $accounts = $schema->tables()['accounts'];

        expect($accounts->hasPrimaryKey())->toBeTrue();
        expect($accounts->hasColumn('email'))->toBeTrue();
        expect($accounts->isIndexed('email'))->toBeTrue();
        expect($accounts->foreignKeys())->toHaveCount(1);
        expect($accounts->foreignKeys()[0]->column)->toBe('team_id');
    } finally {
        unlink($createFile);
        unlink($renameFile);
        rmdir($path);
    }
});

it('applies column drops and renames and removes indexes and foreign keys from the resulting table', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('alter-table_', true);
    mkdir($path, 0700, true);

    $createFile = writeTempMigration($path, '0001_create_settings_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unique('email', 'settings_email_unique');
            $table->string('nickname');
            $table->foreignId('team_id')->constrained();
        });
    }
};
PHP);

    $alterFile = writeTempMigration($path, '0002_alter_settings_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('nickname');
            $table->renameColumn('name', 'full_name');
            $table->dropUnique('settings_email_unique');
            $table->dropForeign('team_id');
        });
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);
        $settings = $schema->tables()['settings'];

        expect($settings->hasColumn('nickname'))->toBeFalse();
        expect($settings->hasColumn('full_name'))->toBeTrue();
        expect($settings->hasColumn('name'))->toBeFalse();
        expect($settings->isIndexed('email'))->toBeFalse();
        expect($settings->foreignKeys())->toBeEmpty();
    } finally {
        unlink($createFile);
        unlink($alterFile);
        rmdir($path);
    }
});

it('drops tables when a later migration removes them', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('drop-table_', true);
    mkdir($path, 0700, true);

    $createFile = writeTempMigration($path, '0001_create_posts_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
    }
};
PHP);

    $dropFile = writeTempMigration($path, '0002_drop_posts_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('posts');
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);

        expect($schema->tables())->toBeEmpty();
    } finally {
        unlink($createFile);
        unlink($dropFile);
        rmdir($path);
    }
});

it('ignores unsupported structural methods while preserving known column state', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('unsupported-structural_', true);
    mkdir($path, 0700, true);

    $file = writeTempMigration($path, '0001_create_notes_table.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
PHP);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);
        $notes = $schema->tables()['notes'];

        expect($notes->hasPrimaryKey())->toBeTrue();
        expect($notes->hasColumn('title'))->toBeTrue();
        expect($notes->columns())->toHaveCount(2);
    } finally {
        unlink($file);
        rmdir($path);
    }
});

it('returns an empty result set when no migration files exist', function (): void {
    $path = sys_get_temp_dir().'/schema-builder-test-'.uniqid('empty_', true);
    mkdir($path, 0700, true);

    try {
        $schema = app(SchemaBuilder::class)->buildFromDirectory($path);

        expect($schema->tables())->toBeArray();
        expect($schema->tables())->toBeEmpty();
    } finally {
        rmdir($path);
    }
});
