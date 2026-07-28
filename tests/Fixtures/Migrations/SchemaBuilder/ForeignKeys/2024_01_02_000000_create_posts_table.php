<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();

            // convention: column user_id -> infers table "users"
            $table->foreignId('user_id')->constrained();

            // explicit table name via positional arg
            $table->foreignId('editor_id')->constrained('users');

            // explicit table name via named arg
            $table->foreignId('category_id')->constrained(table: 'categories');

            // old-style foreign()->references()->on()
            $table->unsignedBigInteger('legacy_owner_id');
            $table->foreign('legacy_owner_id')->references('id')->on('users');
        });
    }
};
