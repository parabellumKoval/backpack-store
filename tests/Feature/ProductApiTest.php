<?php

namespace Backpack\Store\Tests\Feature;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;

class ProductApiTest extends TestCase
{
    // use RefreshDatabase;

    /**
     * test_index_is_200
     * 
     * Clearly test api index route is 200 status
     *
     * @return void
     */
    public function test_index_is_200()
    {
        $response = $this->get('/api/product');
        $response->assertStatus(200);
    }

        
    /**
     * test_products_isset_in_db
     * 
     * Test products exists in DB after seeding and 
     * return by api index route without exceptions and fails
     *
     * @return void
     */
    public function test_products_isset_in_db() 
    {
      $response = $this->getJson('/api/product');
      
      try {
        $response_array = $response->json();
      }catch(\Exception $e) {
        echo 'Error duaring json getting: ' . $e->getMessage(); 
      }

      $this->assertTrue(count($response_array['data']) > 0);
    }
    
    /**
     * test_index_structure_is_ok
     * 
     * Test whole clear index api route return correct json structure 
     *
     * @return void
     */
    public function test_index_structure_is_ok()
    {
      $response = $this->get('/api/product'); 
      $response->assertJsonStructure([
        'data' => [
          '*' => [
            'id',
            'name',
            'slug',
            'price',
            'old_price',
            'rating',
            'image',
            'excerpt',
            'modifications'
          ]
        ],
        'meta' => [
          'current_page',
          'from',
          'last_page',
          'links',
          'path',
          'per_page',
          'to',
          'total'
        ]
      ]);
    }
    
    /**
     * test_show_structure_is_ok
     * 
     * Test api product show route return correct structure of json
     *
     * @return void
     */
    public function test_show_structure_is_ok() {
      // Get random first product from db
      $product = Product::firstOrFail();

      $response = $this->get("/api/product/{$product->slug}"); 
      $response->assertJsonStructure([
        'id',
        'name',
        'slug',
        'code',
        'price',
        'old_price',
        'rating',
        'reviews_rating_detailes',
        'images' => [
          '*' => [
            'src',
            'alt',
            'title'
          ]
        ],
        'content',
        'attrs',
        'categories' => [
          '*' => [
            'id',
            'name',
            'slug',
            'extras'
          ]
        ],
        'modifications' => [
          '*' => [
            'id',
            'name',
            'short_name',
            'slug',
            'price',
            'attrs'
          ]
        ],
        'seo' => [
          'meta_title',
          'meta_description'
        ]
      ]);
    }

    
    /**
     * test_index_filter_by_category_id_is_ok
     *
     * Test get api index route with filtering by category id and category slug
     * 
     * @return void
     */
    public function test_index_filter_by_category_id_is_ok(){
      $category = Category::has('products')->firstOrFail();

      //Filter collection using category id
      $filters = "?category_id={$category->id}";
      $response = $this->getJson('/api/product'.$filters);
      $response_array = $response->json();
      $this->assertTrue(count($response_array['data']) > 0);

      //Filter collection using category slug
      $filters = "?category_slug={$category->slug}";
      $response = $this->getJson('/api/product'.$filters);
      $response_array = $response->json();
      $this->assertTrue(count($response_array['data']) > 0);
    }
    
    /**
     * test_index_search_by_string_is_ok
     * 
     * Test get api index route with filtering by search query (search by name/short_name/code)
     *
     * @return void
     */
    public function test_index_search_by_string_is_ok() {
      // Getrandom product
      $product = Product::firstOrFail();

      // Search by product name 
      $filters = "?q={$product->name}";

      $response = $this->getJson('/api/product'.$filters);
      $response_array = $response->json();

      $this->assertTrue(count($response_array['data']) > 0);
    }
}
