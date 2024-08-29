<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_sources', function (Blueprint $table) {
          $table->id();
          $table->string('name');
          $table->string('key')->unique();
          $table->foreignId('supplier_id')->nullable();
          $table->text('link')->nullable();
          $table->text('content')->nullable();
          $table->boolean('is_active')->default(1);
          $table->enum('type', ['xml_link'])->default('xml_link');
          // Common overprice
          $table->double('overprice', 8, 2)->default(1);
          $table->json('settings')->nullable();
          $table->json('rules')->nullable();
          $table->timestamp('last_loading')->nullable();
          $table->integer('every_minutes')->default(60);
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
        Schema::dropIfExists('ak_sources');
    }
}
