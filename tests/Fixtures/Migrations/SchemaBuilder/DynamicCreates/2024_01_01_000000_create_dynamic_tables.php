<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class Models
{
    public static function table(string $name): string
    {
        return $name;
    }
}

final class User
{
    public function getTable(): string
    {
        return 'users';
    }
}

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Models::table('abilities'), function (Blueprint $table): void {
            $table->id();
        });

        Schema::create((new User)->getTable(), function (Blueprint $table): void {
            $table->id();
        });

        Schema::create(config('permission.table_names.roles'), function (Blueprint $table): void {
            $table->id();
        });

        $tableName = 'users';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void {}
};
