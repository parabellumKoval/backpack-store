<?php

// namespace App\Console\Commands;
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Supplier;
use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\Source;
use Backpack\Store\app\Models\UploadHistory;
use Backpack\Store\app\Jobs\uploadFromXmlSource;

use Backpack\Store\app\Traits\Exchange;

class XmlSource extends Command
{
    use Exchange;
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

    protected $currentSource = null;
    protected $uploadHistory = null;

    protected $lang = 'en';
    protected $available_languages;
    protected $exchange_rate = 1;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
      $this->isSuppliersEnabled = config('backpack.store.supplier.enable', false);
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
        if($source->last_loading && $source->every_minutes && \Carbon\Carbon::now()->diffInMinutes($source->last_loading->addMinute($source->every_minutes), false) > 0) {
          continue;
        }

        // update loading timestamp
        $source->last_loading = \Carbon\Carbon::now();
        $source->save();

        try {
          $this->loadFromXml($source);
        }catch (\Exception $e) {
          $this->setStatusUploadHistory('error');
        }
      }

			$bar->finish();
    }
  
    
    /**
     * loadFromXml
     *
     * @param  mixed $source
     * @return void
     */
    private function loadFromXml($source) {
      $this->bootSource($source);

      $this->createUploadHistory();
      
      $xml = $this->getXMLCatalog($source->link);

      $way = 'Catalog->items->item';
      $item = array_reduce(explode('->', $this->settings['item']), function($model, $property) {
        return $model->{$property};
      }, $xml);

      $this->totalRecords = count($item);
      
      $this->totalUploadHistory($this->totalRecords);

      for($i = 0; $i < $this->totalRecords; $i++){
        
        $xml_product = [
          'category' => $item[$i]->{$this->settings['fieldCategory']}->__toString(),
          'name' => $item[$i]->{$this->settings['fieldName']}->__toString(),
          'brand' => $item[$i]->{$this->settings['fieldBrand']}->__toString(),
          'inStock' => $item[$i]->{$this->settings['fieldInStock']}->__toString(),
          'code' => $item[$i]->{$this->settings['fieldCode']}->__toString() ?? null,
          'barcode' => $item[$i]->{$this->settings['fieldBarcode']}->__toString() ?? null,
          'price' => $item[$i]->{$this->settings['fieldPrice']}->__toString(),
        ];

        if($this->validateData($xml_product)) {
          // TRY TO FIND EXISTE PRODUCT
          try {
            $response = $this->updateOrCreateItem($xml_product);
            $this->updateUploadHistory($response);
          }catch(\Exception $e) {
            $this->errorUploadHistory();
				    \Log::channel('xml')->error($e->getMessage());
            // throw new \Exception $e;
          }
        }else {
          $this->processedUploadHistory();
          continue;
        }

      }

      $this->setStatusUploadHistory('done');
    }
        
    /**
     * setStatusUploadHistory
     *
     * @param  mixed $status
     * @return void
     */
    private function setStatusUploadHistory($status) {
      $uh = $this->uploadHistory;
      $uh->status = $status;
      $uh->save();
    }
    
    /**
     * createUploadHistory
     *
     * @return void
     */
    private function createUploadHistory() {
      $uh = new UploadHistory;
      $uh->source_id = $this->currentSource->id;
      $uh->status = 'pending';
      $uh->rules = $this->currentSource->rules;
      $uh->save();
      //
      $this->uploadHistory = $uh;
    }
    
    /**
     * processedUploadHistory
     *
     * @return void
     */
    private function processedUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->save();
    }
    
    /**
     * errorUploadHistory
     *
     * @return void
     */
    private function errorUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->error_items += 1;
      $uh->save();
    }

        
    /**
     * totalUploadHistory
     *
     * @param  mixed $total
     * @return void
     */
    private function totalUploadHistory($total) {
      $uh = $this->uploadHistory;
      $uh->total_items = $total;
      $uh->save();
    }
    
    
    /**
     * updateUploadHistory
     *
     * @param  mixed $data
     * @return void
     */
    private function updateUploadHistory($data) {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;

      if($data === 'update') {
        $uh->updated_items += 1;
      }else if($data === 'create') {
        $uh->new_items += 1;
      }

      $uh->save();
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
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateProduct($data) {
      $update_or_create = 'update';

      $product = Product::where('id', '>', 0);

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

                  // when(!empty($data['code']), function($query) use($data) {
                  //   $query->where('code', $data['code']);
                  // })
                  // ->when(!empty($data['barcode']), function($query) use($data) {
                  //   if(!empty($data['code'])) {
                  //     $function_name = 'orWhere';
                  //   }else {
                  //     $function_name = 'where';
                  //   }

                  //   $query->{$function_name}('barcode', $data['barcode']);
                  // })
                // ->orWhereRaw("LOWER(`name->{$this->lang}`) LIKE ? ",[trim(strtolower($data['name'])).'%'])
                // ->first();

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
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateSupplierProduct($data) {
      $update_or_create = 'update';

      // $sp = SupplierProduct::
      //         where('supplier_id', $this->currentSource->supplier_id)
      //       ->when(!empty($data['code']), function($query) use($data) {
      //         $query->where('code', $data['code']);
      //       })
      //       ->when(!empty($data['barcode']), function($query) use($data) {
      //         if(!empty($data['code'])) {
      //           $function_name = 'orWhere';
      //         }else {
      //           $function_name = 'where';
      //         }

      //         $query->{$function_name}('barcode', $data['barcode']);
      //       })
      //       ->first();
      
      $sp = SupplierProduct::
              where('supplier_id', $this->currentSource->supplier_id);


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

      $sp = $sp->first();

      if(!$sp) {
        $update_or_create = 'create';

        $sp = new SupplierProduct;

        // Is Not SupplierProduct means is not Product also
        $product = $this->createProduct($data);

        // Save product
        $product->save();

        // Set category to product
        $this->attachProductCategory($product, $data);
      }else {
        $product = $sp->product;
      }

      // Update Code, Barcode, inStock, price
      $this->setSupplierData($sp, $data);
    
      // Attach Supplier Product to Product
      $sp->product_id = $product->id;
      $sp->save();

      return $update_or_create;
    }
    
    /**
     * createProduct
     *
     * @return void
     */
    private function createProduct($data) {
      $product = new Product();
      $this->setProductInitFields($product);
      $this->setProductName($product, $data);

      // Set brand to product
      $this->attachProductBrand($product, $data);

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

      if(empty($this->stockRules)) {
        $in_stock = intval($data['inStock']);
      }

      $rule = $this->stockRules[$data['inStock']] ?? null;

      // if rule not exists set value to zero
      if($rule === null) {
        $in_stock = 0;
      }else {
        $in_stock = intval($rule['value']);  
      }

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
              $query->{$function_name}("LOWER(`name->{$lang_key}`) LIKE ? ",[trim(strtolower($data['brand']))]);
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
      
      // Else create new brand
      $brand = new Brand;
      $brand->setTranslation('name', $this->lang, $data['brand']);
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
      // SET SOURCE TO NEW PRODUCT
      // $product->parsed_from = 'dobavki.ua';
      // SET SUPPLIER TO NEW PRODUCT
      // $product->supplier_id = 40;
    }
    
    /**
     * setProductName
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function setProductName(&$product, $data) {
      $product->setTranslation('name', $this->lang, $data['name']);
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
      // Get Exchange rates
      $this->exchange_rate = $this->getRate();

      $this->currentSource = $source;

      // Fill Settings
      $this->settings = $source->settings;

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
        if($rule['min_price'] !== null && $product['price'] >= $rule['min_price']) {
          return true;
        }

        if($rule['max_price'] !== null && $product['price'] <= $rule['max_price']) {
          return true;
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
     * getXMLCatalog
     *
     * @return void
     */
    private function getXMLCatalog($url) {
	    try 
	    {
	    	$xml = simplexml_load_file($url);
	    }
	    catch(\Exception $e)
	    {  
		    $message = "Can't get products catalog: " . $e->getMessage();
		    
				\Log::channel('xml')->error($message);
				throw new \Exception($message);
			}
	    
      return $xml;
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
