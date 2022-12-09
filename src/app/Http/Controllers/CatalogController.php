<?php

namespace Aimix\Shop\app\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Models\Product;
use Aimix\Shop\app\Models\Attribute;
use Aimix\Shop\app\Models\AttributeGroup;
use Aimix\Shop\app\Models\Category;
use Aimix\Shop\app\Models\Modification;
use Aimix\Shop\app\Models\Brand;
use App\Models\Country;
use Backpack\PageManager\app\Models\Page;

class CatalogController extends \App\Http\Controllers\Controller
{
	
	//
	// PROPERTIES 
	//	
	
	// filters etc.
	private $search_type = 'name';
	private $search_value = null;
	private $attributes = [];
  private $country = null;
	
	private $filters = [
		'search_type' => 'brand',
		'search_value' => null,
		'attributes' => [],
    'country' => [],
	];
	
	private $isJson = false;
	private $per_page = null;
	private $sort = [
		'value' => 'created_at',
		'dirr' => 'asc'
	];
	private $sort_string = 'created_at_asc';
	
	private $category = null;
	
	// data arrays
	private $all_filters = [];
	private $products = [];
	
	private $selected_filters = [];
  
  private $price_filter = null;
	
	//
	// METHODS
	//
	public function __construct() {
    	$this->per_page = config('aimix.shop.per_page');
	}
	
	// get overall catalog data
    public function index(Request $request, $lang, $category_slug = null) {

		// set properties
	    $this->setPropertiesFromRequest($request);
	    // if category pages disabled
	    // if($category_slug && !$this->isJson && !config('aimix.shop.enable_product_category_pages'))
	      // return redirect('/catalog');
	
	    // It should be right here (for category links where category pages disabled)
	    // if($request->category_slug)
	    //   $category_slug = $request->category_slug;
	
	    // if there is no category with this slug
	    if($category_slug && !Category::where('slug', $category_slug)->first())
	      return redirect('/shop');
	
			// Make request to DB
			if($this->search_value)
			{
				if($this->search_type == 'name')
					$this->products = Product::where('name', 'like', '%'.$this->search_value.'%');
				elseif($this->search_type == 'brand')
					$this->products = Product::whereHas('brand', function(Builder $query){
						$query->where('name', 'like', '%'.$this->search_value.'%');
					});
			}else
				$this->products = Product::where('products.id', '!=', null);
	      
	        foreach($this->attributes as $attr_id => $attr_value) {
	           $this->products = $this->products->whereHas('modifications', function(Builder $query) use($attr_id, $attr_value) {
	               $query->active()->notBase()->whereHas('attrs', function(Builder $attr_query) use($attr_id, $attr_value) {
	                 $attr_query->where('attribute_id', $attr_id)->whereJsonContains('value', $attr_value);
	               });         
	           });
	        }
	    
	    if($this->country && $this->country != 'Не выбрано')
	    {
	      $country_id = Country::where('name', $this->country)->first()->id;
	      
	      $this->products = $this->products->whereHas('brand', function(Builder $query) use ($country_id) {
	        $query->where('country_id', $country_id);
	      });
	    }
	    
			$path = $this->setPath($request);
	
	    // if($category_slug) {
	    //   $this->products = $this->products->whereHas('category', function(Builder $query) use ($category_slug) {
	    //     $query->where('slug', $category_slug);
	    //   });
	    // }
	    
	    if($this->price_filter) {
	      $price_fil = $this->price_filter;
	      $this->products = $this->products->whereHas('modifications', function(Builder $query) use ($price_fil) {
	        $query->where('is_default', 1)->where('price', '>=', $price_fil[0])->where('price', '<=', $price_fil[1]);
	      });
	    }
	    
	    $range_min = null;
	    $range_max = null;
	    
	/*
	    foreach($this->products->get()->toArray() as $product) {
	      if(!$range_min || $range_min > $product['price'])
	        $range_min = $product['price'];
	      
	      if(!$range_max || $range_max < $product['price'])
	        $range_max = $product['price'];
	    }
	*/
	    
	    $range_options = [
	      'min' => $range_min,
	      'max' => $range_max,
	      'step' => 2
	    ];
	    
	    
	    if($this->sort['value'] == 'price') {
	      $this->products = $this->products->where('products.is_active', 1)->join('modifications', 'modifications.product_id', '=', 'products.id')->select('products.*')
	                    ->orderBy('modifications.price', $this->sort['dirr'])
	                    ->paginate($this->per_page)->withPath($path);
	    } else {
	      $this->products = $this->products->active()
	                      ->orderBy($this->sort['value'], $this->sort['dirr'])
	                      ->paginate($this->per_page)->withPath($path);
	    }
	
			$categories = Category::noEmpty()->get()->keyBy('id');

		// Because life is meaningless
		if(Category::where('slug', $lang)->first())
			$category_slug = $lang;
			
		$currentCategory = $category_slug? Category::where('slug', $category_slug)->first(): Category::find(1);
	    
	    //$page = Page::find(12)->withFakes();
			// Generate response
			if($this->isJson)
				return response()->json(['products' => $this->products, 'aatr' => $this->attributes, 'range_options' => $range_options]);
			else
				return view('catalog.index')
						->with('products', $this->products)
						->with('filters', $this->all_filters)
						->with('selected_filters', (object)$this->selected_filters)
						->with('category', $category_slug)
						->with('currentCategory', $currentCategory->withFakes())
						->with('categories', $categories)
						->with('range_options', $range_options);
    }
    
