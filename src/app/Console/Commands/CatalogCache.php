<?php

// namespace App\Console\Commands;
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;

use Backpack\Store\app\Services\Catalog\ProductFilterService;
use Backpack\Store\app\Services\Catalog\ProductQueryService;

class CatalogCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:catalog-update';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    protected $isSuppliersEnabled = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

      $cache_cases = Catalog::getCacheCases();

      foreach($cache_cases as $case) {
        $this->cacheData($case);
      }

      // $this->categoriesCache();

	    // $categories = Category::where('is_active', 1);
      // $this->cacheData($categories, 'category');

	    // $regions = Region::where('is_active', 1);
      // $this->cacheData($regions, 'region');
    }

    private function cacheData($case) {
      $params = $case['params'] ?? null;
      $query_string = $case['query'] ?? null;

      if(!empty($query_string)) {
        $this->handleLoopCase($query_string, $params);
      }elseif(!empty($params)) {
        $this->handleSimpleCase($params);
      }
    }

     private function getCacheRequest($params) {
      $url = url('/api/product/cache');

      $response = Http::get($url, $params);
      $data = $response->json();
    }

    private function handleSimpleCase($params) {
      $this->getCacheRequest($params);
    }

    private function handleLoopCase($query, $params) {
      list($class, $method) = explode('@', $query);
      $instance = new $class();
      $query = $instance->$method();

      $data_count = $query->count();

      $bar = $this->output->createProgressBar($data_count);
      $bar->start();


      foreach($query as $key => $value) {
        $params = array_merge($params, ...[$key => $value]);
        $this->getCacheRequest($params);
        $bar->advance();
      }

      $bar->finish();
    }

    // private function getCacheRequest($params) {
    //   $uri = '/api/product/cache';
    //   // $fullUri = $uri . '?' . http_build_query($params);
    //   // $request = Request::create($fullUri, 'GET');
    //   $request = Request::create($uri, 'GET', $params);
    //   $response = Route::dispatch($request);

      
    //   $content = $response->getContent();
    //   $data = json_decode($content, true);
    //   dd($data);
    // }


    private function categoriesCache() {
	    $category_query = Category::where('is_active', 1);

      $data_cursor = $category_query->cursor();
      $data_count = $category_query->count();

      $bar = $this->output->createProgressBar($data_count);
      $bar->start();

      // $category_controller = new \App\Http\Controllers\Api\CategoryController;
      $product_query_service = app(ProductQueryService::class);

      $fake_request = new \Illuminate\Http\Request();

      foreach($data_cursor as $item) {

        $fake_request->replace([
          'category_slug' => $item->slug,
          'with_filter' => ['selections', 'brands', 'attributes', 'price']
        ]);


        $filterService = new ProductFilterService($fake_request, $product_query_service);
        // $filterService = app($product_filter_service_instance);

        $specCacheKey = 'filters-data-' . $item->slug;
        $filter_data = $filterService->getFiltersData(); 
        dd($filter_data, $item->slug);
        // dd(Cache::get($specCacheKey));
        Cache::put($specCacheKey, $filter_data);

        $specCacheKey = 'filters-count-' . $item->slug;
        $filter_count = $filterService->getFiltersCount(); 
        Cache::put($specCacheKey, $filter_count);

        // if($type === 'region') {
        //   $all_data = $category_controller->catalogData(null, null, $item);
        // }elseif($type === 'category') {
        //   $all_data = $category_controller->catalogData(null, $item);
        // }

        // Cache::put('category-data-' . $item->slug, $all_data);
        $bar->advance();
      }

      $bar->finish();

    }


        
    /**
     * cacheData
     *
     * @param  mixed $query
     * @param  mixed $type
     * @return void
     */
    // protected function cacheData($query, $type) {
	  //   $categories = Category::where('is_active', 1);

    //   $data_cursor = $query->cursor();
    //   $data_count = $query->count();

    //   $bar = $this->output->createProgressBar($data_count);
    //   $bar->start();

    //   $category_controller = new \App\Http\Controllers\Api\CategoryController;

    //   foreach($data_cursor as $item) {
    //     if($type === 'region') {
    //       $all_data = $category_controller->catalogData(null, null, $item);
    //     }elseif($type === 'category') {
    //       $all_data = $category_controller->catalogData(null, $item);
    //     }

    //     Cache::put('category-data-' . $item->slug, $all_data);
    //     $bar->advance();
    //   }

    //   $bar->finish();
    // }
}