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

            $table->string('lang', 2)->nullable();

            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('content', 1000)->nullable();
            $table->string('si', 50)->nullable();
            $table->longtext('default_value')->nullable();
            $table->json('values')->nullable();
            $table->enum('type', ['checkbox','radio','number','string','color','colors'])->default('checkbox');
            
            $table->boolean('is_important')->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('in_filters')->default(1);
            $table->boolean('in_properties')->default(1);

            $table->json('extras')->nullable();
            
            $table->integer('parent_id')->default(0)->nullable();
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
