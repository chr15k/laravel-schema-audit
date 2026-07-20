<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class PropertyExtrasTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->integer('type')->unsigned()->default(1);
            $table->integer('period')->unsigned()->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('period');
        });
    }
}
