<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\CategoryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

/**
 * Class CategoryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class CategoryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;
    
    use \App\Http\Controllers\Admin\Traits\CategoryCrud;

    private $category_class = null;
    private $filter_categories = [];

    public function setup()
    {
      $this->category_class = config('backpack.store.category.class', 'Backpack\Store\app\Models\Category');

      $this->crud->setModel($this->category_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/category');
      $this->crud->setEntityNameStrings('категорию', 'категории');

      $this->filter_categories = $this->category_class::withoutGlobalScopes()
            ->whereNull('parent_id')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function setupReorderOperation()
    {
      // define which model attribute will be shown on draggable elements 
      $this->crud->set('reorder.label', 'name');
      // define how deep the admin is allowed to nest the items
      // for infinite levels, set it to 0
      $this->crud->set('reorder.max_level', 2);
    }
    
    protected function setupListOperation()
    {

      // Filter by category
      $this->crud->addFilter([
        'name' => 'category',
        'label' => 'Родительская категория',
        'type' => 'select2',
      ], function(){
        return $this->filter_categories;
      }, function($id){
        $this->crud->query->where('parent_id', $id);
      });

      $this->crud->addFilter([
        'name' => 'is_active',
        'label' => 'Активная',
        'type' => 'select2',
      ], function(){
        return [
          0 => 'Не активная',
          1 => 'Активная',
        ];
      }, function($is_active){
        $this->crud->query = $this->crud->query->where('is_active', $is_active);
      });

      $this->crud->addFilter([
        'name' => 'is_products',
        'label' => 'С товарами',
        'type' => 'select2',
      ], function(){
        return [
          0 => 'Без товаров',
          1 => 'С товарами',
        ];
      }, function($is_products){
        if($is_products) {
          $this->crud->query->has('products', '>=', 1);
        }else {
          $this->crud->query->has('products', '=', 0);
        }
      });

      $this->crud->addFilter([
        'name' => 'is_seo',
        'label' => 'Заполнено SEO',
        'type' => 'select2',
      ], function(){
        return [
          0 => 'Не заполнено SEO',
          // 1 => 'Частично заполнено',
          2 => 'Заполнено SEO',
        ];
      }, function($is_seo){
        $locale = \Lang::locale();

        if($is_seo == 0) {
          $this->crud->query
            ->where('seo', null)
            ->orWhere(function ($query) use ($locale) {
              $query
                ->where("seo->{$locale}->meta_title", '=', null)
                ->where("seo->{$locale}->meta_description", '!=', null)
                ->where("seo->{$locale}->h1", '=', null);
            });
        }elseif($is_seo == 1){
          // $this->crud->query->where("seo->{$locale}->meta_title", '!=', null);
              // ->where(function ($query) use ($locale) {
              //   $query->where("seo->{$locale}->meta_title", '!=', null, 'xor');
              //   $query->where("seo->{$locale}->meta_description", '!=', null, 'xor');
              //   $query->where("seo->{$locale}->h1", '!=', null, 'xor');
              // });

            // $this->crud->query
            //     ->whereJsonContains("seo->{$locale}->meta_title", null)
            //     ->whereJsonContains("seo->{$locale}->meta_description", null)
            //     ->whereJsonContains("seo->{$locale}->h1", null);
                // ->where("seo->{$locale}->meta_title", '=', null, 'xor')
                // ->where("seo->{$locale}->meta_description", '=', null, 'xor')
                // ->where("seo->{$locale}->h1", '=', null, 'xor');
        }elseif($is_seo == 2){
          $this->crud->query->where("seo->{$locale}->meta_title", '!=', null);
          $this->crud->query->orWhere("seo->{$locale}->meta_description", '!=', null);
          $this->crud->query->orWhere("seo->{$locale}->h1", '!=', null);
        }
      });

      // TODO: remove setFromDb() and manually define Columns, maybe Filters
      // $this->crud->setFromDb(); 
      $this->crud->addColumn([
        'name' => 'imageSrc',
        'label' => '📷',
        'type' => 'image',
        'height' => '50px',
        'width'  => '50px',
      ]);
      
      // $this->crud->addColumn([
      //   'name' => 'id',
      //   'label' => 'ID',
      // ]);

      // IS ACTIVE
      $this->crud->addColumn([
        'name' => 'is_active',
        'label' => '✅',
        'type' => 'check'
      ]);

      $this->crud->addColumn([
        'name' => 'products',
        'label' => '📦',
        'type' => 'relationship_count',
        'suffix' => ' тов.'
      ]);

      $this->crud->addColumn([
        'name' => 'is_seo',
        'label' => 'SEO',
        'type' => 'model_function',
        'function_name' => 'getAdminColumnSeo',
        'limit' => 1000,
      ]);

      $this->crud->addColumn([
        'name' => 'name',
        'label' => 'Название',
        'limit' => 200,
      ]);
      
      $this->crud->addColumn([
        'name' => 'parent',
        'label' => 'Род. категория',
      ]);
      
      $this->crud->addColumn([
        'name' => 'depth',
        'label' => 'Уровень',
      ]);


      $this->listOperation();
    }

    protected function setupCreateOperation()
    {
      $this->crud->setValidation(CategoryRequest::class);
      
      $this->crud->addFields([
        [
          'name' => 'is_active',
          'label' => 'Активна',
          'type' => 'boolean',
          'default' => '1',
          'tab' => 'Основное'
        ],
        [
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
          'tab' => 'Основное'
        ],
        [
          'name' => 'slug',
          'label' => 'URL',
          'hint' => 'По умолчанию будет сгенерирован из названия.',
          'tab' => 'Основное'
        ],
        [
          'name' => 'parent',
          'label' => 'Родительская категория',
          'type' => 'relationship',
          'tab' => 'Основное'
        ],
        [
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'tab' => 'Основное'
        ],
        [
          'name'  => 'images',
          'label' => 'Изображения',
          'type'  => 'repeatable',
          'fields' => [
            [
              'name' => 'src',
              'label' => 'Изображение',
              'type' => 'browse'
            ],
            [
              'name' => 'alt',
              'label' => 'alt'
            ],
            [
              'name' => 'title',
              'label' => 'title'
            ]
          ],
          'new_item_label'  => 'Добавить изобрежение',
          'init_rows' => 1,
          'tab' => 'Изображения'
        ],
        [
          'name' => 'h1',
          'label' => 'H1 заголовок',
          'fake' => true,
          'store_in' => 'seo',
          'tab' => 'SEO'
        ],
        [
          'name' => 'meta_title',
          'label' => 'Meta title',
          'fake' => true,
          'store_in' => 'seo',
          'tab' => 'SEO'
        ],
        [
          'name' => 'meta_description',
          'label' => 'Meta description',
          'type' => 'textarea',
          'fake' => true,
          'store_in' => 'seo',
          'tab' => 'SEO'
        ],
        [
          'name' => 'params',
          'label' => 'Параметры',
          'type' => 'table',
          'columns'  => [
            'key'  => 'Ключ',
            'value'  => 'Значение',
          ],
          'fake' => true,
          'store_in' => 'extras',
          'tab' => 'Дополнительно'
        ],
      ]);

      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
    }
}
