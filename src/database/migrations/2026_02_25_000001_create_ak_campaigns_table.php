<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_campaigns', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true)->index();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('short_description')->nullable();
            $table->longText('conditions_html')->nullable();

            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->integer('priority')->default(0)->index();

            $table->boolean('is_timed')->default(false)->index();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();

            $table->boolean('show_timer_card')->default(false);
            $table->boolean('show_timer_product')->default(false);

            $table->string('horizontal_banner')->nullable();
            $table->string('vertical_banner')->nullable();

            $table->boolean('add_to_main_banner')->default(false)->index();
            $table->boolean('add_banner_to_catalog')->default(false)->index();
            $table->unsignedInteger('catalog_banner_frequency')->nullable();
            $table->unsignedInteger('catalog_banner_position')->nullable();

            $table->string('product_source', 20)->default('filters');
            $table->json('product_filters')->nullable();
            $table->json('manual_products')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_campaigns');
    }
};
