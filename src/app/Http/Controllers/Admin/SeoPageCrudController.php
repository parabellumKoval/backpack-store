<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Http\Requests\Admin\SeoPageRequest;
use Backpack\Store\app\Models\SeoPage;
use Backpack\Store\app\Models\Attribute;

class SeoPageCrudController extends CrudController
{
    // use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; } 
    // use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;

    use \Backpack\Store\app\Http\Controllers\Admin\Traits\BaseCrudTrait;

    private $tabs = [
      'text' => 'Тексты',
      'place' => 'Расположение'
    ];

    public function setup(): void
    {
        CRUD::setModel(SeoPage::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/seo-page');
        CRUD::setEntityNameStrings('посадочная страница', 'посадочных страницы');

        // базовые операции
        // $this->crud->allowAccess(['list','create','update','delete','show']);

        $this->setEntry();
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn(['name'=>'id','label'=>'ID','type'=>'number']);
        CRUD::addColumn(['name'=>'is_active','label'=>'Активен','type'=>'boolean']);
        CRUD::addColumn([
            'name'=>'type','label'=>'Тип','type'=>'select_from_array',
            'options'=>['static' => 'Статический','dynamic' => 'Динамический']
        ]);
        CRUD::addColumn([
            'name' => 'category',
            'label' => 'Категория',
            'type' => 'relationship',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => 'Backpack\Store\app\Models\Category',
        ]);
        CRUD::addColumn(['name'=>'slug','label'=>'Slug','type'=>'text']);
    }

    protected function setupCreateOperation(): void
    {
      // dd($this->entry->priority_sources);
        CRUD::setValidation(SeoPageRequest::class);

        // Общие
        CRUD::addField([
            'name'=>'is_active','label'=>'Активен','type'=>'checkbox','default'=>1,
        ]);

        CRUD::addField([
            'name'=>'slug','label'=>'Slug','type'=>'text',
            'hint'=>'Уникальный url посадочной страницы',
        ]);

        CRUD::addField([
            'name' => 'category_id',
            'label' => 'Категория',
            'type' => 'select2',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => 'Backpack\Store\app\Models\Category',
            'attributes' => [
              'data-dep-source' => 'category'
            ],
            'wrapper'=>['class'=>'form-group col-md-4']
        ]);

        CRUD::addField([
            'name'=>'type','label'=>'Тип','type'=>'select2_from_array',
            'options'=>['static' => 'Статический','dynamic' => 'Динамический'], 'allows_null'=>false,
            'hint'=>'Статический - точное совпадение всех фильтров, Динамический - множественные совпадения', 'wrapper'=>['class'=>'form-group col-md-4']
        ]);

        CRUD::addField([
            'name'=>'countries','label'=>'Страны','type'=>'select2_from_array','allows_multiple'=>true,
            'options'=>$this->countryOptions(), 'allows_null'=>true,
            'hint'=>'Пусто = список активен для всех стран', 'wrapper'=>['class'=>'form-group col-md-4'],
        ]);

        CRUD::addField([
          'label' => 'Набор фильтров',
          'hint' => 'Укажите какому набору фильтров соответствует посадочная страница.',
          ...$this->addFilterFields()
        ]);


        // Тексты (переводимые)
        CRUD::addField([
            'name'=>'h1','label'=>'H1','type'=>'text','tab'=>$this->tabs['text']
        ]);
        CRUD::addField([
            'name'=>'meta_title','label'=>'Meta title','type'=>'text','tab'=>$this->tabs['text']
        ]);
        CRUD::addField([
            'name'=>'meta_description','label'=>'Meta description','type'=>'text','tab'=>$this->tabs['text']
        ]);
        CRUD::addField([
            'name'=>'top_html','label'=>'Верхний текст','type'=>'ckeditor','tab'=>$this->tabs['text']
        ]);
        CRUD::addField([
            'name'=>'bottom_html','label'=>'Нижний текст','type'=>'ckeditor','tab'=>$this->tabs['text']
        ]);

        // Расположение
        CRUD::addField([
            'name'=>'show_on_category','label'=>'Показать в категориях','type'=>'checkbox','default'=>1,'tab'=>$this->tabs['place']
        ]);

        CRUD::addField([
            'name'=>'show_on_product','label'=>'Показать на странице товара','type'=>'checkbox','default'=>1,'tab'=>$this->tabs['place']
        ]);

        CRUD::addField([
            'name'=>'show_in_sitemap','label'=>'Выводить в sitemap','type'=>'checkbox','default'=>1,'tab'=>$this->tabs['place']
        ]);
    }

    private function getCheckboxTypeIds() {
      $ids_array = Attribute::whereIn('type', ['checkbox'])->where('is_active', true)->pluck('id')->toArray();
      $ids_string = implode(',', $ids_array);
      return $ids_string;
    }

    private function getRadioTypeIds() {
      $ids_array = Attribute::whereIn('type', ['radio'])->where('is_active', true)->pluck('id')->toArray();
      $ids_string = implode(',', $ids_array);
      return $ids_string;
    }

    private function getNumberTypeIds() {
      $ids_array = Attribute::whereIn('type', ['number'])->where('is_active', true)->pluck('id')->toArray();
      $ids_string = implode(',', $ids_array);
      return $ids_string;
    }

    private function getStringTypeIds() {
      $ids_array = Attribute::whereIn('type', ['string'])->where('is_active', true)->pluck('id')->toArray();
      $ids_string = implode(',', $ids_array);
      return $ids_string;
    }

    private function getFilterBranchOptions(array $exclude = []) {
      $options = [
          // --- categories ---
          'categories' => [
            'fields' => [
              [
                'name'        => 'categories',
                'label'       => 'Категории',
                'type'        => 'select2_from_ajax_multiple',
                'data_source' => route('backpack.helpers.fetch', ['key' => 'category']),
                'attribute'   => 'uniqHtml',
                'model'       => \Backpack\Store\app\Models\Category::class,
                'minimum_input_length' => 2,
                'placeholder' => "Выберите категорию",
              ],
              [
                'name'  => 'include_children',
                'label' => 'Включать подкатегории',
                'type'  => 'checkbox',
                'default' => true,
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- brands ---
          'brands' => [
            'fields' => [
              [
                'name'        => 'brands',
                'label'       => 'Бренды',
                'type'        => 'select2_from_ajax_multiple',
                'data_source' => route('backpack.helpers.fetch', ['key' => 'brand']),
                'attribute'   => 'uniqHtml',
                'model'       => \Backpack\Store\app\Models\Brand::class,
                'allows_multiple' => true,
                'minimum_input_length' => 2,
                'placeholder' => "Выберите бренд",
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- tags ---
          'tags' => [
            'fields' => [
              [
                'name'        => 'tags',
                'label'       => 'Теги',
                'type'        => 'select2_from_ajax_multiple',
                'data_source' => route('backpack.helpers.fetch', ['key' => 'tag']),
                'attribute'   => 'uniqHtml',
                'model'       => \Backpack\Tag\app\Models\Tag::class,
                'placeholder' => "Выберите тег",
                'allows_multiple' => true,
                'minimum_input_length' => 2,
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- attributes ---
          'attributes' => [
            'fields' => [
              [
                'name'    => 'logic',
                'label'   => 'Оператор',
                'type'    => 'select_from_array',
                'options' => ['AND'=>'AND','OR'=>'OR'],
                'default' => 'AND',
                'allows_null' => false,
                'hint' => 'Логика объединения атрибутов.'
              ],
              [
                'name'   => 'rules',
                'label'  => 'Атрибуты',
                'type'   => 'repeatable_conditional',
                'min_rows' => 1,
                'init_rows'=> 1,
                'fields' => $this->getAttributeFields(),
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- price_range ---
          'price_range' => [
            'fields' => [
              [
                'name'  => 'price_min',
                'label' => 'Мин. цена',
                'type'  => 'number',
                'attributes' => ['min'=>0,'step'=>'0.01'],
              ],
              [
                'name'  => 'price_max',
                'label' => 'Макс. цена',
                'type'  => 'number',
                'attributes' => ['min'=>0,'step'=>'0.01'],
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- stock ---
          'stock' => [
            'fields' => [
              [
                'name'  => 'only_in_stock',
                'label' => 'Только в наличии',
                'type'  => 'checkbox',
                'default' => true,
              ],
              [
                'name'  => 'stock_min',
                'label' => 'Мин. остаток',
                'type'  => 'number',
                'attributes' => ['min'=>0],
              ],
              $this->getFilterDirectionFields()
            ],
          ],
          
          // --- on_sale ---
          'sale' => [
            'fields' => [
              [
                'name'  => 'min_discount_percent',
                'label' => 'Мин. скидка %',
                'type'  => 'number',
                'attributes' => ['min'=>0,'max'=>100,'step'=>'1'],
                'default' => 0,
              ],
              [
                'name'  => 'require_old_price',
                'label' => 'Только товары со скидкой',
                'type'  => 'checkbox',
                'default' => true,
              ],
              $this->getFilterDirectionFields()
            ],
          ],

          // --- include_products ---
          'products' => [
            'fields' => [
              [
                'name'        => 'include_product_ids',
                'label'       => 'Выберите товары',
                'type'        => 'select2_from_ajax_multiple',
                'data_source' => route('backpack.helpers.fetch', ['key' => 'product']),
                'attribute'   => 'uniqHtml',
                'model'       => \Backpack\Store\app\Models\Product::class,
                'allows_multiple' => true,
                'minimum_input_length' => 2,
                'placeholder' => "Выберите товар",
              ],
              $this->getFilterDirectionFields()
            ],
          ],
          
        ];

      if(!empty($exclude))
        $options = array_filter($options, fn($key) => !in_array($key, $exclude), ARRAY_FILTER_USE_KEY);

      return $options;
    }

    private function getAttributeFields() {
      return  [
        [
          'name'   => 'rule',
          'type'   => 'conditional_fields',
          'label'  => 'Правило',
          // 'driver' => [
          //   'name'        => 'attribute',
          //   'label'       => 'Атрибут',
          //   'type'        => 'select2_from_ajax',
          //   'data_source' => backpack_url('/api/attribute'),
          //   'attribute'   => 'name',
          //   'model'       => \Backpack\Store\app\Models\Attribute::class,
          //   'placeholder' => "Выберите атрибут",
          //   'minimum_input_length' => 2,
          // ],
          'driver' => [
            'name'  => 'attribute',
            'label' => 'Атрибут',
            'type'  => 'select_from_array',
            'options' => [],
            'attributes' => [
              'data-depends-on'    => 'category_id',
              'data-dep-scope'     => 'global',
              'data-dep-url'       => backpack_url('/api/attribute/{value}'),
              'data-allow-null'    => '1',
              'data-placeholder'   => 'Выберите атрибут',
            ],
            'wrapper' => [
              'data-init-function' => 'bpFieldInitDependentOptions',
            ]
            // 'placeholder' => "Выберите атрибут",
            // 'minimum_input_length' => 2,
          ],
          'branches' => [
            // '[64,67]'
            '[' . $this->getCheckboxTypeIds() . ']' => [
              'fields' => [
                [
                  'name'        => 'values',
                  'label'       => 'Значения',
                  'type'        => 'select_from_array',
                  'allows_multiple' => true,
                  'options' => [],
                  'attributes' => [
                    'data-init-function' => 'bpFieldInitDependentOptions',
                    'data-depends-on'    => 'attribute',
                    'data-dep-scope'     => '.repeatable-element',
                    'data-dep-url'       => backpack_url('/api/attribute_values/{value}'),
                    'data-label-prop'    => 'value',
                    'data-allow-null'    => '1',
                    'data-placeholder'   => 'Выберите значение',
                  ],
                ],
                [
                  'name'    => 'operator',
                  'label'   => 'Оператор',
                  'type'    => 'select_from_array',
                  'options' => ['AND'=>'ADN','OR'=>'OR'],
                  'default' => 'OR',
                  'allows_null' => false,
                  'hint' => 'Логика объединения значений.'
                ],
              ]
            ],
            '[' . $this->getRadioTypeIds() . ']' => [
              'fields' => [
                [
                  'name'        => 'values',
                  'label'       => 'Значения',
                  'type'        => 'select_from_array',
                  'options' => [],
                  'attributes' => [
                    'data-init-function' => 'bpFieldInitDependentOptions',
                    'data-depends-on'    => 'attribute',
                    'data-dep-scope'     => '.repeatable-element',
                    'data-dep-url'       => backpack_url('/api/attribute_values/{value}'),
                    'data-label-prop'    => 'value',
                    'data-allow-null'    => '1',
                    'data-placeholder'   => 'Выберите значение',
                  ],
                ],

              ]
            ],
            '[' . $this->getNumberTypeIds() . ']' => [
              'fields' => [
                [
                  'name'  => 'value',
                  'label' => 'Значения',
                  'type'  => 'text',
                  'hint' => "<ul>
                  <li>Для точного совпадения: <code>100</code>.</li>
                  <li>Для диапазона значений: <code>100-300</code>.</li>
                  <li>Логические операторы: <code>>=, <=, >, <, !=</code></li>
                  <li>Для совпадения одного любого логического значения из набора | (или): <code>0-100|200|>=300</code>.</li> 
                  <li>Объединить несколько логических значений & (и): <code>0-200&!=100</code></li>
                  </ul>"
                ],
              ]
            ],
            '[' . $this->getStringTypeIds() . ']' => [
              'fields' => [
                [
                  'name'  => 'value',
                  'label' => 'Значения',
                  'type'  => 'text'
                ],
              ]
            ]
          ],
        ],
        // [
        //   'name'  => 'values',
        //   'label' => 'Значения',
        //   'type'  => 'textarea',
        //   'hint'  => 'Для IN — список значений через разделитель | (вертикальную черту). Для EQ/GTE/LTE — одно значение',
        // ],
      ];
    }

    private function addFilterFields(array $exclude = []) {
      
      return [
        'name'  => 'filters',
        'type'  => 'repeatable_conditional',
        'min_rows' => 0,
        'init_rows'=> 0,
        'new_item_label' => 'Добавить правило',
        'fields' => [
          [
            'name'   => 'rule',
            'type'   => 'conditional_fields',
            'label'  => 'Правило',
            'driver' => [
              'name'    => 'key',
              'label'   => 'Тип правила',
              'type'    => 'select_from_array',
              'options' => $this->getFilterOptions($exclude),
              'allows_null' => false,
            ],

            'branches' => $this->getFilterBranchOptions($exclude),
          ],


        ],
      ];
    }

    private function getFilterDirectionFields() {
      $field = [
        'name'  => 'direction',
        'label' => 'Направление фильтра',
        'type'  => 'select_from_array',
        'options' => ['include'=>'Включить','exclude'=>'Исключить'],
        'hint' => 'Включить - будут выбраны только товары соответствующие фильтру. Исключить - будут выбрать все товары, кроме соответствующих фильтру.',
        'allows_null' => false,
        'default' => 'include',
      ];

      return $field;
    }


    private function getFilterOptions(array $exclude = []) {
      $filter_options = [
        'categories'        => 'Категории',
        'brands'            => 'Бренды',
        'tags'              => 'Теги',
        'attributes'        => 'Атрибуты',
        'price_range'       => 'Диапазон цены',
        'stock'             => 'Склад',
        'sale'              => 'Скидка',
        'products'          => 'Товары',
      ];

      if(!empty($exclude))
        $filter_options = array_filter($filter_options, fn($key) => !in_array($key, $exclude), ARRAY_FILTER_USE_KEY);

      return $filter_options;
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function countryOptions(): array
    {
        return \Store::countryOptions();
    }


    public function store()
    {
        return $this->traitStore();
    }

    public function update()
    {
        return $this->traitUpdate();
    }

    
}
