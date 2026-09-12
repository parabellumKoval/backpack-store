<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionModeToSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('ak_suppliers', function (Blueprint $table) {
        // Per-supplier AI description generation strategy consumed by the
        // external LLM generator:
        //   'scratch' - generate from product name + brand (default, legacy behaviour)
        //   'rewrite' - deep-rewrite the supplier's imported description
        $table->string('description_mode')->default('scratch')->after('type');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('ak_suppliers', function (Blueprint $table) {
        $table->dropColumn('description_mode');
      });
    }
}
