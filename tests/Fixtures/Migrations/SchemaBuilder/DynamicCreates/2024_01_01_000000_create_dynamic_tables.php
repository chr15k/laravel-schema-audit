<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void {}
};
