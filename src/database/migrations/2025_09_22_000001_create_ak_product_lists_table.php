<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ak_product_lists', function (Blueprint $t) {
            $t->id();
            $t->string('page', 64)->index();                 // product_page, cart, checkout, main, category_page, brand_page, ...
            $t->string('slug', 200);                          // уникален в связке (page, slug)
            $t->string('name', 200);                                // {"uk":"…","ru":"…","en":"…"}
            $t->json('title')->nullable();                   // 
            $t->json('button_text')->nullable();
            $t->json('full_url')->nullable();                // переводимые URL (если надо)
            $t->unsignedSmallInteger('capacity')->default(12);

            $t->json('sources')->nullable();        // ["links","bought_together","tags","category", ...]
            $t->json('sort_order')->nullable();              // ["discount_first","relevance","price_asc"]

            $t->json('filters')->nullable();              // базовый фильтр (категории/бренды/теги/атрибуты/цены)
            $t->json('countries')->nullable();               // список стран или null = везде

            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['page','slug'], 'ak_pl_page_slug_uq');

            // Backpack Reorder
            $t->foreignId('parent_id')->default(null)->nullable();
            $t->integer('lft')->default(0)->nullable();
            $t->integer('rgt')->default(0)->nullable();
            $t->integer('depth')->default(0)->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('ak_product_lists'); }
};
