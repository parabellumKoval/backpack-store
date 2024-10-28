<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAkAttributeValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('ak_attribute_values', function (Blueprint $table) {
        $table->string('transform')->nullable();
        $table->json('extras')->nullable();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('ak_attribute_values', function (Blueprint $table) {
        $table->dropColumn('transform');
        $table->dropColumn('extras');
      });
    }
}
