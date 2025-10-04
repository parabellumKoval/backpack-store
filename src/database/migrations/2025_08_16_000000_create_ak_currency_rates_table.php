<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkCurrencyRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('ak_currency_rates', function (Blueprint $table) {
          $table->id();
          $table->string('source')->index();
          $table->string('base', 3)->index();
          $table->json('rates');
          $table->timestamp('fetched_at')->index();
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
        Schema::dropIfExists('ak_currency_rates');
    }
}
