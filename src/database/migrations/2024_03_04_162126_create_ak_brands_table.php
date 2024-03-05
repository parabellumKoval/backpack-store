<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkBrandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_brands', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug', 255);
            $table->json('content')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(1);
            $table->json('seo')->nullable();
            $table->json('extras')->nullable();
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
        Schema::dropIfExists('ak_brands');
    }
}