    // some setters
    private function setPath($request){
      $path = $request->url().'?per_page='.$this->selected_filters['per_page'].'&page='.$this->selected_filters['page'].'&sort='.$this->selected_filters['sort'];
      
      if($this->selected_filters['filters']['search_value'])
        $path .= '&filters[search_type]='.$this->selected_filters['filters']['search_type'].'&filters[search_value]='.$this->selected_filters['filters']['search_value'];
      
      if($this->selected_filters['filters']['country'])
        $path .= '&filters[country]='.$this->selected_filters['filters']['country'];
	    
	    foreach($this->selected_filters['filters']['attributes'] as $key => $attribute){
		    $path .= '&filters[attributes]['.$key.']='.$attribute;
	    }
      
	    return $path;
    }
    
    private function setPropertiesFromRequest($request){

	    if($request->category_slug){
        $this->category = Category::where('slug', $request->category_slug)->first();
			}
      
      if($request->price){
        $this->price_filter = $request->price;
      } else {
        $this->price_filter = null;
      }
		
      if($this->category) {
        $this->all_filters = [
            'attributes' => $this->category->attributes()->where('in_filters', 1)->get(),
            // 'countries' => Country::noEmpty()->get()
        ];
      }

		// Set properties form request
		if($request->has('filters'))
		{
			if(isset($request->input('filters')['search_type']))
				$this->search_type = $request->input('filters')['search_type'];
			else
				$this->search_type = 'brand';
				
			if(isset($request->input('filters')['search_value']))
				$this->search_value = $request->input('filters')['search_value'];
			else
				$this->search_value = null;
				
			if(isset($request->input('filters')['attributes']))
				$this->attributes = $request->input('filters')['attributes'];
			else
				$this->attributes = [];
        
      if(isset($request->input('filters')['country']))
				$this->country = $request->input('filters')['country'];
					
			$this->filters = $request->input('filters');
		}
      
		if($request->has('per_page'))
			$this->per_page = $request->input('per_page');
		
		if($request->has('sort')){
			$this->sort_string = $request->input('sort');
			$this->sort = $this->getSortArray($request->input('sort'));
		}
		
		$this->attributes = array_filter($this->attributes, function($item){
			return $item == 'Не выбрано'? false : true;
		});

		if($request->isJson)
			$this->isJson = $request->isJson;
		
		$this->selected_filters = [
			'isJson' => true,
			'filters' => [
				'search_type' => $this->search_type,
				'search_value' => $this->search_value,
				'attributes' => $this->attributes,
        'country' => $this->country, 
			],
	        'sort' => $this->sort_string,
	        'per_page' => $this->per_page,
	        'page' => 1
	    ];
    } 
    
    private function getSortArray($sort_string){
	    preg_match_all("/([\w]+)_([\w]+)/", $sort_string, $value);
	    
	    return ['value' => $value[1][0], 'dirr' => $value[2][0]];
    }
    
    public function requestSearchList(Request $request, $type, $value) {
      $values = [];

      if($type == 'brand' && $value) {
        $values = Brand::noEmpty()->where('name', 'like', '%'.$value.'%')->get();
      }elseif($type == 'name' && $value) {
        $values = Product::where('is_active', 1)->where('name', 'like', '%'.$value.'%')->get();
      }
      
      return response()->json($values);
    }
    
    // public function show(Request $request, $category_slug, $slug) {
    //   $lang = session()->has('lang')? session()->get('lang') : 'ru';
    //   $category = Category::where('slug', $category_slug)->first();
    //   $typeId = ($lang == 'ru')? 2 : 9;
      
    //   // if there is no category with this slug
    //   if(!$category)
    //     return redirect('/catalog');

    //   $category_id = $category->id;
      
    //   $product = Product::where('category_id', $category_id)->where('slug', $slug)->active()->first();
    //   $attribute_groups = AttributeGroup::orderBy('lft')->get();
    //   $in_stock = $product->toArray()['in_stock'];
    //   $product_type = $product->baseAttributes->find($typeId)->pivotValue;
      
    //   // if there is no product with this slug
    //   if(!$product) {
    //     if(config('aimix.shop.enable_product_category_pages'))
    //       return redirect('/catalog/' . $category_slug);

    //     return redirect('/catalog');
    //   }
      
    //   $same_category_products = Product::where('category_id', $category_id)->where('slug', '!=', $slug)->whereHas('modifications', function(Builder $query) use($typeId, $product_type){
    //     $query->where('is_default', 1)->whereHas('attrs', function(Builder $attrs_query) use($typeId, $product_type){
    //       $attrs_query->where('attribute_id', $typeId)->whereJsonContains('value', $product_type);
    //     });
    //   })->get();
      
    //   $recommended_products = Product::where('category_id', $category_id)->where('slug', '!=', $slug)->whereHas('modifications', function(Builder $query) use($typeId, $product_type){
    //     $query->where('is_default', 1)->whereHas('attrs', function(Builder $attrs_query) use($typeId, $product_type){
    //       $attrs_query->where('attribute_id', $typeId)->whereJsonDoesntContain('value', $product_type);
    //     });
    //   })->get();
      
    //   $reviews = $product->reviews()->where('is_moderated', 1)->orderBy('created_at', 'desc')->get();

    //   return view('catalog.show')->with('product', $product)->with('attribute_groups', $attribute_groups)->with('same_category_products', $same_category_products)->with('in_stock', $in_stock)->with('recommended_products', $recommended_products)->with('reviews', $reviews);
    // }
}
