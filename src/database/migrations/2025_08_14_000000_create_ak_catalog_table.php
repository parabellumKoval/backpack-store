<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkCatalogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_catalog', function (Blueprint $t) {
            $t->bigIncrements('id');
            // Идентификация записи и группировка
            $t->unsignedBigInteger('product_id');                 // всегда child (модификация)
            $t->unsignedBigInteger('group_id');                   // parent_id ?: product_id
            $t->string('country_code', 2);                        // ISO Alpha-2
            $t->string('currency_code', 3);                       // ISO 4217 (валюта цены)

            $t->enum('item_type', ['s', 'm'])->default('s');                // s - simple, m - modification
            // Публикация и наличие
            $t->boolean('is_available')->default(true);
            $t->integer('in_stock')->default(0);                  // агрегировано по складам страны

            // Цены (уже с учётом overrides и конвертации)
            $t->decimal('price', 12, 2);                          // финальная цена для страны
            $t->decimal('old_price', 12, 2)->nullable();

            // Основные связи для фильтров
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->json('category_ids')->nullable();

            // Поля для отображения (см. п.2 — денормализация)
            $t->json('name')->nullable();                  // название товара (эффективное)
            $t->json('short_name')->nullable();            // название модификации (эффективное)
            $t->string('slug', 255)->nullable();           // SEO-слизг (группы или варианта)
            $t->json('excerpt')->nullable();               // краткое описание
            $t->json('images')->nullable();                // галерея (не уч. в фильтрах)
            $t->string('code', 100)->nullable();            // артикул
            $t->json('extras')->nullable();
            $t->float('rating', 10, 2)->nullable();        // средняя оценка товара
            $t->integer('reviews')->nullable();             // кол-во отзывов
            $t->integer('ratings')->nullable();             // кол-во оценок

            // Ключи/индексы
            // $t->primary(['product_id', 'country_code']);
            $t->unique(['product_id', 'country_code'], 'ak_catalog_unique_product_country');
            $t->index(['country_code', 'is_available', 'price'], 'akc_country_cat_price');
            $t->index(['country_code', 'brand_id', 'is_available'], 'akc_country_brand');
            $t->index(['country_code', 'group_id', 'is_available', 'price'], 'akc_country_group_price');
            $t->index(['country_code', 'is_available', 'in_stock'], 'akc_country_stock');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_catalog');
    }
}
