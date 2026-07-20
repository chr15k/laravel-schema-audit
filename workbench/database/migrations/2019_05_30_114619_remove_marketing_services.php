<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class RemoveMarketingServices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('properties_marketing_services', function (Blueprint $table) {
            $table->dropForeign('properties_marketing_services_marketing_service_id_foreign');
            $table->dropForeign('properties_marketing_services_property_id_foreign');
        });
        Schema::dropIfExists('marketing_services');
        Schema::dropIfExists('properties_marketing_services');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('marketing_services', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name', 255);

            $table->unique('name', 'marketing_services_name_unique');
        });

        Schema::create('properties_marketing_services', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('property_id');
            $table->unsignedInteger('marketing_service_id');
            $table->text('value');
        });

        Schema::table('properties_marketing_services', function (Blueprint $table) {
            $table->foreign('marketing_service_id', 'properties_marketing_services_marketing_service_id_foreign')->references('id')->on('marketing_services')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('property_id', 'properties_marketing_services_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }
}
