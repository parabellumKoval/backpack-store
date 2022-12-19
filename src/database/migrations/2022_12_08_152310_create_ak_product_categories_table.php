<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkProductCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_product_categories', function (Blueprint $table) {
          $table->id();

          $table->json('name', 255);
          $table->string('slug', 255);
          $table->longtext('content')->nullable();
          $table->text('excerpt', 500)->nullable();
          $table->json('images')->nullable();
          
          $table->boolean('is_active')->default(1);
          
          $table->integer('parent_id')->default(0)->nullable();
          $table->integer('lft')->default(0)->nullable();
          $table->integer('rgt')->default(0)->nullable();
          $table->integer('depth')->default(0)->nullable();

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
        Schema::dropIfExists('ak_product_categories');
    }
}
