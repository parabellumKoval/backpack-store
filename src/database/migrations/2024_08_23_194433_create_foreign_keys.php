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

      // CATEGORY PRODUCT
      Schema::table('ak_category_product', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_category_product', 'category_id')) {
          $table->foreign('category_id')
                ->references('id')
                ->on('ak_product_categories')
                ->onDelete('cascade');
        }
        
        if(!$this->_isForeignKeysExist('ak_category_product', 'product_id')) {
          $table->foreign('product_id')
                ->references('id')
                ->on('ak_products')
                ->onDelete('cascade');
        }
      });

      // ATTRIBUTE CATEGORIES
      Schema::table('ak_attribute_category', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_attribute_category', 'attribute_id')) {
          $table->foreign('attribute_id')
                ->references('id')
                ->on('ak_attributes')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_attribute_category', 'category_id')) {
          $table->foreign('category_id')
                ->references('id')
                ->on('ak_product_categories')
                ->onDelete('cascade');
        }
      });

      // ATTRIBUTE PRODUCT
      Schema::table('ak_attribute_product', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_attribute_product', 'attribute_id')) {
          $table->foreign('attribute_id')
                ->references('id')
                ->on('ak_attributes')
                ->onDelete('cascade');
        }
        
        if(!$this->_isForeignKeysExist('ak_attribute_product', 'product_id')) {
          $table->foreign('product_id')
                ->references('id')
                ->on('ak_products')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_attribute_product', 'attribute_value_id')) {
          $table->foreign('attribute_value_id')
                ->references('id')
                ->on('ak_attribute_values')
                ->onDelete('cascade');
        }
      });

      // ATTRIBUTE VALUES
      Schema::table('ak_attribute_values', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_attribute_values', 'attribute_id')) {
          // Remove all values if attribute has been deleted
          $table->foreign('attribute_id')
                ->references('id')
                ->on('ak_attributes')
                ->onDelete('cascade');
        }
      });

      // ORDER PRODUCT
      Schema::table('ak_order_product', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_order_product', 'order_id')) {
          $table->foreign('order_id')
                ->references('id')
                ->on('ak_orders')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_order_product', 'product_id')) {
          $table->foreign('product_id')
                ->references('id')
                ->on('ak_products')
                ->onDelete('restrict');
        }
      });

      // CART
      Schema::table('ak_carts', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_carts', 'product_id')) {
          $table->foreign('product_id')
                ->references('id')
                ->on('ak_products')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_carts', 'user_id')) {
          $table->foreign('user_id')
                ->references('id')
                ->on('ak_users')
                ->onDelete('cascade');
        }
      });


      //
      Schema::table('ak_supplier_product', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_supplier_product', 'product_id')) {
          $table->foreign('product_id')
                ->references('id')
                ->on('ak_products')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_supplier_product', 'supplier_id')) {
          $table->foreign('supplier_id')
                ->references('id')
                ->on('ak_suppliers')
                ->onDelete('cascade');
        }
      });


      //
      Schema::table('ak_category_source', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_category_source', 'source_id')) {
          $table->foreign('source_id')
                ->references('id')
                ->on('ak_sources')
                ->onDelete('cascade');
        }

        if(!$this->_isForeignKeysExist('ak_category_source', 'category_id')) {
          $table->foreign('category_id')
                ->references('id')
                ->on('ak_categories')
                ->onDelete('cascade');
        }
      });

      //
      Schema::table('ak_brand_source', function(Blueprint $table) {
        if(!$this->_isForeignKeysExist('ak_brand_source', 'source_id')) {
          $table->foreign('source_id')
                ->references('id')
                ->on('ak_sources')
                ->onDelete('cascade');
        }
        
        if(!$this->_isForeignKeysExist('ak_brand_source', 'brand_id')) {
          $table->foreign('brand_id')
                ->references('id')
                ->on('ak_brands')
                ->onDelete('cascade');
        }
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
      $this->_dropForeignIfExist('ak_category_product', 'category_id');
      $this->_dropForeignIfExist('ak_category_product', 'product_id');

      $this->_dropForeignIfExist('ak_attribute_category', 'attribute_id');
      $this->_dropForeignIfExist('ak_attribute_category', 'category_id');

      $this->_dropForeignIfExist('ak_attribute_product', 'attribute_id');
      $this->_dropForeignIfExist('ak_attribute_product', 'product_id');
      $this->_dropForeignIfExist('ak_attribute_product', 'attribute_value_id');

      $this->_dropForeignIfExist('ak_attribute_values', 'attribute_id');
     
      $this->_dropForeignIfExist('ak_order_product', 'order_id');
      $this->_dropForeignIfExist('ak_order_product', 'product_id');

      $this->_dropForeignIfExist('ak_carts', 'product_id');
      $this->_dropForeignIfExist('ak_carts', 'user_id');

      $this->_dropForeignIfExist('ak_supplier_product', 'product_id');
      $this->_dropForeignIfExist('ak_supplier_product', 'supplier_id');

      $this->_dropForeignIfExist('ak_category_source', 'source_id');
      $this->_dropForeignIfExist('ak_category_source', 'category_id');

      $this->_dropForeignIfExist('ak_brand_source', 'source_id');
      $this->_dropForeignIfExist('ak_brand_source', 'brand_id');

    }

    // public function _createForeignIfNotExist($tableName, $indexName){

    // }
  
    /**
     * _dropForeignIfExist
     *
     * @param  mixed $tableName
     * @param  mixed $indexName
     * @return void
     */
    public function _dropForeignIfExist($tableName, $indexName)
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) { 
            if($this->_isForeignKeysExist($tableName, $indexName)) {
              $table->dropForeign([$indexName]);
            }
        });
    }
    
    /**
     * _isForeignKeysExist
     *
     * @param  mixed $tableName
     * @param  mixed $column
     * @return bool
     */
    public function _isForeignKeysExist(string $tableName, string $column): bool
    {  
        $fkColumns = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableForeignKeys($tableName);

        return collect($fkColumns)->map(function ($fkColumn) {
            return $fkColumn->getColumns();
        })->flatten()->contains($column);
    }
}
