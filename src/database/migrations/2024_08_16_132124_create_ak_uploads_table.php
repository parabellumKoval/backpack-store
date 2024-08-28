<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkUploadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_uploads', function (Blueprint $table) {
          $table->id();
          $table->foreignId('source_id');
          $table->enum('status', ['pending', 'done', 'error'])->default('pending');
          $table->integer('processed_items')->default(0);
          $table->integer('total_items')->default(0);
          $table->integer('error_items')->default(0);
          $table->integer('new_items')->default(0);
          $table->integer('updated_items')->default(0);
          $table->json('rules')->nullable();
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
        Schema::dropIfExists('ak_uploads');
    }
}
