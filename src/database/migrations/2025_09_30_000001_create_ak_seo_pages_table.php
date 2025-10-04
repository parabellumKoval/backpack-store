<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
      Schema::create('ak_seo_pages', function (Blueprint $table) {
          $table->id();

          $table->unsignedBigInteger('category_id')->index();          // строка 23
          $table->string('type', 16)->index();                          // static|dynamic (5–22)
          $table->boolean('is_active')->default(true)->index();        // 21

          $table->string('slug', 254)->nullable();
          
          // мульти-региональность (56)
          $table->json('countries')->nullable();

          // мульти-язык (37, 56)
          $table->json('h1')->nullable();                               // поддержка {{ }} автоподстановок (16, 34)
          $table->json('meta_title')->nullable();
          $table->json('meta_description')->nullable();
          $table->json('top_html')->nullable();                         // (36)
          $table->json('bottom_html')->nullable();                      // (36)
          
          $table->boolean('show_on_category')->default(false);          // 40
          $table->boolean('show_on_product')->default(false);           // 41
          $table->boolean('show_in_sitemap')->default(true);            // 42

          // фильтровая логика
          $table->json('filters')->nullable();

          $table->timestamps();
          // $table->unique(['category_id','type']);
      });

    }
    public function down(): void { Schema::dropIfExists('ak_seo_pages'); }
};
