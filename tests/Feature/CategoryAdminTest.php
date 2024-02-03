<?php

namespace Backpack\Store\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;

class CategoryAdminTest extends TestCase
{
  // use WithoutMiddleware;
  // use RefreshDatabase;

  public function test_category_index_200() {

    $response = $this->get('/admin/category');  
    $response->assertStatus(200);
  }

  public function test_category_edit_200 () {
    $category = Category::firstOrFail();
    $response = $this->get('/admin/category/'.$category->id.'/edit');
    $response->assertStatus(200);
  }

  public function test_category_create_200 () {
    $response = $this->get('/admin/category/create');
    $response->assertStatus(200);
  }


  public function test_category_store_ok () {
    $response = $this->post('/admin/category', [
      'name' => 'Category name',
      'content' => 'Category description',
      'excerpt' => 'Category short content',
      'images' => null,
      'is_active' => 1,
      'parent_id' => 0,
      'lft' => 0,
      'rgt' => 0,
      'depth' => 0,
      'seo' => null,
      'extras' => null
    ]);

    $response->assertStatus(302);
    // $response->assertCreated();
  }
}
