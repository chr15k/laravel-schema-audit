<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveApiuserTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_user_ip_addresses', function (Blueprint $table) {
            $table->dropForeign('api_user_ip_addresses_api_user_id_foreign');
        });

        Schema::table('api_users', function (Blueprint $table) {
            $table->dropForeign('api_users_created_by_user_id_foreign');
        });

        Schema::dropIfExists('api_users');
        Schema::dropIfExists('api_user_ip_addresses');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('api_users', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name', 255);
            $table->string('secret', 255);
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->tinyInteger('ip_restrict')->default(0);
            $table->rememberToken();
        });

        Schema::create('api_user_ip_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('ip', 255);
            $table->unsignedInteger('api_user_id')->nullable();
        });

        Schema::table('api_user_ip_addresses', function (Blueprint $table) {
            $table->foreign('api_user_id', 'api_user_ip_addresses_api_user_id_foreign')->references('id')->on('api_users')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('api_users', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'api_users_created_by_user_id_foreign')->references('id')->on('users')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }
}
