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
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    

    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \App\Http\Controllers\Admin\Traits\ProductCrud;

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
      $this->crud->setModel($this->product_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/product');
      $this->crud->setEntityNameStrings('товар', 'товары');

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

      // $this->crud->query = $this->crud->query->withoutGlobalScopes();
      
      // $this->crud->model->clearGlobalScopes();
      
      $this->filter_categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
      $this->filter_brands = Brand::pluck('name', 'id')->toArray();
      
      $this->suppliers_list = Supplier::pluck('name', 'id')->toArray();
      // if(config('backpack.store.brands.enable')) {
      //   $this->brands = Brand::NoEmpty()->pluck('name', 'id')->toArray();
      // }

      // available languages
      $this->available_languages = config('backpack.crud.locales');
      $this->langs_list = array_keys($this->available_languages);

      // $this->crud->model->clearGlobalScopes();

    }

    protected function fetchOrder()
    {
        return $this->fetch(\Backpack\Store\app\Models\Order::class);
    }


    protected function setupFilters() {

        // langs
        $langs_list = $this->langs_list;

        /* The above PHP code is adding a filter for the "brand" field in a CRUD (Create, Read, Update,
        Delete) interface. The filter allows users to select a brand from a dropdown list. If the
        user selects the option "🔴 Без бренда" (translation: "🔴 No brand"), the code filters the
        query to show only records where the "brand_id" is null. If a specific brand is selected,
        the code filters the query to show only records with that specific "brand_id". */
        $this->crud->addFilter([
          'name' => 'brand',
          'label' => 'Бренд',
          'type' => 'select2',
        ], function(){
          $list = ['empty' => '🔴 Без бренда'] + $this->filter_brands;
          return $list;
        }, function($id){
          if($id === 'empty') {
            $this->crud->query->where('brand_id', '=', null);
          }else {
            $this->crud->query->where('brand_id', $id);
          }
        });

        /* The above PHP code snippet is adding a filter for a category in a CRUD (Create, Read,
        Update, Delete) interface. The filter allows users to select a category from a dropdown
        list. */
        $this->crud->addFilter([
          'name' => 'category',
          'label' => 'Категория',
          'type' => 'select2',
        ], function(){
          $list = ['empty' => '🔴 Без категории'] + $this->filter_categories;
          return $list;
        }, function($id){
          if($id === 'empty') {
            $this->crud->query->has('categories', '=', 0);
          }else {
            $this->crud->query->whereHas('categories', function ($query) use ($id) {
                $query->where('category_id', $id);
            });
          }
        });


        /* The above PHP code snippet is adding a filter to a CRUD (Create, Read, Update, Delete)
        interface. The filter is for the 'is_active' field and is displayed as a select dropdown
        with two options: '🔴 Не активный' (Not active) and '🟢 Активный' (Active). When a user
        selects an option, the query will filter the results based on the selected 'is_active'
        value. */
        $this->crud->addFilter([
          'name' => 'is_active',
          'label' => 'Активный',
          'type' => 'select2',
        ], function(){
          return [
            0 => '🔴 Не активный',
            1 => '🟢 Активный',
          ];
        }, function($is_active){
          $this->crud->query->where('is_active', $is_active);
        });


        /* The above PHP code is adding a filter named 'modifications' to a CRUD (Create, Read, Update,
        Delete) interface. This filter is a select2 type filter with options 'Без модификаций'
        (Without modifications) and 'С модификациями' (With modifications). */
        $this->crud->addFilter([
          'name' => 'modifications',
          'label' => 'Модификации',
          'type' => 'select2',
        ], function(){
          return [
            0 => 'Без модификаций',
            1 => 'С модификациями',
          ];
        }, function($modifications){
          if($modifications) {
            $this->crud->query->has('parent')->orHas('children');
          }else {
            $this->crud->query->has('parent', '=', 0)->has('children', '=', 0);
          }
        });


        /* The above PHP code is defining a filter named 'translation' for a CRUD (Create, Read,
        Update, Delete) operation. The filter allows users to select a translation option from a
        dropdown list. */
        $this->crud->addFilter([
          'name' => 'translation',
          'label' => 'Перевод',
          'type' => 'select2',
        ], function(){
          $al = array_map(function($item) {
            return 'Нет ' . $item;
          }, $this->available_languages);

          $list = [
            0 => 'Нет (какого-то)',
            1 => 'Есть (все)'] + $al;

          return $list;
        }, function($translation){
          $langs_list = $this->langs_list;

          if($translation === '0') {
            $this->crud->query->where(function($query) use($langs_list) {
              foreach($langs_list as $index => $lang_key) {
                $function = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function}('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) < ? ', 150);
                $query->{$function}('JSON_EXTRACT(content, "$.' . $lang_key . '") IS NULL');
              }
            });
          }else if($translation === '1') {
            $this->crud->query->where(function($query) use($langs_list) {
              foreach($langs_list as $index => $lang_key) {
                $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) >= ? ', 150);
              }
            });
          }else {
            $this->crud->query->where(function($query) use($translation) {
              $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $translation . '")) < ? ', 150)
                  ->orWhereRaw('JSON_EXTRACT(content, "$.' . $translation . '") IS NULL');
            });
          }
        });

        /* The above PHP code snippet is adding a filter named 'filles' to a CRUD (Create, Read,
        Update, Delete) interface. This filter allows users to select the quality of data entry from
        a dropdown list with options for 'низкое' (low), 'среднее' (medium), and 'высокое' (high). */

        $this->crud->addFilter([
          'name' => 'filles',
          'label' => 'Качество заполнения',
          'type' => 'select2',
        ], function(){
          return [
            0 => 'низкое',
            1 => 'среднее',
            2 => 'высокое',
          ];
        }, function($filles){
          if($filles == 0) {
            $this->crud->query->fillQualityLow();
          }else if($filles == 1) {
            $this->crud->query->fillQualityNormal();
          }else if($filles == 2) {
            $this->crud->query->fillQualityHight();
          }
        });


        /* The above PHP code is adding a filter for a CRUD (Create, Read, Update, Delete) operation. The
        filter is for checking the availability of a product in stock. */
        $this->crud->addFilter([
          'name' => 'in_stock',
          'label' => 'Наличие',
          'type' => 'select2',
        ], function(){
          return [
            0 => '🔴 Нет в наличие',
            1 => '🟢 В наличие',
          ];
        }, function($in_stock){
          if($in_stock == 0) {
            $this->crud->query->where(function($query) {
              $query->whereHas('suppliers', function ($query) {
                $query->where('in_stock', '=', 0);
              })->orHas('suppliers', '=', 0);
            });
          }else {
            $this->crud->query->whereHas('suppliers', function ($query) {
              $query->where('in_stock', '>', 0);
            });
          }
        });
        

        /* The above PHP code snippet is adding a filter for a price range in a CRUD (Create, Read,
        Update, Delete) system. When this filter is applied, it will filter the data based on the
        price range specified by the user. */
        $this->crud->addFilter([
          'name' => 'price',
          'label' => 'Цена',
          'type' => 'range',
        ], false, function($value){
          $range = json_decode($value);
          
          if ($range->from) {
            $this->crud->addClause('whereHas', 'sp', function($query) use ($range) {
              $query->where('in_stock', '>', 0)->where('price', '>=', $range->from);
            });
          }
          if ($range->to) {
            $this->crud->addClause('whereHas', 'sp', function($query) use ($range) {
              $query->where('in_stock', '>', 0)->where('price', '<=', $range->to);
            });
          }
        });

        /* The above PHP code is adding a filter to a CRUD (Create, Read, Update, Delete) interface
        based on a condition from the configuration file. If the configuration setting
        'backpack.store.supplier.enable' is true, then a filter for selecting suppliers is added.
        The filter allows users to filter data based on the supplier associated with it. The filter
        includes an option for selecting records without a supplier ('🔴 Без поставщика') and a list
        of suppliers to choose from. Depending on the selected supplier, the query is modified to
        filter records accordingly. If 'empty' is */
        if(config('backpack.store.supplier.enable')) {
          $this->crud->addFilter([
            'name' => 'supplier',
            'label' => 'Поставщик',
            'type' => 'select2',
          ], function(){
            $list = ['empty' => '🔴 Без поставщика'] + $this->suppliers_list;
            return $list;
          }, function($id){
            if($id === 'empty') {
              $this->crud->query->has('suppliers', '=', 0);
            }else {
              $this->crud->query->whereHas('suppliers', function ($query) use ($id) {
                $query->where('supplier_id', $id);
              });
            }
          });
        }
    }

    public function handleBulkAction($action)
    {
        $this->crud->hasAccessOrFail('update');

        $ids = request()->input('ids', []);
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one item.',
            ]);
        }

        switch ($action) {
            case 'set_active':
                $this->crud->model->whereIn('id', $ids)->update(['is_active' => 1]);
                return response()->json([
                    'success' => true,
                    'message' => count($ids) . ' items have been activated.',
                ]);

            case 'set_inactive':
                $this->crud->model->whereIn('id', $ids)->update(['is_active' => 0]);
                return response()->json([
                    'success' => true,
                    'message' => count($ids) . ' items have been deactivated.',
                ]);

            case 'set_category':
                $categoryId = request()->input('category_id');
                
                // Получаем все продукты которые нужно обновить
                $products = $this->crud->model->whereIn('id', $ids)->get();
                
                foreach ($products as $product) {
                    if ($categoryId === '') {
                        // Если категория пустая - отвязываем все категории
                        $product->categories()->detach();
                    } else {
                        // Иначе синхронизируем с выбранной категорией
                        $product->categories()->sync([$categoryId]);
                    }
                }

                $message = empty($categoryId) 
                    ? count($ids) . ' items have had their categories removed.'
                    : count($ids) . ' items have been moved to the selected category.';

                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid action.',
                ]);
        }
    }

    protected function setupListOperation()
    {
        // langs
        $langs_list = $this->langs_list;

        $this->crud->addClause('withSum', 'sp', 'in_stock');

        $this->setupFilters();

        // Добавляем кастомную кнопку для bulk операций
        $this->crud->addButton('bottom', 'bulk_actions', 'view', 'store-crud::buttons.product_bulk_actions', 'end');


        $this->crud->addColumn([
          'name' => 'imageSrc',
          'label' => '📷',
          'type' => 'image',
          'height' => '60px',
          'width'  => '40px',
          'priority' => 2,
        ]);

        $this->crud->addColumn([
          'name' => 'adminCode',
          'label' => '<span title="Артикул товара или баркод">#️⃣</span>',
          'escaped' => false,
          'limit' => 2500,
          'priority' => 1,
          'searchLogic' => function ($query, $column, $searchTerm) {
            $query
              ->whereHas('sp', function($query) use($searchTerm) {
                $query->where('code', 'LIKE', '%'.$searchTerm.'%')
                ->orWhere('barcode', 'LIKE', '%'.$searchTerm.'%');
              })
              ->orWhere('code', 'LIKE', '%'.$searchTerm.'%');
          },
        ]);
        
        
        $this->crud->addColumn([
          'name' => 'inStockTotalSuppliers',
          'label' => '<span title="Сумарно товаров в наличие">📦</span>',
          'type' => 'number',
          'suffix' => ' шт.',
          'priority' => 4,
          'orderable'   => true,
          'orderLogic' => function ($query, $column, $columnDirection) {
            return $query->withSum('sp', 'in_stock')
                  ->orderBy('sp_sum_in_stock', $columnDirection);
          },
        ]);

        $this->crud->addColumn([
          'name' => 'simplePrice',
          'label' => 'Цена',
          'type' => 'number',
          'orderable'   => true,
          'orderLogic' => function ($query, $column, $columnDirection) {
            return $query
            ->leftJoin('ak_supplier_product', 'ak_supplier_product.product_id', '=', 'ak_products.id')
            ->orderBy('ak_supplier_product.price', $columnDirection)
            ->select('ak_products.*');
          },
          'priority' => 6,
        ]);
        
        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => '<span title="Активный ли товар?">✅</span>',
          'type' => 'toggle',
          'view_namespace' => 'store-crud::columns',
          'priority' => 5,
          'orderable'   => true,
        ]);

        // if(config('backpack.store.supplier.enable')) {
        //   $this->crud->addColumn([
        //     'name' => 'suppliers',
        //     'label' => '<span title="Колличество поставщиков">🚚</span>',
        //     'type' => 'relationship_count',
        //     'suffix' => '',
        //     'priority' => 5,
        //     'orderable'   => true,
        //     'orderLogic' => function ($query, $column, $columnDirection) {
        //       return $query->withCount('suppliers')
        //             ->orderBy('suppliers_count', $columnDirection);
        //       }
        //   ]);
        // }

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'textarea',
          'limit' => 100,
          'priority' => 1,
          'searchLogic' => function ($query, $column, $searchTerm) use($langs_list) {
            $query->orWhere(function($query) use ($searchTerm, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
              }
            });
          },
        ]);

        $this->crud->addColumn([
          'name' => 'adminTranslations',
          'label' => '<span title="Переводы">🌐</span>',
          'escaped' => false,
          'limit' => 1500,
          'priority' => 7
        ]);

        // $this->crud->addColumn([
        //   'name' => 'categories',
        //   'label' => 'Категории',
        //   'type'  => 'model_function',
        //   'function_name' => 'getCategoriesString',
        //   'limit' => 200,
        //   'priority' => 7
        // ]);

        $this->crud->addColumn([
          'name' => 'categories',
          'label' => 'Категории',
          'type'  => 'select2_multiple',
          'model' => Category::class,
          'attribute' => 'name',
          'data_source' => url('admin/api/category'),
          'max_width' => '400px',
          // 'limit' => 200,
          'priority' => 7
        ]);
        

        $this->crud->addColumn([
          'name' => 'fillAdmin',
          'label' => '<span title="Качество заполнения">💎</span>',
          'escaped' => false,
          'limit' => 1500,
          'priority' => 4
        ]);


        $this->listOperation();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProductRequest::class);

        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
          'tab' => 'Основное'
        ]);
        

        // CODE
        if(config('backpack.store.product.code.enable', true)) {
          $this->crud->addField([
            'name' => 'code',
            'label' => 'Артикул',
            'wrapper'   => [ 
              'class' => 'form-group col-md-6'
            ],
            'hint' => 'Заполните если хотите чтобы у товара был фиксированный артикул, иначе будут выводиться артикулы от поставщиков.',
            'tab' => 'Основное'
          ]);
        }

        if(!config('backpack.store.supplier.enable', false)) {
          $this->crud->addField([
            'name' => 'defaultSupplier[barcode]',
            'label' => 'Баркод/код',
            'wrapper'   => [ 
              'class' => 'form-group col-md-6'
            ],
            'value' => $this->entry->defaultSupplier['barcode'] ?? null,
            'tab' => 'Основное'
          ]);
        }

        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
          'tab' => 'Основное'
        ]);

        
        // SLUG
        $this->crud->addField([
          'name' => 'slug',
          'label' => 'URL',
          'hint' => 'По умолчанию будет сгенерирован из названия.',
          'tab' => 'Основное'
        ]);


        if(!config('backpack.store.supplier.enable', false)) {
          $this->crud->addField([
            'name' => 'defaultSupplierVirtual',
            'type' => 'hidden',
            'value' => 'fakevalue'
          ]);

          // PRICE
          if(config('backpack.store.product.price.enable', true)) {
            $this->crud->addField([
              'name' => 'defaultSupplier[price]',
              'label' => 'Цена',
              'type' => 'number',
              'value' => $this->entry->defaultSupplier['price'] ?? null,
              'prefix' => config('backpack.store.currency.symbol'),
              'wrapper'   => [ 
                'class' => 'form-group col-md-4'
              ],
              'attributes' => [
                'step' => 0.01,
                'min' => 0
              ],
              'tab' => 'Основное'
            ]);
          }
  
          // OLD PRICE
          if(config('backpack.store.product.old_price.enable', true)) {
            $this->crud->addField([
              'name' => 'defaultSupplier[old_price]',
              'label' => 'Старая цена',
              'type' => 'number',
              'value' => $this->entry->defaultSupplier['old_price'] ?? null,
              'prefix' => config('backpack.store.currency.symbol'),
              'wrapper'   => [ 
                'class' => 'form-group col-md-4'
              ],
              'attributes' => [
                'step' => 0.01,
                'min' => 0
              ],
              'tab' => 'Основное'
            ]);
          }

          // IN STOCK
          $this->crud->addField([
            'name' => 'defaultSupplier[in_stock]',
            'label' => "Количество в наличии",
            'type' => 'number',
            'value' => $this->entry->defaultSupplier['in_stock'] ?? null,
            'tab' => 'Основное',
            'hint' => 'Кол-во товара будет автоматически вычитаться при совершении заказов на сайте.',
            'wrapper'   => [ 
              'class' => 'form-group col-md-4'
            ],
          ]);
        }
        


        $this->crud->addField([
          'name' => 'categories',
          'label' => 'Категории',
          'type' => 'select2_multiple',
          'entity' => 'categories',
          'attribute' => 'name',
          'model' => 'Backpack\Store\app\Models\Category',
          'tab' => 'Основное',
          'hint' => 'Характеристики товара зависят от выбранных категорий. После сохранения записи характеристики будут синхронизированы с категориями.',
          'value' => $this->categories? $this->categories: null,
          // 'attributes' => $category_attributes
        ]);


        // BRAND
        if(config('backpack.store.brands.enable')) {
          $this->crud->addField([
            'name' => 'brand',
            'label' => 'Бренд',
            'type' => 'select2',
            'entity' => 'brand',
            'attribute' => 'name',
            'model' => 'Backpack\Store\app\Models\Brand',
            'tab' => 'Основное',
          ]);
        }

        // DESCRIPTION
        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 7
          ],
          'tab' => 'Основное'
        ]);
        
        
        // IMAGES
        if(config('backpack.store.product.images.enable', true)) {
          $this->crud->addField([
            'name'  => 'images',
            'label' => 'Изображения',
            'type'  => 'repeatable',
            'fields' => [
              [
                'name' => 'src',
                'label' => 'Изображение',
                'type' => 'browse',
              ],
              [
                'name' => 'alt',
                'label' => 'alt'
              ],
              [
                'name' => 'title',
                'label' => 'title'
              ],
              [
                'name' => 'size',
                'type' => 'radio',
                'label' => 'Размер',
                'options' => [
                  'cover' => 'Cover',
                  'contain' => 'Contain'
                ],
                'inline' => true
              ]
            ],
            'new_item_label'  => 'Добавить изобрежение',
            'init_rows' => 1,
            'default' => [],
            'tab' => 'Изображения'
          ]);
        }
        
        
        // CUSTOM PROPERTIES
        $this->crud->addField([
          'name' => 'delim',
          'type' => 'custom_html',
          'value' => '<h3>Индивидуальные характеристики</h3>
            <p class="help-block">Уникальные, индивидуальные или малораспространенные свойства товаров.
            Заполняются индивидуально к каждому товару. Выводятся только в характеристиках товара (в фильтрах не исспользуются).
            (Переводы для каждой языковой версии заполняются отдельно).
            </p>',
          'tab' => 'Характеристики'
        ]);

        $this->crud->addField([
          'name' => 'custom_attrs',
          'label' => 'Индивидуальные характеристики',
          'type' => 'table',
          'entity_singular' => 'атрибут',
          'columns'         => [
              'name'  => 'Название',
              'value'  => 'Значение',
          ],
          'min' => 0,
          'fake' => true, 
          'store_in' => 'extras_trans',
          'tab' => 'Характеристики'
        ]);


        $this->crud->addField([
          'name' => 'delim_2',
          'type' => 'custom_html',
          'value' => '<h3>Атрибуты</h3><p class="help-block">Универсальные свойства товаров.
            Создаются и управляются отдельно в разделе <a href="'.url('/admin/attribute').'">Атрибуты</a>.
            Могут быть исспользованы в фильтрах и в характеристиках товара.</p>',
          'tab' => 'Характеристики'
        ]);

        // ATTRIBUTES
        if(config('backpack.store.attributes.enable', true)){
          $this->setAttributesFields();
        }

        // SEO FIELDS
        if(config('backpack.store.product.seo.enable', true)){
          $this->crud->addField([
              'name' => 'meta_title',
              'label' => "Meta Title", 
              'type' => 'text',
              'fake' => true, 
              'store_in' => 'seo',
              'tab' => 'SEO'
          ]);

          $this->crud->addField([
              'name' => 'meta_description',
              'label' => "Meta Description", 
              'type' => 'textarea',
              'fake' => true, 
              'store_in' => 'seo',
              'tab' => 'SEO'
          ]);
        }


        // SUPPLIERS
        if(config('backpack.store.supplier.enable')) {
          $this->crud->addField([
            'name'  => 'suppliersData',
            'label' => 'Поставшики',
            'type'  => 'repeatable',
            'fields' => [
                [
                    'name'    => 'supplier',
                    'type'    => 'select_from_array',
                    'label'   => 'Поставщик',
                    'options'     => $this->suppliers_list,
                    'allows_null' => false,
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'    => 'code',
                    'type'    => 'text',
                    'label'   => 'Артикул товара',
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'    => 'barcode',
                    'type'    => 'text',
                    'label'   => 'Код/баркод',
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'    => 'in_stock',
                    'type'    => 'number',
                    'label'   => 'В наличие, шт',
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'    => 'price',
                    'type'    => 'number',
                    'label'   => 'Цена',
                    'prefix' => config('backpack.store.currency.symbol'),
                    'attributes' => [
                      'step' => 0.01,
                      'min' => 0
                    ],
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'  => 'old_price',
                    'type'  => 'number',
                    'label' => 'Старая цена',
                    'prefix' => config('backpack.store.currency.symbol'),
                    'attributes' => [
                      'step' => 0.01,
                      'min' => 0
                    ],
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ],
                [
                    'name'  => 'updated_at',
                    'type'  => 'text',
                    'label' => 'Последнее обновление',
                    'attributes' => [
                      'readonly'  => 'readonly',
                      'disabled'  => 'disabled'
                    ]
                ],
            ],
        
            // optional
            'new_item_label'  => 'Добавить поставщика',
            'init_rows' => 1,
            'min_rows' => 2,
            'tab' => 'Склад',
          ]);
        }


        // MODIFICATIONS
        $this->crud->addField([
          'name' => 'delim_mod',
          'type' => 'custom_html',
          'value' => '<h3>Модификации</h3>',
          'tab' => 'Управление'
        ]);

        if(config('backpack.store.product.modifications.enable', true)) {
          $this->crud->addField([
            'name' => 'parent_id',
            'type' => 'hidden',
            'value' => \Request::query('parent_id') ?? null
          ]);
        }
        
        if(config('backpack.store.product.modifications.enable', true)) {

          $this->crud->addField([
            'name' => 'modifications',
            'label' => 'Связанные товары',
            'type'    => 'relationship',
            'model'     => 'Backpack\Store\app\Models\Product',
            'attribute' => 'name',
            'ajax' => true,
            'multiple' => true,
            'entity' => 'children',
            'data_source' => url("/admin/api/product"),
            'placeholder' => "Поиск по названию товара",
            'minimum_input_length' => 0,
            'inline_create' => [
              'entity' => 'product',
              'force_select' => true,
            ],
            'hint' => 'Связанные товары - это другие разновидности этого же товара. Найдите и прикрепите модификации к товару, чтобы связать их в одну группу.',
            'tab' => 'Управление'
          ]);

        }

        // SHORT NAME FOR MODIFICATIONS
        // if($this->entry && !$this->entry->isBase || \Request::get('parent_id')) {
        if(config('backpack.store.product.modifications.enable', true)) {
          $this->crud->addField([
            'name' => 'short_name',
            'label' => 'Краткое название этой модификации',
            'type' => 'text',
            'hint' => 'Краткое название этой модификации товара, будет исспользовано в списке модификаций на сайте. Это может быть вкус/цвет и т.п.',
            'tab' => 'Управление'
          ]);
        }
        // }

      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
    }
    

    public function showDetailsRow($id) {
      $sps = SupplierProduct::
                where('product_id', $id)
              ->orderByRaw('IF(in_stock > ?, ?, ?) DESC', [0, 1, 0])
              ->orderBy('price')
              ->get();

      if($sps->count()){
        $html = '<b>Поставщики</b>';
  
        $html .= '<table>';
        $html .= "<tr>
          <th>Название</th>
          <th>Артикул</th>
          <th>Код/баркод</th>
          <th>В наличии</th>
          <th>Цена</th>
          <th>Старая цена</th>
          <th>Последнее обновление</th>
        </tr>";

        $currency = config('backpack.store.currency.symbol');

        foreach($sps as $sp) {
          $supplierName = $sp->supplier->name ?? '-';
          $supplierColor = $sp->supplier->color;
          $price = $sp->price !== null? $sp->price . $currency: '';
          $old_price = $sp->old_price !== null? $sp->old_price . $currency: '';

          $html .= '<tr>';
          $html .= "<td><b style='color: " . $supplierColor . "'>{$supplierName}</b></td>";
          $html .= "<td>{$sp->code}</td>";
          $html .= "<td>{$sp->barcode}</td>";
          $html .= "<td>{$sp->in_stock}</td>";
          $html .= "<td>{$price}</td>";
          $html .= "<td>{$old_price}</td>";
          $html .= "<td>{$sp->updated_at}</td>";

          $html .= '</tr>';
        }

        $html .= '</table>';
      }else {
        $html = 'Информация по поставщикам отсутсвует';
      }

      return $html;
    }

    /**
     * setAttributesFields
     * 
     * Set Attributes create/update fields
     *
     * @return void
     */
    public function setAttributesFields() {
      
      // $this->entry - current product data from DB
      // $this->attrs - collection of all attributes for attached categories
      if(isset($this->attrs) && $this->entry) {

        // Adding hidden field
        $this->crud->addField([
          'name' => 'props',
          'type' => 'hidden_fake_array',
          'value' => null,
        ]);

        $attr_fields = [];

        //
        foreach($this->attrs as $index => $attribute) {
          // Attribute Model ID
          $id = $attribute->id;

          // Attribute Model values list
          // $available_values = $attribute->values->mapWithKeys(function ($item, $key) {
          //   return [$item['id'] => $item['value']];
          // });

          // Attribute settings
          $settings = $attribute->extras;
          // dd($settings['min'] ?? '1');

          // If entry has attached attributes
          // Try find current value for this attribute 
          if($this->entry->ap) {
            // Find this attribute from already attached attributes
            $model_attribute = $this->entry->ap()->where('attribute_id', $attribute->id)->get();
          }else {
            $model_attribute = null;
          }
          
          // Create base attribute field template
          $si = $attribute->getExtrasTrans('si');

          $base_hint = '';
          $base_hint .= $attribute->in_properties? '<b>В характеристиках</b>': '';
          $base_hint .= $base_hint && mb_strlen($base_hint) > 0 && $attribute->in_filters? ' и ': '';
          $base_hint .= $attribute->in_filters? '<b>В фильтрах</b>': '';
          
          $attr_fields[$index] = [
            'name' => "props[{$id}]",
            'label' => $attribute->name . ($si? ' (' . $si . ')': ''),
            'tab' => 'Характеристики',
            'hint' => $base_hint
          ];

          // Set correct options for different attribute types
          // For checkbox
          if($attribute->type === 'checkbox')
          {
            // IMPORTANT !!!!! CHANGE THIS
            // If exists get pivot value 
            $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
            // dd($value);
            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                // 'name' => 'avsFake',
                'type'    => 'relationship_custom',
                'model2'     => 'Backpack\Store\app\Models\AttributeValue',
                'attribute' => 'value',
                'value' => $value,
                'ajax' => true,
                'multiple' => true,
                // 'entity' => Backpack\Store\app\Models\AttributeValue::class,
                // 'entity' => 'av',
                'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                'placeholder' => "Поиск по названию параметра",
                'minimum_input_length' => 0,
                'inline_create' => [
                  'entity' => 'value',
                  'force_select' => true,
                ]
              ],
              // [
              //   'type'    => 'select2_from_ajax_multiple',
              //   'model'     => 'Backpack\Store\app\Models\AttributeValue',
              //   'attribute' => 'value',
              //   'value' => $value ?? null,
              //   'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
              //   'placeholder' => "Поиск по названию параметра",
              //   'minimum_input_length' => 0
              // ]
            );
          }
          // For radio
          else if($attribute->type === 'radio')
          {
            // IMPORTANT !!!!! CHANGE THIS
            // $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
            // dd($model_attribute);
            $value = $model_attribute->first();
            // dd($value->attribute_value_id);

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type'    => 'select2_from_ajax',
                'model'     => 'Backpack\Store\app\Models\AttributeValue',
                'attribute' => 'value',
                'value' => $value->attribute_value_id ?? null,
                'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                'placeholder' => "Поиск по названию параметра",
                'minimum_input_length' => 0
              ]
            );
          }
          // For number
          else if($attribute->type === 'number')
          {
            // IMPORTANT !!!!! CHANGE THIS
            $value = $model_attribute->first()->value ?? null;

            $options = [];
            $options['min'] = $settings['min'] ?? 0;
            $options['max'] = $settings['max'] ?? 999999999999;
            $options['step'] = $settings['step'] ?? 0.1;         

            $hint = $attr_fields[$index]['hint'] . ', ';
            $hint .= "мин значение: {$options['min']}, макс значение: {$options['max']}, шаг: {$options['step']}";

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'number',
                'attributes' => [
                  'min' => $options['min'],
                  'max' => $options['max'],
                  'step' => $options['step'],
                ],
                'value' => $value,
                'hint' => $hint
              ]
            );
          }
          // For string
          else if($attribute->type === 'string')
          {
            $value = $model_attribute->first()->value_trans ?? null;

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'text',
                'value' => $value,
              ]
            );
          }
        }
        

        // Set all prepared fields
        foreach($attr_fields as $attr_field) {
          $this->crud->addField($attr_field);
        }
      }
      else {
        $this->crud->addField([
          'name'  => 'no_attributes',
          'type'  => 'custom_html',
          'value' => "
          <p>Для редактирования характеристик сперва убедитесь, что:</p>
          <ul>
            <li>Выбрана категория записи</li>
            <li>Выбранной категории соответсвует хотябы один атрибут</li>
            <li>Данные были сохранены хотябы один раз</li>
          </ul>",
          'tab' => 'Характеристики'
        ]);
      }
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

    //   if($query_category_id)
    //   {
    //     $this->category = Category::find($query_category_id);
    //   }
    //   else if($this->opr === 'create') 
    //   {
    //     if(isset($this->parent_entry) && !empty($this->parent_entry)){
    //       $this->category = $this->parent_entry->category;
    //     } else {
    //       $this->category = null;
    //     }
    //   }
    //   else if($this->opr === 'update') 
    //   {
    //     $this->category = $this->entry->category;
    //   }
    //   else 
    //   {
    //     $this->category = null;
    //   }

    //   if(!$this->category)
    //   {
    //     $this->category = Category::first();
    //   }
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
     * getProducts
     *
     * @param  mixed $request
     * @return void
     */
    public function getProducts(Request $request) {
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

    public function toggle($id)
    {
        $this->crud->hasAccessOrFail('update'); // Проверяем доступ

        $entry = $this->crud->model->findOrFail($id);
        $entry->is_active = request()->input('is_active', 0);
        $entry->save();

        return response()->json(['success' => true]);
    }
}
