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
      // PRODUCTS
      Schema::table('ak_products', function(Blueprint $table) {
        $table->foreign('parent_id')->references('id')->on('ak_products')
              ->onDelete('cascade')
              ->onUpdate('no action');
        
        $table->foreign('brand_id')->references('id')->on('ak_brands')
              ->onDelete('cascade')
              ->onUpdate('no action');
      });

      // CATEGORIES
      Schema::table('ak_product_categories', function(Blueprint $table) {
        $table->foreign('parent_id')->references('id')->on('ak_product_categories')
              ->onDelete('cascade')
              ->onUpdate('no action');
      });

      // CATEGORY PRODUCT
      Schema::table('ak_category_product', function(Blueprint $table) {
        $table->foreign('category_id')->references('id')->on('ak_product_categories')
              ->onDelete('cascade')
              ->onUpdate('no action');

        $table->foreign('product_id')->references('id')->on('ak_products')
              ->onDelete('cascade')
              ->onUpdate('no action');
      });

      // ORDERS
      // Schema::table('ak_orders', function(Blueprint $table) {
      //   $table->foreign('orderable_id')->references('id')->on('ak_product_categories')
      //         ->onDelete('cascade')
      //         ->onUpdate('no action');
      // });


      // ATTRIBUTES
      Schema::table('ak_attributes', function(Blueprint $table) {
        $table->foreign('parent_id')->references('id')->on('ak_attributes')
              ->onDelete('cascade')
              ->onUpdate('no action');
      });


      // ATTRIBUTE CATEGORIES
      Schema::table('ak_attribute_category', function(Blueprint $table) {
        $table->foreign('attribute_id')->references('id')->on('ak_attributes')
            ->onDelete('cascade')
            ->onUpdate('no action');

        
        $table->foreign('category_id')->references('id')->on('ak_product_categories')
            ->onDelete('cascade')
            ->onUpdate('no action');
      });

      // ATTRIBUTE PRODUCT
      Schema::table('ak_attribute_product', function(Blueprint $table) {
        // Deny allow to remove attribute if it has attached to whitchever product
        $table->foreign('attribute_id')->references('id')->on('ak_attributes')
            ->onDelete('restrict')
            ->onUpdate('no action');
        
        $table->foreign('product_id')->references('id')->on('ak_products')
            ->onDelete('cascade')
            ->onUpdate('no action');

        $table->foreign('attribute_value_id')->references('id')->on('ak_attribute_values')
            ->onDelete('no action')
            ->onUpdate('no action');
      });

      // ATTRIBUTE VALUES
      Schema::table('ak_attribute_values', function(Blueprint $table) {
        // Remove all values if attribute has been deleted
        $table->foreign('attribute_id')->references('id')->on('ak_attributes')
            ->onDelete('cascade')
            ->onUpdate('no action');

        
        $table->foreign('parent_id')->references('id')->on('ak_attribute_values')
            ->onDelete('cascade')
            ->onUpdate('no action');
      });

      // ORDER PRODUCT
      Schema::table('ak_order_product', function(Blueprint $table) {
        $table->foreign('order_id')->references('id')->on('ak_orders')
            ->onDelete('cascade')
            ->onUpdate('no action');

        // Deny allow to remove product if it has attached to whichever order
        $table->foreign('product_id')->references('id')->on('ak_products')
            ->onDelete('restrict')
            ->onUpdate('no action');
      });

      // CART
      Schema::table('ak_carts', function(Blueprint $table) {
        // IMPORTANT pay attention for this, no index for users
        // $table->foreign('user_id')->references('id')->on('ak_orders')
        //     ->onDelete('cascade')
        //     ->onUpdate('no action');

        // Deny allow to remove product if it has attached to whichever cart
        $table->foreign('product_id')->references('id')->on('ak_products')
            ->onDelete('no action')
            ->onUpdate('no action');
      });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('ak_products', function(Blueprint $table) {
        $table->dropForeign(['parent_id']);
      });

      Schema::table('ak_product_categories', function(Blueprint $table) {
        $table->dropForeign(['parent_id']);
      });

      Schema::table('ak_category_product', function(Blueprint $table) {
        $table->dropForeign(['category_id']);
        $table->dropForeign(['product_id']);
      });

      Schema::table('ak_attributes', function(Blueprint $table) {
        $table->dropForeign(['parent_id']);
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
        $table->dropForeign(['parent_id']);
      });

      Schema::table('ak_order_product', function(Blueprint $table) {
        $table->dropForeign(['order_id']);
        $table->dropForeign(['product_id']);
      });

      Schema::table('ak_carts', function(Blueprint $table) {
        $table->dropForeign(['product_id']);
      });
    }
}
