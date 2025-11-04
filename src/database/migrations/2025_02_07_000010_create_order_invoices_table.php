<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ak_orders')->cascadeOnDelete();
            $table->string('template')->index();
            $table->string('locale', 16)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('payload_hash', 64)->index();
            $table->string('path')->unique();
            $table->unsignedBigInteger('filesize')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('meta')->nullable();

            $table->string('qr_format', 8)->nullable();
            $table->string('qr_path')->nullable();
            $table->string('qr_payload_hash', 64)->nullable()->index();
            $table->timestamp('qr_generated_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'template']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_order_invoices');
    }
};
