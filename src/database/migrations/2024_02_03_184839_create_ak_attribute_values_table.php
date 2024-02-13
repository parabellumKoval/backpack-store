<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkAttributeValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_attribute_values', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to Attribute
            $table->foreignId('attribute_id');

            // Translatable values
            $table->json('value')->nullable();
            
            // For reorderable
            $table->foreignId('parent_id')->default(null)->nullable();
            $table->integer('lft')->default(0)->nullable();
            $table->integer('rgt')->default(0)->nullable();
            $table->integer('depth')->default(0)->nullable();

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
        Schema::dropIfExists('ak_attribute_values');
    }
}
