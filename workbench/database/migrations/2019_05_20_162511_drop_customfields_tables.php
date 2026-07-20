<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class DropCustomfieldsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('properties_custom_fields', function (Blueprint $table) {
            $table->dropForeign('properties_custom_fields_property_id_foreign');
        });

        Schema::dropIfExists('property_custom_fields');
        Schema::dropIfExists('properties_custom_fields');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('properties_custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('property_id');
            $table->unsignedInteger('property_custom_field_id');
            $table->mediumText('custom_value');

            $table->index('property_custom_field_id', 'properties_custom_fields_property_custom_field_id_foreign');
        });

        Schema::create('property_custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name', 255)->default('');
            $table->mediumText('value');

            $table->index('name', 'property_custom_fields_name_index');
        });

        Schema::table('properties_custom_fields', function (Blueprint $table) {
            $table->foreign('property_id', 'properties_custom_fields_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }
}
