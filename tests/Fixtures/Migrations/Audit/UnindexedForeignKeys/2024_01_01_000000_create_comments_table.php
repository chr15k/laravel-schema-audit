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
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('post_id');

            $table->foreign('post_id')
                ->references('id')
                ->on('posts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('posts');
    }
};
