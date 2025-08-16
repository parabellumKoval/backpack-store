<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkExchangeRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('ak_exchange_rates', function (Blueprint $table) {
        $table->id();
        $table->string('from_currency', 3);
        $table->string('to_currency', 3);
        $table->decimal('rate', 15, 8);
        $table->timestamp('valid_from');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_exchange_rates');
    }
}
