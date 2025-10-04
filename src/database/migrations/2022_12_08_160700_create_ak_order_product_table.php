<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkOrderProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_order_product', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->nullable()->index();
            $table->char('currency_code', 3)->index();

            $table->foreignId('order_id');
            $table->foreignId('product_id');
            $table->foreignId('supplier_id');

            $table->integer('amount')->default(1);
            $table->longtext('value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_order_product');
    }
}
