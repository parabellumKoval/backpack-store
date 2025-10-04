<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkBoughtTogetherTable extends Migration
{
    public function up(): void
    {
      Schema::create('ak_bought_together', function (Blueprint $table) {
          $table->id();
          $table->unsignedBigInteger('product_id');
          $table->unsignedBigInteger('with_product_id');
          $table->string('country_code', 2)->nullable()->index();
          $table->unsignedInteger('score')->default(0)->index();
          $table->timestamps();

          // <- короткое имя уникального индекса
          $table->unique(
              ['product_id','with_product_id','country_code'],
              'ak_bt_pid_wpid_cc_uq'
          );

          // индексы под быстрый read-path (тоже с короткими именами)
          $table->index(
              ['product_id','country_code','score','with_product_id'],
              'ak_bt_anchor_idx'
          );
          $table->index(
              ['with_product_id','country_code','score','product_id'],
              'ak_bt_rev_idx'
          );

          $table->foreign('product_id')->references('id')->on('ak_products')->cascadeOnDelete();
          $table->foreign('with_product_id')->references('id')->on('ak_products')->cascadeOnDelete();
      });

    }

    public function down(): void
    {
        Schema::dropIfExists('ak_bought_together');
    }
}
