<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkAttributeProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_attribute_product', function (Blueprint $table) {
            $table->id();
            
            // Only used when attribute type: number
            $table->double('value')->nullable();

            // Only used when attribute type: string
            $table->json('value_trans')->nullable();

            // Only used when attribute type: checkbox, radio
            $table->foreignId('attribute_value_id')->nullable();

            // Link to attribute
            $table->foreignId('attribute_id');

            // Link to product
            $table->foreignId('product_id');

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
        Schema::dropIfExists('ak_attribute_product');
    }
}
