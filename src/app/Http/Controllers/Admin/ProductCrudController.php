<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\ProductRequest;

use Backpack\Store\app\Http\Controllers\Admin\Base\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Illuminate\Database\Eloquent\Builder;

// MODELS
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Supplier;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\SupplierProduct;

//EVENTS
use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Events\ProductCreating;

//

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ProductCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \App\Http\Controllers\Admin\Traits\ProductCrud;
    use \Backpack\Store\app\Http\Controllers\Admin\Traits\Product\ProductFiltersTrait;
    use \Backpack\Store\app\Http\Controllers\Admin\Traits\Product\ProductFieldsTrait;
    use \Backpack\Store\app\Http\Controllers\Admin\Traits\Product\ProductColumnsTrait;

    protected $setupDetailsRowRoutes = true;
    
    private $categories;
    private $filter_categories;
    private $filter_brands;
    private $suppliers_list;
    private $brands;
    private $attrs;

    private $available_languages = [];
    private $langs_list = [];
    
    private $product_class = null;

    public function __construct() {
      $this->product_class = config('backpack.store.product.class_admin', 'Backpack\Store\app\Models\Admin\Product');

      // Set event listiner to Model
      $this->product_class::saved(function($entry) {
        // Attach attributes here
        ProductSaved::dispatch($entry);
      });


      // Set event listiner to Model
      $this->product_class::creating(function($entry) {
        // Attach attributes here
        ProductCreating::dispatch($entry);
      });

      parent::__construct();
    }

    public function setup()
    {

      $this->crud->enableExportButtons(); 

      $this->crud->setModel($this->product_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/product');
      $this->crud->setEntityNameStrings(
          trans('backpack-store::product.entity_singular'),
          trans('backpack-store::product.entity_plural')
      );

      if(config('backpack.store.supplier.enable', false)) {
        $this->crud->enableDetailsRow();
      }

      // SET LOCALE
      $this->setLocale();

      // SET OPERATION
      $this->setOperation();

      // CURRENT MODEL
      $this->setEntry();
      
      // SET PARENT MODEL
      $this->setParentEntry();
        
      // SET CATEGORY MODEL
      $this->setCategories();

      // SET ATTRIBUTES MODEL 
      $this->setAttrsForCategories();
      
      $this->filter_categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
      $this->filter_brands = Brand::pluck('name', 'id')->toArray();
      
      $this->suppliers_list = Supplier::pluck('name', 'id')->toArray();

      // available languages
      $this->available_languages = config('backpack.crud.locales');
      $this->langs_list = array_keys($this->available_languages);
    }
    
    /**
     * Method fetchOrder
     *
     * @return void
     */
    protected function fetchOrder()
    {
        return $this->fetch(\Backpack\Store\app\Models\Order::class);
    }
    
    /**
     * Method setupListOperation
     *
     * @return void
     */
    protected function setupListOperation()
    {
        // Common Classes
        $this->crud->addClause('withSum', 'sp', 'in_stock');

        // System Trait   
        $this->setupFilters();

        // Add bulk actions button
        $this->crud->addButton('bottom', 'bulk_actions', 'view', 'store-crud::buttons.product_bulk_actions', 'end');

        //
        $this->crud->addButton('top', 'export_csv', 'view', 'store-crud::buttons.product_bulk_actions', 'top_search');

        // System Trait   
        $this->setupColumns();

        // User Trait
        $this->listOperation();
    }
    
    /**
     * Method setupCreateOperation
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProductRequest::class);

        // System Trait
        $this->setupFields();
        
        // User Trait
        $this->createOperation();
    }
    
    /**
     * Method setupUpdateOperation
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
    }
    
    
    /**
     * Method showDetailsRow
     *
     * @param $id $id [explicite description]
     *
     * @return void
     */
    public function showDetailsRow($id) {
      $sps = SupplierProduct::
                where('product_id', $id)
              ->orderByRaw('IF(in_stock > ?, ?, ?) DESC', [0, 1, 0])
              ->orderBy('price')
              ->get();

      $currency = config('backpack.store.currency.symbol');

      return view('store-crud::details.product_suppliers', compact('sps', 'currency'));
    }

    
    /**
     * setAttrsForCategories
     * 
     * Set all attributes for attached categories 
     *
     * @return void
     */
    private function setAttrsForCategories() {
      // if operation type differ from create/update go out
      if(!in_array($this->opr, ['create', 'update']))
        return;

      // create empty collection
      $this->attrs = collect();

      // if categories have not been set go out
      if(!$this->categories || !$this->categories->count())
        return;
      
      // 
      foreach($this->categories as $category) {
        
        $category_parent_node = $category->getParentNode();

        foreach($category_parent_node as $category) {
          // Take all active attributes for this category 
          $cat_attrs = $category->attributes()->active()->get();
  
          // If isset active attributes for this category merge with common list
          if($cat_attrs && $cat_attrs->count()) {
            $this->attrs = $this->attrs->merge($cat_attrs);
          }
        }
      }
    }
    
    /**
     * setCategories
     *
     * @return void
     */
    private function setCategories()
    {
      if(!in_array($this->opr, ['create', 'update']))
        return;

      $query_category_ids = \Request::query('category_id');

      if($query_category_ids) {
        $this->categories = Category::whereIn('id', $query_category_ids)->get();
        return;
      }

      
      if(isset($this->parent_entry) && !empty($this->parent_entry))
      {
        $this->categories = $this->parent_entry->categories;
      }
      elseif($this->entry)
      {
        $this->categories = $this->entry->categories;
      }
    }
    
    /**
     * setEntry
     *
     * @return void
     */
    private function setEntry() {
      if($this->crud->getCurrentOperation() === 'update')
        $this->entry = $this->crud->getEntry(\Route::current()->parameter('id'));
      else
        $this->entry = null;
    }
    
    /**
     * setParentEntry
     *
     * @return void
     */
    private function setParentEntry() {
      if(!empty($parent_id = \Request::query('parent_id')))
        $this->parent_entry = $this->crud->getEntry($parent_id);
      elseif($this->entry && $this->entry->parent){
        $this->parent_entry = $this->entry->parent;
      }else{
        $this->parent_entry = null;
      }
    }
    
    /**
     * setOperation
     *
     * @return void
     */
    private function setOperation() {
      $this->opr = $this->crud->getCurrentOperation();
    }
    
    /**
     * setLocale
     *
     * @return void
     */
    private function setLocale() {
      if(\Request::query('locale'))
        app()->setLocale(\Request::query('locale'));
    }

    
    /**
     * getProductsRouter
     *
     * @param  mixed $request
     * @return void
     */
    public function getProductsRouter(Request $request) {
      $search_term = $request->input('q');

      // langs
      $langs_list = $this->langs_list;

      if ($search_term)
      {
        $locale = \Lang::locale();

        $results = $this->product_class::
            // where("name->{$locale}", 'LIKE', "%" . $search_term . "%")
            where(function($query) use ($search_term, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($search_term)).'%']);
              }
            })
          ->orWhere('code', 'LIKE', '%'.$search_term.'%')
          ->orWhereHas('sp', function($query) use($search_term) {
            $query->where('code', 'LIKE', '%'.$search_term.'%')->orWhere('barcode', 'LIKE', '%'.$search_term.'%');
          })
          ->orWhere('slug', 'LIKE', '%'.$search_term.'%')
          // ->orWhere("short_name->{$locale}", 'LIKE', '%'.$search_term.'%')
          ->paginate(20);
      }
      else
      {
          $results = $this->product_class::paginate(20);
      }

      return $results;
    }
    
    /**
     * Method toggleIsActiveRouter
     *
     * @param $id $id [explicite description]
     *
     * @return void
     */
    public function toggleIsActiveRouter($id)
    {
        $this->crud->hasAccessOrFail('update');

        $entry = $this->crud->model->findOrFail($id);
        $entry->is_active = request()->input('is_active', 0);
        $entry->save();

        return response()->json(['success' => true]);
    }


    /**
     * Method handleBulkActionRouter
     *
     * @param $action $action [explicite description]
     *
     * @return void
     */
    public function handleBulkActionRouter($action)
    {
        $this->crud->hasAccessOrFail('update');

        $ids = request()->input('ids', []);
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => trans('backpack-store::bulk_actions.select_items'),
            ]);
        }

        switch ($action) {
            case 'set_active':
                $this->crud->model->whereIn('id', $ids)->update(['is_active' => 1]);
                return response()->json([
                    'success' => true,
                    'message' => trans('backpack-store::bulk_actions.activated', ['count' => count($ids)]),
                ]);

            case 'set_inactive':
                $this->crud->model->whereIn('id', $ids)->update(['is_active' => 0]);
                return response()->json([
                    'success' => true,
                    'message' => trans('backpack-store::bulk_actions.deactivated', ['count' => count($ids)]),
                ]);


            case 'set_brand':
                $brandId = request()->input('brand_id');
                
                // Обновляем бренд для всех выбранных продуктов
                $this->crud->model->whereIn('id', $ids)->update(['brand_id' => $brandId]);

                $message = is_null($brandId) 
                    ? trans('backpack-store::bulk_actions.brand_removed', ['count' => count($ids)])
                    : trans('backpack-store::bulk_actions.brand_assigned', ['count' => count($ids)]);

                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);

            case 'set_category':
                $categoryIds = request()->input('category_ids', []);
                
                // Remove empty values that might come from "Без категории" option
                $categoryIds = array_filter($categoryIds, function($value) {
                    return $value !== '';
                });
                
                // Получаем все продукты которые нужно обновить
                $products = $this->crud->model->whereIn('id', $ids)->get();
                
                foreach ($products as $product) {
                    if (empty($categoryIds)) {
                        // Если категории пустые или выбран "Без категории" - отвязываем все категории
                        $product->categories()->detach();
                    } else {
                        // Получаем текущие категории продукта
                        $existingCategoryIds = $product->categories()->pluck('ak_product_categories.id')->toArray();
                        
                        // Фильтруем только новые категории, которых еще нет у продукта
                        $newCategoryIds = array_diff($categoryIds, $existingCategoryIds);
                        
                        if (!empty($newCategoryIds)) {
                            // Добавляем только новые категории
                            $product->categories()->attach($newCategoryIds);
                        }
                    }
                }

                $message = empty($categoryIds) 
                    ? trans('backpack-store::bulk_actions.categories_removed', ['count' => count($ids)])
                    : trans('backpack-store::bulk_actions.categories_assigned', ['count' => count($ids)]);

                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);

            default:
                return response()->json([
                    'success' => false,
                    'message' => trans('backpack-store::bulk_actions.invalid_action'),
                ]);
        }
    }
}
