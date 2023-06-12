<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkPromocodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_promocodes', function (Blueprint $table) {
          $table->id();
          
          // promocode number
          $table->string('code', 100);

          // name of the promocode 
          $table->json('name')->nullable();

          // type - sale in percents or fixed value
          $table->enum('type', ['percent', 'value'])->default('percent');

          // size of the sale percent / fixed price
          $table->double('value');

          // how manu times can be used
          $table->integer('limit')->default(0);

          // Date and time until which to use
          $table->datetime('valid_until')->nullable();

          // Status of promocode 
          $table->boolean('is_active')->default(1);

          // some extra data
          $table->json('extras')->nullable();

          // How many times already use
          $table->integer('used_times')->default(0);

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
        Schema::dropIfExists('ak_promocodes');
    }
}
