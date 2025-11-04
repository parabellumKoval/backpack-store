<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_orders', 'promocode_discount_total')) {
                $table->decimal('promocode_discount_total', 14, 2)
                    ->nullable()
                    ->after('discount_total');
            }

            if (!Schema::hasColumn('ak_orders', 'bonus_discount_total')) {
                $table->decimal('bonus_discount_total', 14, 2)
                    ->nullable()
                    ->after('promocode_discount_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('ak_orders', 'bonus_discount_total')) {
                $columns[] = 'bonus_discount_total';
            }

            if (Schema::hasColumn('ak_orders', 'promocode_discount_total')) {
                $columns[] = 'promocode_discount_total';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
