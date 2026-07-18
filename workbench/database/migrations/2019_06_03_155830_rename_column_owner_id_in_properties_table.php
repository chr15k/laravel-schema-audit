<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class RenameColumnOwnerIdInPropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign('properties_owner_id_foreign');
            $table->renameColumn('owner_id', 'supplier_id');
            $table->foreign('supplier_id', 'properties_supplier_id_foreign')->references('id')->on('suppliers')->onDelete('SET NULL')->onUpdate('RESTRICT');
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
            $table->dropForeign('properties_supplier_id_foreign');
            $table->renameColumn('supplier_id', 'owner_id');
            $table->foreign('owner_id', 'properties_owner_id_foreign')->references('id')->on('owners')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }
}
