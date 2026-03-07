<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_orders', 'campaign_discount_total')) {
                $table->decimal('campaign_discount_total', 14, 2)
                    ->nullable()
                    ->after('personal_discount_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ak_orders', function (Blueprint $table) {
            if (Schema::hasColumn('ak_orders', 'campaign_discount_total')) {
                $table->dropColumn('campaign_discount_total');
            }
        });
    }
};
