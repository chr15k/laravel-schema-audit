<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class ChangeTitleInCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('title', 255)->default('')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('title', 255)->default('')->change();
        });
    }
}
