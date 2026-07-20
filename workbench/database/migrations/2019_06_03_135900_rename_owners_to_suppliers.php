<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class RenameOwnersToSuppliers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('owner_email_addresses', function (Blueprint $table) {
            $table->dropForeign('owner_email_addresses_owner_id_foreign');
        });

        Schema::rename('owner_email_addresses', 'supplier_email_addresses');

        Schema::table('supplier_email_addresses', function (Blueprint $table) {
            $table->renameColumn('owner_id', 'supplier_id');
        });

        Schema::rename('owners', 'suppliers');

        Schema::table('supplier_email_addresses', function (Blueprint $table) {
            $table->foreign('supplier_id', 'supplier_email_addresses_supplier_id_foreign')->references('id')->on('suppliers')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('supplier_email_addresses', function (Blueprint $table) {
            $table->dropForeign('supplier_email_addresses_supplier_id_foreign');
        });

        Schema::rename('suppliers', 'owners');

        Schema::table('supplier_email_addresses', function (Blueprint $table) {
            $table->renameColumn('supplier_id', 'owner_id');
        });

        Schema::table('owner_email_addresses', function (Blueprint $table) {
            $table->foreign('owner_id', 'owner_email_addresses_owner_id_foreign')->references('id')->on('owners')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }
}
