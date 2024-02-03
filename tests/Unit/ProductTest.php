<?php
 
namespace Backpack\Store\tests\Unit;
 
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Promocode;

// DATE
use Carbon\Carbon;
 
class ProductTest extends TestCase
{  
  /**
   * test_seo_attribute
   *
   * @return void
   */
  public function test_seo_attribute():void {
    $product = Product::first();
   
    $this->assertArrayHasKey('meta_title', $product->seo);
    $this->assertArrayHasKey('meta_description', $product->seo);
  }
  
  /**
   * test_base_attribute
   * 
   * Base attribute return parent of the product or self Model
   *
   * @return void
   */
  public function test_base_attribute():void {
    // Get product that has parent
    $product_with_parent = Product::where('parent_id', '!=', null)->first();

    // Should return parent Product 
    $this->assertTrue($product_with_parent->base->id === $product_with_parent->parent_id);

    // Get product without parent
    $product_no_parent = Product::where('parent_id', null)->first();

    // Should return it self
    $this->assertTrue($product_no_parent->base === $product_no_parent);
  }
  
  /**
   * test_modifications_attribute
   * 
   * Return collection of all related products: Parent + childrens
   *
   * @return void
   */
  public function test_modifications_attribute():void {
    // 1.
    // Product with childrens
    $product = Product::has('children')->first();

    // Should return childrens + itself
    $this->assertTrue($product->modifications->count() === $product->children->count() + 1);
    
    // Should contains base product itself
    $this->assertTrue($product->modifications->where('id', $product->id)->first() !== null);

    // 2. 
    // Take product that is one of the products family
    $child_product = Product::has('parent')->first();

    // Should return all family products: min itself + parent
    $this->assertTrue($child_product->modifications->count() === $child_product->parent->children->count() + 1);
    
    // Should contains itself
    $this->assertTrue($child_product->modifications->where('id', $child_product->id)->first() !== null);
  }

  
  /**
   * test_image_attribute
   *
   * @return void
   */
  public function test_image_attribute():void {
    $product = Product::first();

    // Test responce is Array
    $this->assertIsArray($product->image);

    // Test array structure
    $this->assertArrayHasKey('src', $product->image);
    $this->assertArrayHasKey('alt', $product->image);
    $this->assertArrayHasKey('title', $product->image);
  }
  
  /**
   * test_image_src_attribute
   *
   * @return void
   */
  public function test_image_src_attribute():void {
    $product = Product::first();
    $this->assertIsString($product->imageSrc);
  }
  
  /**
   * test_category_attribute
   *
   * @return void
   */
  public function test_category_attribute():void {
    $product = Product::first();
    $this->assertInstanceOf(Category::class, $product->category);
  }
  
  /**
   * test_categories_relationship
   *
   * @return void
   */
  public function test_categories_relationship():void {
    $product = Product::first();
    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $product->categories());
  }
  
  /**
   * test_orders_relationship
   *
   * @return void
   */
  public function test_orders_relationship():void {
    // Create order with atached products
    Order::factory()
    ->hasAttached(
      Product::factory()->count(3),
      [
        'amount' => rand(1,10),
        'value' => rand(100, 9999)
      ]
    )->create();

    $product = Product::has('orders')->first();
    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $product->orders());
  }
  
  /**
   * test_children_relationship
   *
   * @return void
   */
  public function test_children_relationship():void {
    $product = Product::has('children')->first();
    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $product->children());
  }

  
  /**
   * test_parent_relationship
   *
   * @return void
   */
  public function test_parent_relationship():void {
    $product = Product::has('parent')->first();
    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $product->parent());
  }

  
  /**
   * test_attrs_relationship
   *
   * @return void
   */
  // public function test_attrs_relationship():void {}
}