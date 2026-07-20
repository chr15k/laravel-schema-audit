<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class RemoveFieldsFromPropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('url_availability');
            $table->dropColumn('url_survey_monkey');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->text('url_availability')->nullable();
            $table->text('url_survey_monkey')->nullable();
        });
    }
}
