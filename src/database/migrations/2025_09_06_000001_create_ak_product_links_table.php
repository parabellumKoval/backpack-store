<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkProductLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('ak_product_links', function (Blueprint $table) {
            $table->id();
            $table->morphs('linkable');
            // $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('kind', ['cross', 'up'])->index();    // тип связи
            $table->unsignedTinyInteger('priority')->default(0)->index(); // 0..255, чем больше — важнее

            // Reorder
            $table->foreignId('parent_id')->default(null)->nullable();
            $table->integer('lft')->default(0)->nullable();
            $table->integer('rgt')->default(0)->nullable();
            $table->integer('depth')->default(0)->nullable();

            $table->timestamps();

            // --- ограничения/индексы ---
            // 1) защита от дублей: на один якорь нельзя дважды привязать один и тот же товар того же типа
            $table->unique(
                ['linkable_type','linkable_id','product_id','kind'],
                'ak_pl_linkable_pid_kind_uq'
            );

            // 2) «read-path» под выборки на странице якоря:
            //    WHERE linkable_type=?, linkable_id=?, kind=? ORDER BY priority DESC
            $table->index(
                ['linkable_type','linkable_id','kind','priority','product_id'],
                'ak_pl_anchor_idx'
            );

            // 3) иногда нужно быстро найти все якоря, где фигурирует товар:
            $table->index(['product_id','kind'], 'ak_pl_product_idx');

            // FK только на product_id
            $table->foreign('product_id')->references('id')->on('ak_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_product_links');
    }
}
