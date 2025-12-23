<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStoreOnlyFlags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ak_product_categories', function (Blueprint $table) {
            $table->json('store_only_countries')
                ->nullable()
                ->after('countries');
        });

        Schema::table('ak_catalog', function (Blueprint $table) {
            $table->boolean('store_only')
                ->default(false)
                ->after('is_available');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ak_product_categories', function (Blueprint $table) {
            $table->dropColumn('store_only_countries');
        });

        Schema::table('ak_catalog', function (Blueprint $table) {
            $table->dropColumn('store_only');
        });
    }
}
