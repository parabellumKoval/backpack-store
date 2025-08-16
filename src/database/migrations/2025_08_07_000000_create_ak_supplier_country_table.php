<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkSupplierCountryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('ak_supplier_country', function (Blueprint $table) {
          $table->id();
          $table->foreignId('supplier_id')->constrained('ak_suppliers')->onDelete('cascade');
          $table->string('country_code', 2); // пример: 'UA', 'CZ'
          $table->unique(['supplier_id', 'country_code']);
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_supplier_country');
    }
}
