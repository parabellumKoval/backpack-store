<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ak_search_queries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('q', 255);
            $t->string('normalized_q', 255)->nullable();
            $t->string('country_code', 8)->nullable();
            $t->string('locale', 8)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->unsignedInteger('results_count')->default(0);
            $t->unsignedInteger('took_ms')->default(0);
            $t->string('driver', 32)->nullable();
            $t->timestamps();
            $t->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_search_queries');
    }
};
