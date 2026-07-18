<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveCurrencyIdFromCustomers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign('customers_currency_id_foreign');
            $table->dropColumn('currency_id');
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
            $table->unsignedInteger('currency_id')->nullable();
            $table->foreign('currency_id', 'customers_currency_id_foreign')->references('id')->on('currencies')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }
}
