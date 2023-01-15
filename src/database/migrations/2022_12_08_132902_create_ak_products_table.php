<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_products', function (Blueprint $table) {
          $table->id();

          $table->string('code', 20)->nullable();
          $table->json('name');
          $table->string('short_name', 255)->nullable();
          $table->string('slug', 255);
          $table->json('content')->nullable();
          $table->json('excerpt')->nullable();
          $table->json('images')->nullable();

          $table->foreignId('parent_id')->nullable()->default(null);

          $table->foreignId('brand_id')->default('0');

          $table->double('price', 8, 2)->nullable();
          $table->double('old_price', 8, 2)->nullable();

          $table->float('rating')->nullable();

          $table->integer('in_stock')->default(1);
          $table->boolean('is_active')->default(1);
          $table->boolean('is_pricehidden')->default(0);
          
          $table->json('seo')->nullable();
          $table->json('extras')->nullable();

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
        Schema::dropIfExists('ak_products');
    }
}
