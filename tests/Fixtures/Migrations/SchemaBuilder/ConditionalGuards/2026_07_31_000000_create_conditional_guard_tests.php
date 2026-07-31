<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });

        if (! Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('email')->index();
            });
        }

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
        });

        if (! Schema::hasColumn('posts', 'user_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained();
            });
        }

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
        });
    }
};
