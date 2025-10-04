<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateAkOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_orders', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();

            $table->nullableUuidMorphs('orderable');
            $table->string('code', 6);
            // new, canceled, failed, completed
            $table->string('status', 30)->default('new');
            // waiting, failed, paied
            $table->string('pay_status', 30)->default('waiting');
            // waiting, sent, failed, delivered, pickedup
            $table->string('delivery_status', 30)->default('waiting');

            $table->float('price')->default(0);
            $table->char('currency_code', 3)->index();
            $table->decimal('fx_rate', 16, 8);

            foreach (['subtotal','discount_total','shipping_total','tax_total','grand_total'] as $col) {
                $table->decimal($col, 14, 2)->nullable();
            }

            $table->char('shipping_country_code', 2)->nullable();
            $table->char('billing_country_code', 2)->nullable();

            // Сборная информация о заказе
            $table->json('info')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['country_code', 'status']);
            $table->index(['country_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('ak_orders', function (Blueprint $table) {
        //     $table->dropIndex(['orders_country_code_status_index']);
        //     $table->dropIndex(['orders_country_code_created_at_index']);
        // });

        Schema::dropIfExists('ak_orders');
    }
}
