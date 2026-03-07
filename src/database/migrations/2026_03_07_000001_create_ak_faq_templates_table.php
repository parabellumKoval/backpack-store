<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_faq_templates', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->json('name')->nullable();
            $table->json('extras_trans')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_faq_templates');
    }
};
