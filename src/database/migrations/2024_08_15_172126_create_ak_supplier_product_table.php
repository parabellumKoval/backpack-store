<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkSupplierProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_supplier_product', function (Blueprint $table) {
          $table->id();
          $table->foreignId('supplier_id');
          $table->foreignId('product_id');
          $table->integer('in_stock')->default(1);
          $table->string('code')->nullable();
          $table->double('price', 8, 2)->nullable();
          $table->double('old_price', 8, 2)->nullable();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_supplier_product');
    }
}
