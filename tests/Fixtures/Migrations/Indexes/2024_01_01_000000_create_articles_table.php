<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('email')->index();
            $table->string('reference')->unique('custom_reference_unique');
            $table->integer('category_id');
            $table->integer('author_id');
            $table->index(['category_id', 'author_id']);
            $table->unique(['category_id', 'author_id'], 'articles_category_author_unique');
        });
    }
};
