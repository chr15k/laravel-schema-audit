<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

final class RenameSuppliersToProviders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('suppliers', 'providers');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('providers', 'suppliers');
    }
}
