<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // this table was never created in this fixture set (e.g. it exists
        // in a migration path this test doesn't include) — the builder
        // must not manufacture a phantom table out of a bare alter.
        Schema::table('never_declared', function (Blueprint $table) {
            $table->string('anything');
        });
    }
};
