<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\ProductRequest;
use Backpack\Store\app\Http\Requests\ProductModificationRequest;

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
use Backpack\Store\app\Events\ProductSaving;
use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Events\ProductCreating;

// use App\Http\Controllers\Admin\Traits\ProductCrud;

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
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
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
      $this->product_class = \Settings::get('dress.product.model_admin', 'Backpack\Store\app\Models\Admin\Product');

      // Set event listiner to Model
      $this->product_class::saved(function($entry) {
        // Attach attributes here
        ProductSaved::dispatch($entry);
      });


      $this->product_class::saving(function($entry) {
        ProductSaving::dispatch($entry);
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

      if(\Settings::get('dress.supplier.enable', false)) {
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
    
    public function update()
    {
        $response = $this->traitUpdate();

        // Upsell
        $links = request()->input('linksData', []);
        $this->syncLinks($this->crud->entry->id, $links);

        return $response;

    }
    public function store()
    {  

        $response = $this->traitStore();

        // Upsell
        $links = request()->input('linksData', []);
        $this->syncLinks($this->crud->entry->id, $links);

        return $response;
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

      $op = $this->crud->getCurrentOperation();

      if($op !== 'create') {
        return;
      }

        // $this->crud->setValidation(ProductRequest::class);

        // System Trait
        // $this->setupFields();
        
        // User Trait
        // $this->createOperation();

      // 1) Кладём кастомный шаблон (он один и для create, и для edit)
      $this->crud->setCreateView('store-crud::create_product');

      // 2) Если передан parent_id — это создание модификации. По умолчанию показываем ЛАЙТ-набор полей.
      $isVariantCreate = request()->filled('parent_id');
      $full = request()->boolean('full'); // ?full=1 для «все поля»

      if($isVariantCreate) {
        $this->crud->setValidation(ProductModificationRequest::class);
      }

      if ($isVariantCreate && !$full) {
        $this->addLiteFields();
      } else {
        // System Trait
        $this->setupFields();

        // User Trait
        $this->createOperation();
      }

      // 3) Значение поля parent_id
      // if ($isVariantCreate) {
      //     $this->crud->addField([
      //         'name'  => 'parent_id',
      //         'type'  => 'hidden',
      //         'value' => (int) request('parent_id'),
      //     ]);
      // }
      if($parentId = request()->input('parent_id')) {
        $parent = $this->product_class::findOrFail($parentId);

        $this->crud->setOperationSetting('parent', [
            'parent'   => $parent,
            'isBaseProduct'  => $parent? false: true,
        ]);
      }

    }
    
    /**
     * Method setupUpdateOperation
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
      // $this->setupCreateOperation();

      $this->crud->setEditView('crud::edit_product');

      $entry = $this->crud->getCurrentEntry();
      $isVariant = (bool) $entry->parent_id;
      $full = request()->boolean('full'); // ?full=1 для «все поля»

      if($isVariant) {
        $this->crud->setValidation(ProductModificationRequest::class);
      }else{
        $this->crud->setValidation(ProductRequest::class);
      }

      if ($isVariant && !$full) {
          $this->addLiteFields();
      } else {
        // System Trait
        $this->setupFields();

        // User Trait
        $this->createOperation();
      }

      // ---- ДАННЫЕ ДЛЯ ТАБОВ МОДИФИКАЦИЙ (и для create, и для edit) ----
      // в update — показываем siblings (все дети одного parent) либо своих детей
      $parent = $entry->parent ?: $entry;
      $siblings = $parent->children()
          ->select('id','short_name','price')
          ->orderBy('id')
          ->get()
          ->map(function($p){
              // $title = trim(($p->short_name ?: '—').' - '.(isset($p->simplePrice) ? $p->simplePrice : '—'), ' -');
              $title = $p->short_name;
              return [
                  'id'    => $p->id,
                  'title' => $title ?: ('#'.$p->id),
                  'parentId' => $p->parent_id
              ];
          })->values()->all();

      // отдадим в шаблон через operation settings
      $this->crud->setOperationSetting('variantTabs', [
          'currentId'  => $entry->id,
          'parentId'   => $parent->id,
          'items'      => $siblings,
          'isBaseProduct'     => $isVariant? false: true,
      ]);


      // \Backpack\Reviews\Facades\Reviews::attachToCrud($this->crud, 'Отзывы');
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

      $currency = \Settings::get('dress.store.currency.symbol');

      return view('store-crud::details.product_suppliers', compact('sps', 'currency'));
    }

    // Upsell
    protected function syncLinks($productId, string|array $payload): void
    {
        if (!is_array($payload)) {
            $payload = json_decode($payload ?? '[]', true);
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $entry = $this->crud->entry ?? null;
        $linkableType = $entry ? $entry->getMorphClass() : $this->product_class;
        $now = now();

        $rows = collect($payload)
            ->filter(fn($row) => is_array($row))
            ->map(function (array $row) use ($productId, $linkableType, $now) {
                $linkedProductId = (int) ($row['product_id'] ?? $row['links'] ?? $row['linked_product_id'] ?? 0);

                if ($linkedProductId <= 0 || $linkedProductId === (int) $productId) {
                    return null;
                }

                $kind = in_array($row['kind'] ?? null, ['cross', 'up'], true)
                    ? $row['kind']
                    : 'cross';

                $priority = (int) ($row['priority'] ?? 0);
                $priority = max(0, min(255, $priority));

                return [
                    'linkable_type' => $linkableType,
                    'linkable_id'   => $productId,
                    'product_id'    => $linkedProductId,
                    'kind'          => $kind,
                    'priority'      => $priority,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            })
            ->filter()
            ->unique(fn($row) => $row['product_id'].'_'.$row['kind'])
            ->values();

        \DB::transaction(function () use ($linkableType, $productId, $rows) {
            \DB::table('ak_product_links')
                ->where('linkable_type', $linkableType)
                ->where('linkable_id', $productId)
                ->delete();

            if ($rows->isNotEmpty()) {
                \DB::table('ak_product_links')->insert($rows->all());
            }
        });
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
      $search_keys = $request->input('keys');

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
      } elseif($search_keys) {
        $search_key_array = is_numeric($search_keys)? [$search_keys]: json_decode($search_keys, true);
        $results = $this->product_class::whereIn('id', $search_key_array)->get();
      } else {
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
