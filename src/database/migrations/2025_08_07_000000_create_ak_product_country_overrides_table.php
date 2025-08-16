<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateakProductCountryOverridesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_product_country_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ak_products')->onDelete('cascade');
            $table->string('country_code', 2);
            $table->decimal('price_override', 12, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('old_price_override', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'country_code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_product_country_overrides');
    }
}
