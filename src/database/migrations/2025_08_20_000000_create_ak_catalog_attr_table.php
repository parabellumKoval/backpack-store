<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ak_catalog_attr', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->string('country_code', 8)->index();
            $t->string('storefront_code', 64)->default('main')->index();

            // группа (id базового товара) и вариант (product_id модификации)
            $t->unsignedBigInteger('group_id')->index();
            $t->unsignedBigInteger('product_id')->nullable()->index();

            // атрибут и его значение
            $t->unsignedBigInteger('attribute_id')->index();
            $t->unsignedBigInteger('attribute_value_id')->nullable(); // для check/radio
            $t->double('value')->nullable();                          // для number

            // индексы под EXISTS и фасеты
            $t->index(['country_code','storefront_code','attribute_id','attribute_value_id','group_id'], 'ak_ca_idx_discrete');
            $t->index(['country_code','storefront_code','attribute_id','value','group_id'], 'ak_ca_idx_number');
            $t->index(['country_code','storefront_code','group_id'], 'ak_ca_idx_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_catalog_attr');
    }
};
