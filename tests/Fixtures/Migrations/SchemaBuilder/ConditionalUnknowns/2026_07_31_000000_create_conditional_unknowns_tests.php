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

        if (config('app.debug')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('email')->index();
            });
        }

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
        });

        if (config('app.debug')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained();
            });
        }

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->unique('slug');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
        });

        if (config('app.debug')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->index('slug');
            });
        }
    }
};
