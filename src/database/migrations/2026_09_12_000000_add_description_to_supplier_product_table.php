<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToSupplierProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('ak_supplier_product', function (Blueprint $table) {
        // Raw product description as imported from the supplier feed.
        // Used as the source text for the "rewrite" AI generation mode.
        $table->longText('description')->nullable()->after('barcode');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('ak_supplier_product', function (Blueprint $table) {
        $table->dropColumn('description');
      });
    }
}
