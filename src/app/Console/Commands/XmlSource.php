<?php

// namespace App\Console\Commands;
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

use \Cviebrock\EloquentSluggable\Services\SlugService;

use Backpack\Store\app\Models\Brand;
// use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Supplier;
// use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\Source;
use Backpack\Store\app\Jobs\uploadFromXmlSource;


use Illuminate\Support\Facades\Storage;

use Backpack\Store\app\Traits\Exchange;

class XmlSource extends Command
{
    use Exchange;
    use Traits\XmlSource\UploadHistoryTrait;
    use Traits\XmlSource\FileSourceTrait;
    use Traits\XmlSource\XmlSourceTrait;
    use Traits\XmlSource\ForceUpdateTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xml:source';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    protected $totalRecords = 0;
    protected $totalNew = 0;
    protected $totalUpdated = 0;

    protected $settings = null;
    protected $rules = null;
    protected $stockRules = [];
    protected $cs = null;

    protected $isSuppliersEnabled = false;
    
    protected $SP_CLASS = null;
    protected $PRODUCT_CLASS = null;
    protected $IS_TEST_MODE = false;
    protected $TEST_ITEMS = -1;

    protected $currentSource = null;
    protected $uploadHistory = null;

    protected $lang = 'en';
    protected $available_languages;
    protected $exchange_rate = 1;

    protected $fieldLetters = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
      $this->isSuppliersEnabled = config('backpack.store.supplier.enable', false);

      $this->SP_CLASS = config('backpack.store.supplier.sp_class', 'Backpack\Store\app\Models\SupplierProduct');
      $this->PRODUCT_CLASS = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');

      $this->IS_TEST_MODE = config('backpack.store.source.test.enable', false);
      $this->TEST_ITEMS = config('backpack.store.source.test.items', -1);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $sources = Source::active()->get();

      $bar = $this->output->createProgressBar(count($sources));
			$bar->start();

      foreach($sources as $source) {
      	$bar->advance();

        // skip if it's not time yet
        if(config('app.mode') === 'production') {
        // if(true) {
          if($source->type === 'file') {
            // type file
            if($source->updated_at->diffInSeconds($source->last_loading) <= 2) {
              continue;
            }
          }else {
            // type xml_link
            if($source->last_loading && $source->every_minutes && \Carbon\Carbon::now()->diffInMinutes($source->last_loading->addMinute($source->every_minutes), false) > 0) {
              continue;
            }
          }
        }

        // update loading timestamp
        $source->last_loading = \Carbon\Carbon::now();
        $source->save();

          try {
            if($source->type === 'file') {
              $this->loadExcelFile($source);
            }else {
              $this->loadFromXml($source);
            }
          }catch (\Exception $e) {
            \Log::channel('xml')->error($e->getMessage());
            $this->setStatusUploadHistory('error');
          }
      }

