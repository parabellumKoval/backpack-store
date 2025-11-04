<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\CategoryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;
use Backpack\LangFileManager\app\Models\Language;
use Backpack\Tag\app\Traits\TagFields;

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
    use \Backpack\Helpers\app\Http\Controllers\Operations\ReorderDeepOperation;
    
    use HasImagesCrudComponents;
    
    use \App\Http\Controllers\Admin\Traits\CategoryCrud;
    use TagFields;

    private $category_class = null;
    private $filter_categories = [];

    private $available_languages = null;
    private $langs_list = null;
    
    public function setup()
    {
        //
        $this->category_class = \Settings::get('dress.category.model_admin', 'Backpack\Store\app\Models\Category');

        $this->crud->setModel($this->category_class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/category');
        $this->crud->setEntityNameStrings(
            trans('backpack-store::category.entity_singular'),
            trans('backpack-store::category.entity_plural')
        );

        $this->filter_categories = $this->category_class::withoutGlobalScopes()
            ->whereNull('parent_id')
            ->pluck('name', 'id')
            ->toArray();
      
        $this->available_languages = config('backpack.crud.locales');
        $this->langs_list = array_keys($this->available_languages);
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
        $langs_list = $this->langs_list;

        // Filter by category
        $this->crud->addFilter([
            'name' => 'category',
            'label' => trans('backpack-store::category.filters.parent_category'),
            'type' => 'select2',
        ], function() {
            return $this->filter_categories;
        }, function($id) {
            $this->crud->query->where('parent_id', $id);
        });

        $this->crud->addFilter([
            'name' => 'is_active',
            'label' => trans('backpack-store::category.filters.active.label'),
            'type' => 'select2',
        ], function() {
            return [
                0 => trans('backpack-store::category.filters.active.options.0'),
                1 => trans('backpack-store::category.filters.active.options.1'),
            ];
        }, function($is_active) {
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
            }elseif($is_seo == 2){
                $this->crud->query->where("seo->{$locale}->meta_title", '!=', null);
                $this->crud->query->orWhere("seo->{$locale}->meta_description", '!=', null);
                $this->crud->query->orWhere("seo->{$locale}->h1", '!=', null);
            }
        });

        $this->setupFilers();

        $this->addImagesColumn([
            'label' => '📷',
        ]);

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
            'searchLogic' => function ($query, $column, $searchTerm) use($langs_list) {
                $query->where(function($query) use ($searchTerm, $langs_list){
                    foreach($langs_list as $index => $lang_key) {
                        $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                        $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
                    }
                });
            },
        ]);
      
        $this->crud->addColumn([
            'name' => 'parent',
            'label' => 'Род. категория',
        ]);
      
        $this->crud->addColumn([
            'name' => 'depth',
            'label' => 'Уровень',
        ]);

        $this->crud->addColumn([
            'name' => 'admin_countries_label',
            'label' => 'Страны',
            'type' => 'model_function',
            'function_name' => 'getAdminCountriesLabel',
            'limit' => 255,
        ]);

        $this->setupTagColumns();

        $this->listOperation();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(CategoryRequest::class);
      
        $this->crud->addFields([
            [
                'name' => 'is_active',
                'label' => trans('backpack-store::category.fields.is_active'),
                'type' => 'boolean',
                'default' => '1',
                'tab' => trans('backpack-store::category.tabs.main')
            ],
            [
                'name' => 'name',
                'label' => trans('backpack-store::category.fields.name'),
                'type' => 'text',
                'tab' => trans('backpack-store::category.tabs.main')
            ],
            [
                'name' => 'slug',
                'label' => trans('backpack-store::category.fields.slug'),
                'hint' => trans('backpack-store::category.fields.slug_hint'),
                'tab' => trans('backpack-store::category.tabs.main')
            ],
            [
                'name' => 'parent',
                'label' => 'Родительская категория',
                'type' => 'relationship',
                'tab' => trans('backpack-store::category.tabs.main')
            ],
            [
                'name' => 'countries',
                'label' => 'Страны',
                'type' => 'select2_from_array',
                'allows_multiple' => true,
                'options' => $this->countryOptions(),
                'allows_null' => true,
                'hint' => 'Пусто — категория доступна во всех странах',
                'tab' => trans('backpack-store::category.tabs.main')
            ],
            [
                'name' => 'content',
                'label' => 'Описание',
                'type' => 'ckeditor',
                'tab' => trans('backpack-store::category.tabs.main')
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

        $this->addImagesField([
            'label' => 'Изображения',
            'tab' => 'Изображения',
            'new_item_label' => 'Добавить изображение',
        ]);

        $this->setupTagFields();
        $this->crud->modifyField('tags', [
            'tab' => 'Дополнительно'
        ]);



         // MERCHNTS CATEGORY
        $this->crud->addField([
            'name' => 'merchant_id',
            'label' => 'Категория Google Merchants',
            'type' => 'select2',
            'entity' => 'merchant',
            'attribute' => 'keyName',
            'model' => 'Backpack\Store\app\Models\MerchantCategory',
            'tab' => 'Google Merchants',
            'hint' => 'Выберите из списка категорию Google Merchants, которой соответствует данная.',
        ]);

        $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function countryOptions(): array
    {
        return \Store::countryOptions();
    }
    
    /**
     * getCategories
     *
     * @param  mixed $request
     * @return void
     */
    public function getCategories(Request $request) {
        $search_term = $request->input('q');
        $ids = $request->input('keys');

        // langs
        $langs_list = $this->langs_list;

        if($ids) {
            $search_key_array = is_numeric($ids)? [$ids]: json_decode($ids, true);
            $categories = $this->category_class::whereIn('id', $search_key_array)->get();

            return $categories;
        }

        if ($search_term)
        {
            $results = $this->category_class::
                                where(function($query) use ($search_term, $langs_list){
                                    foreach($langs_list as $index => $lang_key) {
                                        $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                                        $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($search_term)).'%']);
                                    }
                                })
                                ->paginate(20);
        }
        else
        {
            $results = $this->category_class::paginate(20);
        }

        return $results;
    }

}
