<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_attributes', function (Blueprint $table) {
            $table->id();
            
            // Attribute name translatable
            $table->json('name');

            // Latin slug
            $table->string('slug', 255);

            // Translatable Text description, content 
            $table->json('content')->nullable();

            // $table->json('values')->nullable();
            $table->enum('type', ['checkbox','radio','number'])->default('checkbox');
            
            // Is attribute active
            $table->boolean('is_active')->default(1);

            // Is attribute shows in filters 
            $table->boolean('in_filters')->default(1);

            // Is attribute shows in prodect properties
            $table->boolean('in_properties')->default(1);

            // Aditional info
            $table->json('extras')->nullable();

            // Translatable aditional info
            $table->json('extras_trans')->nullable();
            
            // For reorderable
            $table->foreignId('parent_id')->default(0)->nullable();
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
        Schema::dropIfExists('ak_attributes');
    }
}
