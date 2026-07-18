<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class PropertyExtrasTax extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->tinyInteger('excluding_tax')->unsigned()->default(0);
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
            $table->dropColumn('excluding_tax');
        });
    }
}
