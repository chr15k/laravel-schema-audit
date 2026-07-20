<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class RecreateForeignKeyInBookingSupplierconditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('booking_supplierconditions', function (Blueprint $table) {
            $table->dropForeign('booking_supplierconditions_supplier_id_foreign');
            $table->foreign('supplier_id', 'booking_supplierconditions_supplier_id_foreign')->references('id')->on('suppliers')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('booking_supplierconditions', function (Blueprint $table) {
            $table->dropForeign('booking_supplierconditions_supplier_id_foreign');
        });
    }
}
