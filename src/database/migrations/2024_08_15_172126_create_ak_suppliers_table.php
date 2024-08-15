<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_suppliers', function (Blueprint $table) {
          $table->id();
          $table->string('name');
          $table->text('content')->nullable();
          $table->boolean('is_active')->default(1);
          $table->enum('type', ['warehouse', 'dropshipping', 'common'])->default('common');
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
        Schema::dropIfExists('ak_suppliers');
    }
}
