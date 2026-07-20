<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class AddFieldToPropertyExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
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
            $table->dropColumn('description');
        });
    }
}
