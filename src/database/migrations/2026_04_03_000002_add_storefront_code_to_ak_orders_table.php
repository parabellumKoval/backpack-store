<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_orders', 'storefront_code')) {
                $table->string('storefront_code', 64)->nullable()->after('country_code');
                $table->index(['storefront_code', 'created_at'], 'ak_orders_storefront_code_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            if (Schema::hasColumn('ak_orders', 'storefront_code')) {
                $table->dropIndex('ak_orders_storefront_code_created_at_index');
                $table->dropColumn('storefront_code');
            }
        });
    }
};
