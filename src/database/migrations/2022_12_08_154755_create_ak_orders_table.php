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
            $table->nullableUuidMorphs('orderable');
            $table->string('code', 6);
            // new, canceled, failed, completed
            $table->string('status', 30)->default('new');
            // waiting, failed, paied
            $table->string('pay_status', 30)->default('waiting');
            // waiting, sent, failed, delivered, pickedup
            $table->string('delivery_status', 30)->default('waiting');
            $table->float('price')->default(0);
            $table->json('info')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_orders');
    }
}
