<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_user_ip_addresses', function (Blueprint $table) {
            $table->foreign('api_user_id', 'api_user_ip_addresses_api_user_id_foreign')->references('id')->on('api_users')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('api_users', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'api_users_created_by_user_id_foreign')->references('id')->on('users')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->foreign('booking_id', 'booking_lines_booking_id_foreign')->references('id')->on('bookings')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('cancels_line', 'booking_lines_cancels_line_foreign')->references('id')->on('booking_lines')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('tax_band_id', 'booking_lines_tax_band_id_foreign')->references('id')->on('tax_bands')->onDelete('RESTRICT')->onUpdate('RESTRICT');
        });

        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreign('booking_id', 'booking_payments_booking_id_foreign')->references('id')->on('bookings')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('booking_supplierconditions', function (Blueprint $table) {
            $table->foreign('booking_id', 'booking_supplierconditions_booking_id_foreign')->references('id')->on('bookings')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('supplier_id', 'booking_supplierconditions_supplier_id_foreign')->references('id')->on('owners')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('currency_id', 'bookings_currency_id_foreign')->references('id')->on('currencies')->onDelete('RESTRICT')->onUpdate('RESTRICT');
            $table->foreign('customer_id', 'bookings_customer_id_foreign')->references('id')->on('customers')->onDelete('SET NULL')->onUpdate('RESTRICT');
            $table->foreign('property_id', 'bookings_property_id_foreign')->references('id')->on('properties')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('currency_id', 'customers_currency_id_foreign')->references('id')->on('currencies')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->foreign('currency_id', 'discounts_currency_id_foreign')->references('id')->on('currencies')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->foreign('base_currency_id', 'exchange_rates_base_currency_id_foreign')->references('id')->on('currencies')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('compare_currency_id', 'exchange_rates_compare_currency_id_foreign')->references('id')->on('currencies')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('owner_email_addresses', function (Blueprint $table) {
            $table->foreign('owner_id', 'owner_email_addresses_owner_id_foreign')->references('id')->on('owners')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreign('currency_id', 'properties_currency_id_foreign')->references('id')->on('currencies')->onDelete('RESTRICT')->onUpdate('RESTRICT');
            $table->foreign('owner_id', 'properties_owner_id_foreign')->references('id')->on('owners')->onDelete('SET NULL')->onUpdate('RESTRICT');
            $table->foreign('region_id', 'properties_region_id_foreign')->references('id')->on('regions')->onDelete('SET NULL')->onUpdate('RESTRICT');
            $table->foreign('tax_band_id', 'properties_tax_band_id_foreign')->references('id')->on('tax_bands')->onDelete('RESTRICT')->onUpdate('RESTRICT');
        });

        Schema::table('properties_custom_fields', function (Blueprint $table) {
            $table->foreign('property_id', 'properties_custom_fields_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('properties_marketing_services', function (Blueprint $table) {
            $table->foreign('marketing_service_id', 'properties_marketing_services_marketing_service_id_foreign')->references('id')->on('marketing_services')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('property_id', 'properties_marketing_services_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('properties_related_properties', function (Blueprint $table) {
            $table->foreign('property_id', 'properties_related_properties_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('related_property_id', 'properties_related_properties_related_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('properties_third_party_services', function (Blueprint $table) {
            $table->foreign('property_id', 'dtps_di_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('third_party_service_id', 'dtps_tpsi_foreign')->references('id')->on('third_party_services')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('property_price_rows', function (Blueprint $table) {
            $table->foreign('property_id', 'property_price_rows_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('season_date_id', 'property_price_rows_season_date_id_foreign')->references('id')->on('season_dates')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });

        Schema::table('property_stop_sale_dates', function (Blueprint $table) {
            $table->foreign('third_party_importer_id', 'dssd_tpii_foreign')->references('id')->on('third_party_importers')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('property_id', 'property_stop_sale_dates_property_id_foreign')->references('id')->on('properties')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->foreign('parent_region_id', 'regions_parent_region_id_foreign')->references('id')->on('regions')->onDelete('SET NULL')->onUpdate('RESTRICT');
        });

        Schema::table('regions_tax_bands', function (Blueprint $table) {
            $table->foreign('region_id', 'regions_tax_bands_region_id_foreign')->references('id')->on('regions')->onDelete('CASCADE')->onUpdate('RESTRICT');
            $table->foreign('tax_band_id', 'regions_tax_bands_tax_band_id_foreign')->references('id')->on('tax_bands')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('scheduled_mails', function (Blueprint $table) {
            $table->foreign('booking_id', 'scheduled_mails_booking_id_foreign')->references('id')->on('bookings')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('season_dates', function (Blueprint $table) {
            $table->foreign('season_id', 'season_dates_season_id_foreign')->references('id')->on('seasons')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });

        Schema::table('third_party_importers', function (Blueprint $table) {
            $table->foreign('third_party_service_id', 'tpt_tpsi_foreign')->references('id')->on('third_party_services')->onDelete('CASCADE')->onUpdate('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_user_ip_addresses', function (Blueprint $table) {
            $table->dropForeign('api_user_ip_addresses_api_user_id_foreign');
        });

        Schema::table('api_users', function (Blueprint $table) {
            $table->dropForeign('api_users_created_by_user_id_foreign');
        });

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropForeign('booking_lines_booking_id_foreign');
            $table->dropForeign('booking_lines_cancels_line_foreign');
            $table->dropForeign('booking_lines_tax_band_id_foreign');
        });

        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropForeign('booking_payments_booking_id_foreign');
        });

        Schema::table('booking_supplierconditions', function (Blueprint $table) {
            $table->dropForeign('booking_supplierconditions_booking_id_foreign');
            $table->dropForeign('booking_supplierconditions_supplier_id_foreign');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign('bookings_currency_id_foreign');
            $table->dropForeign('bookings_customer_id_foreign');
            $table->dropForeign('bookings_property_id_foreign');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign('customers_currency_id_foreign');
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->dropForeign('discounts_currency_id_foreign');
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropForeign('exchange_rates_base_currency_id_foreign');
            $table->dropForeign('exchange_rates_compare_currency_id_foreign');

        });

        Schema::table('owner_email_addresses', function (Blueprint $table) {
            $table->dropForeign('owner_email_addresses_owner_id_foreign');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign('properties_currency_id_foreign');
            $table->dropForeign('properties_owner_id_foreign');
            $table->dropForeign('properties_region_id_foreign');
            $table->dropForeign('properties_tax_band_id_foreign');
        });

        Schema::table('properties_custom_fields', function (Blueprint $table) {
            $table->dropForeign('properties_custom_fields_property_id_foreign');
        });

        Schema::table('properties_marketing_services', function (Blueprint $table) {
            $table->dropForeign('properties_marketing_services_marketing_service_id_foreign');
            $table->dropForeign('properties_marketing_services_property_id_foreign');
        });

        Schema::table('properties_related_properties', function (Blueprint $table) {
            $table->dropForeign('properties_related_properties_property_id_foreign');
            $table->dropForeign('properties_related_properties_related_property_id_foreign');
        });

        Schema::table('properties_third_party_services', function (Blueprint $table) {
            $table->dropForeign('dtps_di_foreign');
            $table->dropForeign('dtps_tpsi_foreign');
        });

        Schema::table('property_price_rows', function (Blueprint $table) {
            $table->dropForeign('property_price_rows_property_id_foreign');
            $table->dropForeign('property_price_rows_season_date_id_foreign');
        });

        Schema::table('property_stop_sale_dates', function (Blueprint $table) {
            $table->dropForeign('dssd_tpii_foreign');
            $table->dropForeign('property_stop_sale_dates_property_id_foreign');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropForeign('regions_parent_region_id_foreign');
        });

        Schema::table('regions_tax_bands', function (Blueprint $table) {
            $table->dropForeign('regions_tax_bands_region_id_foreign');
            $table->dropForeign('regions_tax_bands_tax_band_id_foreign');
        });

        Schema::table('scheduled_mails', function (Blueprint $table) {
            $table->dropForeign('scheduled_mails_booking_id_foreign');
        });

        Schema::table('season_dates', function (Blueprint $table) {
            $table->dropForeign('season_dates_season_id_foreign');
        });

        Schema::table('third_party_importers', function (Blueprint $table) {
            $table->dropForeign('tpt_tpsi_foreign');
        });
    }
}
