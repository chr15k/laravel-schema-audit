<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreatePropertyImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_images', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('property_id')->unsigned();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('alt', 255)->nullable();
            $table->integer('sorting')->nullable()->default(1);
            $table->string('img_path', 255)->nullable();

            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_images');
    }
}
