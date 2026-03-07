<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_campaign_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->char('country_code', 2)->index();

            $table->unique(['campaign_id', 'product_id', 'country_code'], 'ak_campaign_product_unique');
            $table->index(['product_id', 'country_code'], 'ak_campaign_product_lookup');
            $table->index(['campaign_id', 'country_code'], 'ak_campaign_country_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_campaign_product');
    }
};
