<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class Post {}

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();

            // foreignIdFor() resolves table/column name from the model class
            $table->foreignIdFor(Post::class)->constrained();

            // fully named-arg override
            $table->foreignId('assigned_to')->constrained(
                table: 'users',
                indexName: 'comments_assigned_to_custom_fk',
                columns: 'id'
            );
        });
    }
};
