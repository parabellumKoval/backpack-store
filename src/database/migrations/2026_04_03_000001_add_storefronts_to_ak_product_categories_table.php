<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_product_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_product_categories', 'storefronts')) {
                $table->json('storefronts')->nullable()->after('countries');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ak_product_categories', function (Blueprint $table) {
            if (Schema::hasColumn('ak_product_categories', 'storefronts')) {
                $table->dropColumn('storefronts');
            }
        });
    }
};
