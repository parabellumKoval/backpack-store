<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddManualSortColumnsForCatalogSorting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ak_products', function (Blueprint $table) {
            $table->decimal('manual_sort', 14, 4)
                ->nullable()
                ->after('in_stock');

            $table->index('manual_sort', 'ak_products_manual_sort_idx');
        });

        Schema::table('ak_catalog', function (Blueprint $table) {
            $table->decimal('manual_sort', 14, 4)
                ->nullable()
                ->after('in_stock');

            $table->timestamp('created_at')
                ->nullable()
                ->after('attrs');

            $table->index('manual_sort', 'ak_catalog_manual_sort_idx');
            $table->index('created_at', 'ak_catalog_created_at_idx');
        });

        DB::table('ak_catalog as c')
            ->join('ak_products as p', 'p.id', '=', 'c.product_id')
            ->select(['c.id', 'p.manual_sort', 'p.created_at'])
            ->orderBy('c.id')
            ->chunk(1000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ak_catalog')
                        ->where('id', $row->id)
                        ->update([
                            'manual_sort' => $row->manual_sort,
                            'created_at' => $row->created_at,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ak_catalog', function (Blueprint $table) {
            $table->dropIndex('ak_catalog_manual_sort_idx');
            $table->dropIndex('ak_catalog_created_at_idx');
            $table->dropColumn(['manual_sort', 'created_at']);
        });

        Schema::table('ak_products', function (Blueprint $table) {
            $table->dropIndex('ak_products_manual_sort_idx');
            $table->dropColumn('manual_sort');
        });
    }
}
