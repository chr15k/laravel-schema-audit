<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->integer('count')->unsigned();
            $table->unsignedBigInteger('total');
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->primary(['team_id', 'user_id']);
        });
    }
};
