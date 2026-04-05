<?php

namespace Backpack\Store\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;

class ProductAdminTest extends TestCase
{
  // use WithoutMiddleware;
  // use RefreshDatabase;

  /** 
   * test_product_index_200
   * 
   * Test backpack products list page is 200 status
   *
   * @return void
   */
  public function test_product_index_200() {
    $response = $this->get('/admin/product');  
    $response->assertStatus(200);
  }

  /** 
   * test_product_edit_200
   * 
   * Test backpack edit product page is 200 status
   *
   * @return void
   */
  public function test_product_edit_200 () {
    // Get random first product
    $product = Product::firstOrFail();

    $response = $this->get('/admin/product/'.$product->id.'/edit');
    $response->assertStatus(200);
  }
     
  /**
   * test_product_create_200
   * 
   * Test backpack create new product page is 200 status
   *
   * @return void
   */
  public function test_product_create_200 () {
    $response = $this->get('/admin/product/create');
    $response->assertStatus(200);
  }
  
  /**
   * test_product_store_ok
   * 
   * Test store new product method in Backpack
   *
   * @return void
   */
  public function test_product_store_ok () {
    $category = Category::factory()->create();

    $code = '4234234';

    $response = $this->post('/admin/product', [
      'code' => $code,
      'name' => 'Название товара',
      'short_name' => 'Короткое название модификации',
      'content' => 'Описание товара',
      'excerpt' => 'Короткое описание',
      'images' => null,
      'parent_id' => null,
      'price' => 500,
      'old_price' => 560,
      'categories' => array($category->id)
    ]);  

    // Find new product or fail if not exists
    $product = Product::where('code', $code)->first();
    $this->assertTrue(!empty($product));

    // $product_category = $product->categories()->first();

    // Check if category has been attached to product
    // $this->assertEquals($product_category->id, $category->id);
  }

  public function test_product_edit_shows_inherited_attribute_even_when_parent_category_is_country_limited(): void
  {
    $parentCategory = Category::factory()->create([
      'countries' => ['cz'],
    ]);
    $childCategory = Category::factory()->create([
      'parent_id' => $parentCategory->id,
      'countries' => null,
    ]);
    $attribute = Attribute::factory()->create([
      'name' => 'Inherited Admin Attribute',
      'is_active' => true,
    ]);

    $parentCategory->attributes()->attach($attribute->id);

    $product = Product::factory()->create();
    $product->categories()->attach($childCategory->id);

    $response = $this->get('/admin/product/'.$product->id.'/edit');

    $response->assertStatus(200);
    $response->assertSee('Inherited Admin Attribute');
  }
}
