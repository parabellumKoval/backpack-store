<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::disableForeignKeyConstraints();


      // PRODUCTS
      // Schema::table('ak_products', function(Blueprint $table) {
      //   $table->foreign('parent_id')->references('id')->on('ak_products')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
        
      //   $table->foreign('brand_id')->references('id')->on('ak_brands')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
      // });

      // CATEGORIES
      // Schema::table('ak_product_categories', function(Blueprint $table) {
      //   $table->foreign('parent_id')->references('id')->on('ak_product_categories')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
      // });

      // CATEGORY PRODUCT
      Schema::table('ak_category_product', function(Blueprint $table) {
        $table->foreign('category_id')
              ->references('id')
              ->on('ak_product_categories')
              ->onDelete('cascade');

        $table->foreign('product_id')
              ->references('id')
              ->on('ak_products')
              ->onDelete('cascade');
      });

      // ORDERS
      // Schema::table('ak_orders', function(Blueprint $table) {
      //   $table->foreign('orderable_id')->references('id')->on('ak_product_categories')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
      // });


      // ATTRIBUTES
      // Schema::table('ak_attributes', function(Blueprint $table) {
      //   $table->foreign('parent_id')
      //         ->references('id')
      //         ->on('ak_attributes')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
      // });


      // ATTRIBUTE CATEGORIES
      Schema::table('ak_attribute_category', function(Blueprint $table) {
        $table->foreign('attribute_id')
              ->references('id')
              ->on('ak_attributes')
              ->onDelete('cascade');

        
        $table->foreign('category_id')
              ->references('id')
              ->on('ak_product_categories')
              ->onDelete('cascade');
      });

      // ATTRIBUTE PRODUCT
      Schema::table('ak_attribute_product', function(Blueprint $table) {

        $table->foreign('attribute_id')
              ->references('id')
              ->on('ak_attributes')
              ->onDelete('cascade');
        
        $table->foreign('product_id')
              ->references('id')
              ->on('ak_products')
              ->onDelete('cascade');

        $table->foreign('attribute_value_id')
              ->references('id')
              ->on('ak_attribute_values')
              ->onDelete('cascade');
      });

      // ATTRIBUTE VALUES
      Schema::table('ak_attribute_values', function(Blueprint $table) {
        // Remove all values if attribute has been deleted
        $table->foreign('attribute_id')
              ->references('id')
              ->on('ak_attributes')
              ->onDelete('cascade');
      });

      // ORDER PRODUCT
      Schema::table('ak_order_product', function(Blueprint $table) {
        $table->foreign('order_id')
              ->references('id')
              ->on('ak_orders')
              ->onDelete('cascade');

        $table->foreign('product_id')
              ->references('id')
              ->on('ak_products')
              ->onDelete('restrict');
      });

      // CART
      Schema::table('ak_carts', function(Blueprint $table) {
        $table->foreign('product_id')
              ->references('id')
              ->on('ak_products')
              ->onDelete('cascade');

        
        $table->foreign('user_id')
            ->references('id')
            ->on('ak_users')
            ->onDelete('cascade');
      });


      //
      Schema::table('ak_supplier_product', function(Blueprint $table) {
        $table->foreign('product_id')
              ->references('id')
              ->on('ak_products')
              ->onDelete('cascade');
        
        $table->foreign('supplier_id')
              ->references('id')
              ->on('ak_suppliers')
              ->onDelete('cascade');
      });


      //
      Schema::table('ak_category_source', function(Blueprint $table) {
        $table->foreign('source_id')
              ->references('id')
              ->on('ak_sources')
              ->onDelete('cascade');
        
        $table->foreign('category_id')
              ->references('id')
              ->on('ak_categories')
              ->onDelete('cascade');
      });

      //
      Schema::table('ak_brand_source', function(Blueprint $table) {
        $table->foreign('source_id')
              ->references('id')
              ->on('ak_sources')
              ->onDelete('cascade');
        
        $table->foreign('brand_id')
              ->references('id')
              ->on('ak_brands')
              ->onDelete('cascade');
      });

      Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::disableForeignKeyConstraints();
      
      Schema::table('ak_category_product', function(Blueprint $table) {
        $table->dropForeign(['category_id']);
        $table->dropForeign(['product_id']);
      });

      Schema::table('ak_attribute_category', function(Blueprint $table) {
        $table->dropForeign(['attribute_id']);
        $table->dropForeign(['category_id']);
      });

      Schema::table('ak_attribute_product', function(Blueprint $table) {
        $table->dropForeign(['attribute_id']);
        $table->dropForeign(['product_id']);
        $table->dropForeign(['attribute_value_id']);
      });

      Schema::table('ak_attribute_values', function(Blueprint $table) {
        $table->dropForeign(['attribute_id']);
      });

      Schema::table('ak_order_product', function(Blueprint $table) {
        $table->dropForeign(['order_id']);
        $table->dropForeign(['product_id']);
      });

      Schema::table('ak_carts', function(Blueprint $table) {
        $table->dropForeign(['product_id']);
        $table->dropForeign(['user_id']);
      });

      Schema::table('ak_supplier_product', function(Blueprint $table) {
        $table->dropForeign(['product_id']);
        $table->dropForeign(['supplier_id']);
      });

      Schema::table('ak_category_source', function(Blueprint $table) {
        $table->dropForeign(['source_id']);
        $table->dropForeign(['category_id']);
      });

      Schema::table('ak_brand_source', function(Blueprint $table) {
        $table->dropForeign(['source_id']);
        $table->dropForeign(['brand_id']);
      });

      Schema::enableForeignKeyConstraints();
    }
}