			$bar->finish();
    }
    

    /**
     * updateOrCreateItem
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateItem($data) {

      if($this->isSuppliersEnabled && $this->currentSource->supplier) {
        return $this->updateOrCreateSupplierProduct($data);
      }else {
        return $this->updateOrCreateProduct($data);
      }

    }


    /**
     * updateOrCreateProduct
     * 
     * if suppliers disabled and product has only single fields: price, in_stock, code etc.
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateProduct($data) {
      $update_or_create = 'update';

      $product = $this->PRODUCT_CLASS::where('id', '>', 0);

      $function_name = !empty($data['code']) && !empty($data['barcode'])? 'orWhere': 'where';

      if(!empty($data['code'])) {
        $product = $product->where('code', $data['code'])
            ->orWhere('barcode', $data['code']);
      }
      if(!empty($data['barcode'])) {
        $product = $product->{$function_name}('code', $data['barcode'])
            ->orWhere('barcode', $data['barcode']);
      }

      $product = $product
                  ->orWhereRaw("LOWER(`name->{$this->lang}`) LIKE ? ",[trim(strtolower($data['name'])).'%'])
                  ->first();

      if(!$product) {
        $update_or_create = 'create';
        $product = $this->createProduct($data);
      }

      // Update Code, Barcode, amount, price
      $this->setProductData($product, $data);

      // Save product
      $product->save();

      // Set category to product
      $this->attachProductCategory($product, $data);

      return $update_or_create;
    }

    
    /**
     * updateOrCreateSupplierProduct
     * 
     * If Multiple suppliers enabled
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateSupplierProduct($data) {
      $update_or_create = 'update';
      
      $sp = $this->SP_CLASS::
              where('supplier_id', $this->currentSource->supplier_id);

      // Find already presented products's supplier in DB
      $sp = $sp->where(function($query) use($data) {
        $function_name = !empty($data['code']) && !empty($data['barcode'])? 'orWhere': 'where';

        if(!empty($data['code'])) {
          $query->where(function($query) use($data) {
            $query->where('code', $data['code'])
                  ->orWhere('barcode', $data['code']);
          });
        }

        if(!empty($data['barcode'])) {
          $query->{$function_name}(function($query) use($data) {
                  $query->where('code', $data['barcode'])
                        ->orWhere('barcode', $data['barcode']);
          }); 
        }

      });

      // Get first supplier was found
      $sp = $sp->first();

      // No supplier it means no product in DB. Create New Product
      if(!$sp) {
        $update_or_create = 'create';

        $sp = new $this->SP_CLASS;

        // Is Not SupplierProduct means is not Product also
        $product = $this->createProduct($data);

        // Save product
        $product->save();

        // Set category to product
        $this->attachProductCategory($product, $data);
      }else {
        $product = $sp->product;
        $this->forceUpdateFields($product, $data);
      }


      // Update Code, Barcode, inStock, price
      $this->setSupplierData($sp, $data);
    
      // Attach Supplier Product to Product
      $sp->product_id = $product->id;
      $sp->checked_at = time();
      
      $sp->saveWithEvent();

      return $update_or_create;
    }
    
    
    
    
    /**
     * createProduct
     *
     * @return void
     */
    private function createProduct($data) {
      $product = new $this->PRODUCT_CLASS;
      $this->setProductInitFields($product);
      
      $this->setProductName($product, $data);
      
      try {
        $this->setProductImage($product, $data);
      }catch(\Exception $e) {
        throw new \Exception('Set Image Error: ' . $e->getMessage());
      }

      try {
        // Set brand to product
        $this->attachProductBrand($product, $data);
      }catch(\Exception $e) {
        throw new \Exception('Set Brand Error: ' . $e->getMessage());
      }

      return $product;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * getInStock
     *
     * @param  mixed $data
     * @return void
     */
    private function getInStock($data) {
      $in_stock = 0;

      // \Log::info('getInStock');
      // \Log::info(print_r($data, true)); 
      // \Log::info(print_r($this->stockRules, true));    

      if(empty($this->stockRules)) {
        $in_stock = intval($data['inStock']);
        // \Log::info('Empty inStockRules' . $in_stock);  
        return $in_stock;
      }

      // If Product has no isStock data
      $inStock = $data['inStock'] === null? 'null': $data['inStock'];
      
      $rule = $this->stockRules[$inStock] ?? null;

      // if rule not exists set value to zero
      if($rule === null) {
        $in_stock = 0;
      }else {
        $in_stock = intval($rule['value']);  
      }

      // \Log::info('Not Empty inStockRules' . $in_stock);
      return $in_stock;
    }
    
    /**
     * getPrice
     *
     * @param  mixed $data
     * @return void
     */
    private function getPrice($data) {
      $exchange_rate = 1;

      // EXCHANGE CURRENCY
      if(isset($this->rules['exchange'][0]) && !empty($this->rules['exchange'][0])) {
        $rule = $this->rules['exchange'][0];
        
        if($this->isRuleForProduct($rule, $data)) {
          $exchange_rate = $this->exchange_rate;
        }

        if(!empty($rule['exchange_coff'])) {
          $exchange_rate = $exchange_rate * $rule['exchange_coff'];
        }
      }

      $overPrice = 1;

      // OVERPRICE
      if(isset($this->rules['overprice']) && !empty($this->rules['overprice'])) {
        foreach($this->rules['overprice'] as $rule) {
          // dd($rule);
          if($this->isRuleForProduct($rule, $data)) {
            $overPrice = $rule['overprice'];
          }
        }
      }
      
      return ceil((float)$data['price'] * $exchange_rate * $overPrice);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * setProductBrand
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function attachProductBrand(&$product, $data) {
      if(empty($data['brand'])) {
        return;
      }

      $langs_list = array_keys($this->available_languages);
      // Try find brand by name in database
      $brand = Brand::
          where(function($query) use ($data, $langs_list){
            foreach($langs_list as $index => $lang_key) {
              $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
              // $query->{$function_name}("LOWER(`name->{$lang_key}`) LIKE ? ",[trim(strtolower($data['brand']))]);
              // dd(mb_trim(mb_strtolower($data['brand'])));
              $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ',['"' . mb_trim(mb_strtolower($data['brand'])) . '"']);
            }
          })
        // ->orWhereRaw("LOWER(`name->uk`) LIKE ? ",[trim(strtolower($data['brand']))])
        // whereRaw('LOWER(JSON_EXTRACT(name, "$.ru")) like ?', ['"' . trim(strtolower($data['brand'])) . '"'])
          ->first();
      
      if($brand) {
        $product->brand_id = $brand->id;
        return;
      }

      // Checking the brand in the list of correspondences
      $bs = $this->currentSource->bs()
                ->whereRaw('LOWER(`name`) LIKE ? ',[trim(strtolower($data['brand'])).'%'])
                ->whereNotNull('brand_id')
                ->first();

      if($bs) {
        $product->brand_id = $bs->brand_id;
        return;
      }
      
      
      if($this->settings['createNewBrand'] !== "1") {
        return;
      }

      // Else create new brand
      $brand = new Brand;
      $brand->setTranslation('name', $this->lang, $data['brand']);
      $brand->slug = SlugService::createSlug(Brand::class, 'slug', $data['brand']);
      $brand->save();
            
      $product->brand_id = $brand->id;
    }
    

    /**
     * setProductCategory
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function attachProductCategory(&$product, $data) {

      $cs = $this->currentSource->cs()
                    ->whereRaw('LOWER(`name`) LIKE ? ',[trim(strtolower($data['category'])).'%'])
                    ->whereNotNull('category_id')
                    ->first();

      if(!$cs) {
        return;
      }

      $product->categories()->attach($cs->category_id);
    }
        
      
    /**
     * setProductData
     *
     * @param  mixed $sp
     * @param  mixed $data
     * @return void
     */
    private function setProductData(&$product, $data) {
      $product->price = $this->getPrice($data);
      $product->in_stock = $this->getInStock($data);
      // $product->barcode = $data['barcode'];
      $product->code = $data['code'];
    }

    /**
     * setSupplierData
     *
     * @param  mixed $sp
     * @param  mixed $data
     * @return void
     */
    private function setSupplierData(&$sp, $data) {
      $sp->supplier_id = $this->currentSource->supplier_id;

      $sp->code = $data['code'] ?? null;
      $sp->barcode = $data['barcode'] ?? null;
      $sp->price = $this->getPrice($data);
      $sp->in_stock = $this->getInStock($data);
    }


    /**
     * setProductInitFields
     *
     * @param  mixed $product
     * @return void
     */
    private function setProductInitFields(&$product) {
      $product->is_active = 0;
    }
    
    /**
     * setProductName
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function setProductName(&$product, array $data) {
      $product->setTranslation('name', $this->lang, $data['name']);
      $product->slug = SlugService::createSlug($this->PRODUCT_CLASS, 'slug', $data['name']);
    }

        
    /**
     * Method setProductImage
     *
     * @param &$product $product [explicite description]
     * @param array $data [explicite description]
     *
     * @return void
     */
    private function setProductImage(&$product, array $data) {
      if(empty($data['images'])) {
        return;
      }

      $links_array = (array)$data['images'];
      $images = [];


      foreach($links_array as $index => $link) {
        if(!empty($link) && $this->ifImageIndexAllowed($index)) {
          $images[] = [
            'src' => $link,
            'alt' => null,
            'title' => null,
          ];
        }
      }

      if(!empty($images)) {
        $product->images = $images;
      }
    }
    
    /**
     * Method ifImageIndexAllowed
     *
     * @param $index $index [explicite description]
     *
     * @return void
     */
    private function ifImageIndexAllowed($index) {
      // Check if imageIndexes is set
      if(!empty($this->settings['imageIndexes'])) {
        $indexes_string = $this->settings['imageIndexes'];
      }else {
        return true;
      }

      // Convert string to array
      $indexes_array = explode(',', $indexes_string);
      
      // Convert string array to int array 
      $indexes_array_num = array_map(function($item) {
        return (int)$item;
      }, $indexes_array);


      $index_num = (int)$index + 1;

      // Check if index is in array
      if(in_array($index_num, $indexes_array_num)) {
        return true;
      }else {
        return false;
      }
    }

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

        
    /**
     * bootSource
     *
     * @param  mixed $source
     * @return void
     */
    private function bootSource($source) {
      // Set current source in processing
      $this->currentSource = $source;

      // Create upload history
      $this->createUploadHistory();

      // Get Exchange rates
      $this->exchange_rate = $this->getRate();

      // Fill Settings
      $this->settings = $source->settings;

      // Clear force update
      $source->clearForceUpdateAndSave();

      // Fill Letters
      if($source->type === 'file') {
        $this->setFieldLetter();
      }

      $this->lang = $this->settings['language'] ?? 'en';
      $this->available_languages = config('backpack.crud.locales');

      // Fill rules
      $rules = [];
      foreach($source->rules as $rule) {
  
        // Transform JSON lists to simple arrays
        $rule['brands'] = $this->simplify_values($rule['brands']);
        $rule['codes'] = $this->simplify_values($rule['codes']);
        $rule['names'] = $this->simplify_values($rule['names']);

        // Create empty array for this rule type
        if(!isset($rules[$rule['type']]) || empty($rules[$rule['type']])) {
          $rules[$rule['type']] = [];
        }

        // Sorting one type rules because: 
        // 1) at first common rules should be applied
        // 2) at last more spicific rules should be applied
        if($rule['target'] === 'all') {
          array_unshift($rules[$rule['type']], $rule);
        }else {
          $rules[$rule['type']][] = $rule;
        }
      }

      $this->rules = $rules;

      // Fill inStock rules
      $this->stockRules = [];

      if(isset($this->settings['inStockRules']) && !empty($this->settings['inStockRules'])) {
        $stock_rules_array = json_decode($this->settings['inStockRules'], true);
  
        foreach($stock_rules_array as $rule) {
          $this->stockRules[$rule['key']] = $rule;
        }
      }

      // Fill categories
      if($source->cs->count()) {
        $this->cs = $source->cs;
      }
    }
    
    /**
     * simplify_values
     *
     * @param  mixed $values
     * @return void
     */
    private function simplify_values($values = null) {
      if(empty($values)) {
        return null;
      }

      $arr = json_decode($values, true);
      $arr_simple = Arr::flatten($arr);
      return $arr_simple;
    }
    
    /**
     * isRuleForProduct
     *
     * @param  mixed $rule
     * @param  mixed $product
     * @return void
     */
    private function isRuleForProduct($rule, $product){
      if($rule['target'] === 'all') {
        return true;
      }

      // IF BANNED BY BRANDS LIST
      if($rule['target'] === 'brand' && !empty($rule['brands']) && is_array($rule['brands'])) {
        if($this->searchInArray($product['brand'], $rule['brands'])) {
          return true;
        }
      }

      // IF BANNED BY NAMES LIST
      if($rule['target'] === 'name' && !empty($rule['names']) && is_array($rule['names'])) {
        if($this->searchInArray($product['name'], $rule['names'])) {
          return true;
        }
      }

      // IF BANNED BY CODES LIST
      if($rule['target'] === 'code' && !empty($rule['codes']) && is_array($rule['codes'])) {
        if($this->searchInArray($product['code'], $rule['codes'], true)) {
          return true;
        }
      }

      // IF BANNED BY PRICE
      if($rule['target'] === 'price' && (!empty($rule['min_price']) || !empty($rule['max_price']))) {
        $points = 0;

        if($rule['min_price'] !== null && $product['price'] >= $rule['min_price']) {
          $points += 1;
        }

        if($rule['max_price'] !== null && $product['price'] <= $rule['max_price']) {
          $points += 1;
        }

        if($points === 2) {
          return 2;
        }
      }

      return false;
    }

        
    /**
     * searchInArray
     *
     * @param  mixed $search
     * @param  mixed $array
     * @return void
     */
    private function searchInArray($search, $array) {
      if(!is_array($array) || empty($array) || !is_string($search)) {
        return false;
      }

      $search = trim($search);

      for($i = 0; $i < count($array); $i++) {
        $item = trim($array[$i]);
        
        // Try to find %% rule
        preg_match('/^%(.+)%$/i', $item, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches[1])) {
          $search_anywhere = strpos($search, $matches[1]);

          // if false continue to search
          if($search_anywhere !== false) {
            return true;
          }
        }
        
        $matches = null;
        // Try find starts with rule
        preg_match('/^\^(.+)/i', $item, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches[1])) {
          $search_starts_with = str_starts_with($search, $matches[1]);

          // if false continue to search
          if($search_starts_with) {
            return true;
          }
        }

        $matches = null;
        // Search exactly
        preg_match('/^' . $item. '$/i', $search, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches)) {
          return true;
        }
      }

      return false;
    }
    
    
    /**
     * validateData
     *
     * @param  mixed $data
     * @return void
     */
    private function validateData($data) {
      // SKIP PRODUCTS THAT ARTICUL STARTS WITH sale_
      // if(str_starts_with($xml_product['articul'], 'sale_'))
      //   continue;

      // ACCEPT ONLY IF IN WHITELIST
      if(isset($this->rules['whitelist']) && !empty($this->rules['whitelist'])) {
        foreach($this->rules['whitelist'] as $whitelist){
          if($this->isRuleForProduct($whitelist, $data)) {
            return true;
          }
        }
        
        // If product is not in whitelists skip all next checks and return false
        return false;
      }
    
      // SKIP IF IN BLACKLIST
      if(isset($this->rules['blacklist']) && !empty($this->rules['blacklist'])) {
        foreach($this->rules['blacklist'] as $blacklist){
          if($this->isRuleForProduct($blacklist, $data)) {
            return false;
          }
        }

        return true;
      }

      return true;
    }
}
