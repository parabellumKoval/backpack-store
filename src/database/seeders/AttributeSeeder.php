<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\AttributeProduct;

class AttributeSeeder extends Seeder
{

  private $attrs;
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      Attribute::where('id', '>=', 0)->delete();
      AttributeValue::where('id', '>=', 0)->delete();
      AttributeProduct::where('id', '>=', 0)->delete();

      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>Attribute was deleted.</info>");

      $this->createAttributes();
      $this->attachToCategories();

      $this->createRegularAttributeValues();
      $this->createUniqAttributeValues();
    }
    
    /**
     * attachToCategories
     *
     * @return void
     */
    private function attachToCategories() {
      $categories = Category::all();

      if(!$categories)
        return;

      foreach($categories as $category) {
        // Get random attributes
        $attributes = Attribute::inRandomOrder()->limit(3)->get();

        // Attach attributes to category
        $category->attributes()->attach($attributes->pluck('id')->toArray());
      }

    }
    
    /**
     * createAttributes
     *
     * @return void
     */
    private function createAttributes() {
      $this->attributes = Attribute::factory()->suspended()->count(20)->create();
    }
    
    /**
     * createAttributeValues
     *
     * @return void
     */
    private function createRegularAttributeValues() {
      $attributes = $this->attributes->whereIn('type', ['checkbox', 'radio']);

      if(!$attributes)
        return;

      foreach($attributes as $attribute) {
        AttributeValue::factory()->count(6)->state([
          'attribute_id' => $attribute->id,
        ])->create();
      }

      // Attach attributes to products
      $products = Product::base()->get();

      foreach($products as $product) {
        if(!$product->category)
          continue;

        $attributes = $product->category->attributes()->whereIn('type', ['checkbox', 'radio'])->get();

        foreach($attributes as $attribute) {
          // random attribute value
          $attribute_value = $attribute->values()->inRandomOrder()->first();
          
          AttributeProduct::create([
            'value' => null,
            'attribute_value_id' => $attribute_value->id,
            'attribute_id' => $attribute->id,
            'product_id' => $product->id
          ]);
        }
      }

    }
    
    /**
     * createUniqAttributeValues
     *
     * @return void
     */
    private function createUniqAttributeValues() {
      $products = Product::base()->get();
      
      foreach($products as $product) {
        if(!$product->category)
          continue;

        // Attributes available for this product (throw its category attributes) 
        $attributes = $product->category->attributes()->where('type', 'number')->get();

        // Create record for each individual attribute
        foreach($attributes as $attribute) {
          AttributeProduct::factory()->state([
            'attribute_id' => $attribute->id,
            'product_id' => $product->id
          ])->create();
        }
      }

    }
}
