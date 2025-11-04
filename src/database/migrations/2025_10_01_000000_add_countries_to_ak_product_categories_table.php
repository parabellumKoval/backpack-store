<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountriesToAkProductCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ak_product_categories', function (Blueprint $table) {
            $table->json('countries')->nullable()->after('extras_trans');
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
            $table->dropColumn('countries');
        });
    }
}
