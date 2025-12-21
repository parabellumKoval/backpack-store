<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ak_product_regional_contents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('ak_products')->onDelete('cascade');
            $table->string('country_code', 2);
            $table->json('content')->nullable();
            $table->json('excerpt')->nullable();
            $table->json('merchant_content')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'country_code'], 'ak_prc_product_country_unique');
            $table->index('country_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ak_product_regional_contents');
    }
};
