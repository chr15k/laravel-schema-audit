<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreatePropertyExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_extras', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->unsignedInteger('property_id')->nullable();
            $table->string('name');
            $table->decimal('cost_price', 10, 2)->default(0.00);
            $table->decimal('sale_price', 10, 2)->default(0.00);
            $table->unsignedInteger('supplier_id')->nullable();
            $table->boolean('is_mandatory')->default(0);
        });

        Schema::table('property_extras', function (Blueprint $table) {
            $table->foreign('property_id', 'property_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('supplier_id', 'property_supplier_id_foreign')->references('id')->on('suppliers')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_extras');
    }
}
