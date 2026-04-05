<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $defaultStorefront = strtolower((string) config('dress.storefront.default', 'main'));
        $defaultStorefront = $defaultStorefront !== '' ? $defaultStorefront : 'main';

        if (Schema::hasTable('ak_catalog') && !Schema::hasColumn('ak_catalog', 'storefront_code')) {
            Schema::table('ak_catalog', function (Blueprint $table) {
                $table->string('storefront_code', 64)->nullable()->after('country_code');
            });

            DB::table('ak_catalog')
                ->whereNull('storefront_code')
                ->update(['storefront_code' => $defaultStorefront]);

            DB::statement(sprintf(
                "ALTER TABLE ak_catalog MODIFY storefront_code VARCHAR(64) NOT NULL DEFAULT '%s'",
                addslashes($defaultStorefront)
            ));

            Schema::table('ak_catalog', function (Blueprint $table) {
                $table->dropUnique('ak_catalog_unique_product_country');
                $table->unique(
                    ['product_id', 'country_code', 'storefront_code'],
                    'ak_catalog_unique_product_country_storefront'
                );

                $table->dropIndex('akc_country_cat_price');
                $table->dropIndex('akc_country_brand');
                $table->dropIndex('akc_country_group_price');
                $table->dropIndex('akc_country_stock');

                $table->index(['country_code', 'storefront_code', 'is_available', 'price'], 'akc_country_storefront_price');
                $table->index(['country_code', 'storefront_code', 'brand_id', 'is_available'], 'akc_country_storefront_brand');
                $table->index(['country_code', 'storefront_code', 'group_id', 'is_available', 'price'], 'akc_country_storefront_group_price');
                $table->index(['country_code', 'storefront_code', 'is_available', 'in_stock'], 'akc_country_storefront_stock');
            });
        }

        if (Schema::hasTable('ak_catalog_attr') && !Schema::hasColumn('ak_catalog_attr', 'storefront_code')) {
            Schema::table('ak_catalog_attr', function (Blueprint $table) {
                $table->string('storefront_code', 64)->nullable()->after('country_code');
            });

            DB::table('ak_catalog_attr')
                ->whereNull('storefront_code')
                ->update(['storefront_code' => $defaultStorefront]);

            DB::statement(sprintf(
                "ALTER TABLE ak_catalog_attr MODIFY storefront_code VARCHAR(64) NOT NULL DEFAULT '%s'",
                addslashes($defaultStorefront)
            ));

            Schema::table('ak_catalog_attr', function (Blueprint $table) {
                $table->dropIndex('ak_ca_idx_discrete');
                $table->dropIndex('ak_ca_idx_number');
                $table->dropIndex('ak_ca_idx_group');

                $table->index(
                    ['country_code', 'storefront_code', 'attribute_id', 'attribute_value_id', 'group_id'],
                    'ak_ca_idx_discrete'
                );
                $table->index(
                    ['country_code', 'storefront_code', 'attribute_id', 'value', 'group_id'],
                    'ak_ca_idx_number'
                );
                $table->index(['country_code', 'storefront_code', 'group_id'], 'ak_ca_idx_group');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ak_catalog_attr') && Schema::hasColumn('ak_catalog_attr', 'storefront_code')) {
            Schema::table('ak_catalog_attr', function (Blueprint $table) {
                $table->dropIndex('ak_ca_idx_discrete');
                $table->dropIndex('ak_ca_idx_number');
                $table->dropIndex('ak_ca_idx_group');

                $table->index(['country_code', 'attribute_id', 'attribute_value_id', 'group_id'], 'ak_ca_idx_discrete');
                $table->index(['country_code', 'attribute_id', 'value', 'group_id'], 'ak_ca_idx_number');
                $table->index(['country_code', 'group_id'], 'ak_ca_idx_group');
                $table->dropColumn('storefront_code');
            });
        }

        if (Schema::hasTable('ak_catalog') && Schema::hasColumn('ak_catalog', 'storefront_code')) {
            Schema::table('ak_catalog', function (Blueprint $table) {
                $table->dropUnique('ak_catalog_unique_product_country_storefront');
                $table->dropIndex('akc_country_storefront_price');
                $table->dropIndex('akc_country_storefront_brand');
                $table->dropIndex('akc_country_storefront_group_price');
                $table->dropIndex('akc_country_storefront_stock');

                $table->unique(['product_id', 'country_code'], 'ak_catalog_unique_product_country');
                $table->index(['country_code', 'is_available', 'price'], 'akc_country_cat_price');
                $table->index(['country_code', 'brand_id', 'is_available'], 'akc_country_brand');
                $table->index(['country_code', 'group_id', 'is_available', 'price'], 'akc_country_group_price');
                $table->index(['country_code', 'is_available', 'in_stock'], 'akc_country_stock');
                $table->dropColumn('storefront_code');
            });
        }
    }
};
