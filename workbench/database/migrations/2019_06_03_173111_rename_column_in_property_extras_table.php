<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\ValueObjectsbase\Migrations\Migration;
use Illuminate\ValueObjectsbase\Schema\Blueprint;

final class RenameColumnInPropertyExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->dropForeign('property_supplier_id_foreign');
            $table->renameColumn('supplier_id', 'provider_id');
            $table->foreign('provider_id', 'property_provider_id_foreign')->references('id')->on('providers')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('property_extras', function (Blueprint $table) {
            $table->dropForeign('property_provider_id_foreign');
            $table->renameColumn('provider_id', 'supplier_id');
            $table->foreign('supplier_id', 'property_supplier_id_foreign')->references('id')->on('suppliers')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });
    }
}
