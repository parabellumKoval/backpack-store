<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\CategoryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;
use Backpack\LangFileManager\app\Models\Language;
use Backpack\Tag\app\Traits\TagFields;
use Backpack\Helpers\Traits\Admin\HasSeoFilters;

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
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\Helpers\app\Http\Controllers\Operations\ReorderDeepOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ServiceOperation;
    
    use HasImagesCrudComponents;
    use HasSeoFilters;
    use \Backpack\Helpers\Traits\Admin\HasToggleColumns;
    
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
        $this->crud->set('reorder.label', 'name');
        $this->crud->set('reorder.max_level', 3);
    }
    
    protected function setupListOperation()
    {
        $langs_list = $this->langs_list;

        $depthOptions = [];
        $depthValues = $this->category_class::withoutGlobalScopes()
            ->select('depth')
            ->whereNotNull('depth')
            ->distinct()
            ->orderBy('depth')
            ->pluck('depth');
        foreach ($depthValues as $depth) {
            $depthOptions[$depth] = (string) $depth;
        }

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

        if (!empty($depthOptions)) {
            $this->crud->addFilter([
                'name' => 'depth',
                'label' => 'Уровень',
                'type' => 'select2',
            ], $depthOptions, function ($depth) {
                $this->crud->query->where('depth', $depth);
            });
        }

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

        $this->addSeoFilledFilter([
            'name' => 'is_seo',
            'label' => trans('backpack-store::category.filters.seo.label'),
            'field' => 'seo',
            'properties' => ['meta_title', 'meta_description', 'h1'],
            'options' => trans('backpack-store::category.filters.seo.options'),
            'empty_value' => 0,
            'filled_value' => 2,
        ]);

        $this->setupFilers();

        $this->addImagesColumn([
            'label' => '📷',
        ]);

        $this->addToggleColumn([
            'name' => 'is_active',
            'label' => '✅',
        ]);

        $this->crud->addColumn([
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text_progress',
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
            'name' => 'seo',
            'label' => 'SEO',
            'type' => 'seo_status_linear',
            'seo_field' => 'seo',
            'properties' => [
                'h1' => trans('backpack-store::category.fields.h1'),
                'meta_title' => trans('backpack-store::category.fields.meta_title'),
                'meta_description' => trans('backpack-store::category.fields.meta_description'),
            ],
            'empty_text' => 'Не заполнено',
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
            'name' => 'products',
            'label' => '📦',
            'type' => 'relationship_count',
            'suffix' => ' тов.'
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
                'attribute' => 'uniqHtml',
                'ajax' => true,
                'data_source' => route('backpack.helpers.fetch', ['key' => 'category']),
                'minimum_input_length' => 2,
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
                'type' => 'countable_textarea',
                'fake' => true,
                'store_in' => 'seo',
                'rows' => 1,
                'resizable' => false,
                'recommended_length' => 60,
                'tab' => 'SEO'
            ],
            [
                'name' => 'meta_title',
                'label' => 'Meta title',
                'type' => 'countable_textarea',
                'fake' => true,
                'store_in' => 'seo',
                'rows' => 2,
                'resizable' => true,
                'recommended_length' => 70,
                'tab' => 'SEO'
            ],
            [
                'name' => 'meta_description',
                'label' => 'Meta description',
                'type' => 'countable_textarea',
                'fake' => true,
                'store_in' => 'seo',
                'rows' => 3,
                'resizable' => true,
                'recommended_length' => 160,
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
            'type' => 'relationship',
            'entity' => 'merchant',
            'attribute' => 'uniqHtml',
            'model' => 'Backpack\Store\app\Models\MerchantCategory',
            'ajax' => true,
            'data_source' => route('backpack.helpers.fetch', ['key' => 'merchant_category']),
            'minimum_input_length' => 2,
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
