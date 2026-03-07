<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Http\Requests\Admin\ProductListRequest;
use Backpack\Store\app\Models\ProductList;

class ProductListCrudController extends CrudController
{
    // use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; } 
    // use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    use \Backpack\Helpers\app\Http\Controllers\Operations\ReorderDeepOperation;

    use \Backpack\Store\app\Http\Controllers\Admin\Traits\BaseCrudTrait;

    private $hints = [
      'per_anchor_limit' => 'Сколько будет подобрано карточек для каждого из переданных якорей(id). Пустое поле или 0 = без ограничений.',
      'filters' => 'C помошью фыильтрации можно дополнительно исключить некоторые карточки товаров из источника.',
      'group' => 'Если объединить два источника в одну группу (указать одно число) они будут срабатывать одновременно.'
    ];

    public $reorder_filterable = [
      'page' => ['type' => 'string', 'column' => 'page']
    ];

    public function setup(): void
    {
        CRUD::setModel(ProductList::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/product-list');
        CRUD::setEntityNameStrings('товарный список', 'товарные списки');

        // базовые операции
        // $this->crud->allowAccess(['list','create','update','delete','show']);

        $this->setEntry();
    }

    protected function setupReorderOperation()
    {
        // define which model attribute will be shown on draggable elements 
        $this->crud->set('reorder.label', 'name');
        // define how deep the admin is allowed to nest the items
        // for infinite levels, set it to 0
        $this->crud->set('reorder.max_level', 1);
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn(['name'=>'id','label'=>'ID','type'=>'number']);
        CRUD::addColumn(['name'=>'name','label'=>'Название']);
        CRUD::addColumn(['name'=>'page','label'=>'Страница','type'=>'select_from_array', 'options' => $this->pageOptions()]);
        CRUD::addColumn(['name'=>'slug','label'=>'Slug','type'=>'text']);
        CRUD::addColumn(['name'=>'capacity','label'=>'Ёмкость','type'=>'number']);
        CRUD::addColumn([
            'name'=>'kind','label'=>'Тип','type'=>'select_from_array',
            'options'=>['up'=>'Up-sell','cross'=>'Cross-sell'], 'allows_null'=>true
        ]);
        CRUD::addColumn(['name'=>'is_active','label'=>'Активен','type'=>'boolean']);

        // кнопка «Управлять ручными товарами»
        // $this->crud->addButtonFromView('line', 'manage_list_items', 'manage_list_items', 'beginning');
        $this->crud->addButtonFromView('line', 'reorder_by_page', 'reorder_by_page', 'beginning');
        $this->crud->removeButton('reorder');
    }

    protected function setupCreateOperation(): void
    {
      // dd($this->entry->priority_sources);
        CRUD::setValidation(ProductListRequest::class);

        // Общие
        CRUD::addField([
            'name'=>'is_active','label'=>'Активен','type'=>'checkbox','default'=>1,
        ]);

        CRUD::addField([
            'name'=>'name','label'=>'Название','type'=>'text'
        ]);

        CRUD::addField([
            'name'=>'slug','label'=>'Slug','type'=>'text',
            'hint'=>'Уникален в рамках страницы',
        ]);

        CRUD::addField([
            'name'=>'page','label'=>'Страница','type'=>'select_from_array',
            'options'=>$this->pageOptions(), 'allows_null'=>false,
            'hint'=>'Страница на которой будет выводиться список',
        ]);

        CRUD::addField([
            'name'=>'capacity','label'=>'Кол-во карточек','type'=>'number','default'=>12,
            'attributes'=>['min'=>1,'max'=>200], 'wrapper'=>['class'=>'form-group col-md-4'],
            'hint'=>'Общее кол-во карточек товара которые будут выведены в списке',
        ]);

        // CRUD::addField([
        //     'name'=>'kind','label'=>'Семантика','type'=>'select_from_array',
        //     'options'=>['up'=>'Up-sell','cross'=>'Cross-sell'],'allows_null'=>true,
        //     'wrapper'=>['class'=>'form-group col-md-4'],
        //     'hint'=>'Тип списка',
        // ]);

        CRUD::addField([
            'name'=>'countries','label'=>'Страны','type'=>'select2_from_array','allows_multiple'=>true,
            'options'=>$this->countryOptions(), 'allows_null'=>true,
            'hint'=>'Пусто = список активен для всех стран', 'wrapper'=>['class'=>'form-group col-md-4'],
        ]);

        CRUD::addField([
            'name'=>'page',
            'label'=>'Страница',
            'type'=>'conditional_fields',
            'options'=>$this->pageOptions(),
            'driver' => [
              'name'=>'page',
              'label'=>'Страница',
              'type'=>'select_from_array',
              'options'=>$this->pageOptions(), 
              'allows_null'=>false,
            ],
        ]);

        $this->addSourceFields();

        // Поведение: Источники и сортировки
        $this->addSortingFields();

        // $this->addFilterFields();

        // Тексты (переводимые)
        CRUD::addField([
            'name'=>'title','label'=>'Заголовок списка','type'=>'text','tab'=>'Тексты'
        ]);

        CRUD::addField([
            'name'=>'button_text','label'=>'Текст кнопки','type'=>'text','tab'=>'Тексты', 'hint' => 'Текст кнопки, которая ведет на полную страницу "Смотреть все"'
        ]);
        CRUD::addField([
            'name'=>'full_url','label'=>'Ссылка кнопки «Смотреть всё»','type'=>'text','tab'=>'Тексты',
        ]);
    }

    private function getGroupField(){
      return [
        'name'  => 'group',
        'label' => 'Номер группы',
        'type'  => 'number',
        'hint'  => $this->hints['group'],
        'attributes' => ['min'=>1,'max'=>100],
        'wrapper'=>['class'=>'form-group col-md-6'],
      ];
    }

    private function getPerAnchorLimitField(){
      return [
        'name'  => 'per_anchor_limit',
        'label' => 'Лимит на якорь',
        'type'  => 'number',
        'hint'  => $this->hints['per_anchor_limit'],
        'attributes' => ['min'=>1,'max'=>100],
      ];
    }

    private function getMinSharedField() {
      return [
        'name'  => 'min_shared',
        'label' => 'Мин. совпадений',
        'type'  => 'number',
        'attributes' => ['min'=>1],
        'hint' => 'Укажите минимальное кол-во одинаковых значений. 0 = должны присутствовать все значения.',
        'default' => 1,
      ];
    }

    private function addSourceFields() {
        CRUD::addField([
          'name'  => 'sources',
          'label' => 'Источники (по приоритету)',
          'type'  => 'repeatable_conditional',
          'min_rows' => 0,
          'init_rows'=> 0,
          'new_item_label' => 'Добавить источник',
          'tab'=>'Заполнение',
          'reorder' => true,
          'fields' => [
            [
              'name'   => 'params',
              'label'  => 'Параметры',
              'type'   => 'conditional_fields',

              // driver — это соседнее поле alias в этой же row
              'driver' => [
                'name'        => 'alias',
                'label'       => 'Источник',
                'type'        => 'select_from_array',
                'options'     => $this->sourceOptionsAnchored(),
                'allows_null' => false,
              ],

              'branches' => [

                // ---------- manual_list_items (любой режим) ----------
                'manual_list_items' => [
                  'fields' => [
                    [
                      'name'   => 'items',
                      'label'  => 'Товары (ручной набор)',
                      'type'   => 'repeatable_conditional',
                      'min_rows' => 0,
                      'init_rows'=> 0,
                      'new_item_label' => 'Добавить товар',
                      'fields' => [
                        [
                          'name'        => 'product_id',
                          'label'       => 'Товар',
                          'type'        => 'select2_from_ajax',
                          'data_source' => route('backpack.helpers.fetch', ['key' => 'product']),
                          'attribute'   => 'uniqHtml',
                          'model'       => \Backpack\Store\app\Models\Product::class,
                          'placeholder' => 'Начните вводить название',
                          'minimum_input_length' => 2,
                        ],
                        [
                          'name'  => 'priority',
                          'label' => 'Приоритет',
                          'type'  => 'number',
                          'default' => 0,
                          'attributes' => ['min'=>0,'max'=>255],
                        ],
                      ],
                      // итоговый JSON: params.items[] = {product_id, priority}
                    ],
                  ],
                ],

                // ---------- links (только anchored) ----------
                'links' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Для различных типов записей могут быть указаны связанные товары. Например для товаров, статей и т.д. можно указать связанные товары (заполняется в админке в соответсвующих разделах).</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других записей). 2) В админке указаны связанные товары к этой записи (якорю).</li>
                      </ul>
                      "
                    ],
                    [
                      'name'    => 'kind',
                      'label'   => 'Тип связи',
                      'type'    => 'select_from_array',
                      'options' => ['any'=>'Любой','up'=>'Up-sell','cross'=>'Cross-sell'],
                      'default' => 'any',
                    ],
                    [
                      'name'  => 'min_priority',
                      'label' => 'Мин. приоритет',
                      'type'  => 'number',
                      'attributes' => ['min'=>0,'max'=>255],
                    ],
                    [
                      'name'  => 'include_reverse',
                      'label' => 'Учитывать обратные связи (B→A)',
                      'type'  => 'checkbox',
                      'default' => false,
                    ],
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                  // JSON: {kind, min_priority?, per_anchor_limit?, include_reverse?}
                ],

                // ---------- bought_together (только anchored) ----------
                'bought_together' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые покупали вмести с теми, что переданы в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров). 2) Существует связи в истории покупок.</li>
                      </ul>
                      "
                    ],
                    [
                      'name'  => 'fallback_global',
                      'label' => 'Мультирегиональный добор',
                      'hint'  => 'Дополнительно учитывать статистику из других регионов',
                      'type'  => 'checkbox',
                      'default' => true,
                    ],
                    [
                      'name'  => 'min_score',
                      'label' => 'Мин кол-во совместных покупок',
                      'type'  => 'number',
                      'attributes' => ['min'=>0],
                    ],
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(),
                      'hint' => $this->hints['filters']
                    ]
                  ]
                ],

                // ---------- tags (Dual) ----------
                'tags' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые имеют такие же теги как и товары переданные в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров)</li>
                      </ul>
                      "
                    ],
                    [
                      'name'  => 'match_mode',
                      'label' => 'Совпадение',
                      'type'  => 'select_from_array',
                      'options' => ['ANY'=>'Любой','ALL'=>'Все'],
                      'default' => 'ANY',
                    ],
                    $this->getMinSharedField(),
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(['tags']),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                  // JSON: {origin, tags[]?, match_mode, min_shared, per_anchor_limit?}
                ],

                // ---------- category (Dual) ----------
                'category' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые имеют такие же категории как и товары переданные в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров)</li>
                      </ul>
                      "
                    ],
                    [
                      'name'  => 'include_children',
                      'label' => 'Включать подкатегории',
                      'type'  => 'checkbox',
                      'default' => true,
                    ],
                    $this->getMinSharedField(),
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(['categories']),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                  // JSON: {origin, categories[]?, include_children, min_shared?, per_anchor_limit?}
                ],

                // ---------- brand (Dual) ----------
                'brand' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые имеют такой же бренд как и товары переданные в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров)</li>
                      </ul>
                      "
                    ],
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(['brand']),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                  // JSON: {origin, brands[]?, per_anchor_limit?}
                ],

                // ---------- attributes (Dual) ----------
                'attributes' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые имеют такие же атрибуты как и товары переданные в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров)</li>
                      </ul>
                      "
                    ],
                    $this->getMinSharedField(),
                    $this->getPerAnchorLimitField(),
                    [
                      ...$this->addFilterFields(['attributes']),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                  // JSON: {origin, logic, rules?[]}
                ],

                // ---------- price_band (Dual) ----------
                'price_band' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник выведет товары которые имеют цену соозмеримую (с учетом коофициентов) с ценой товаров, которые переданы в запросе.</li>
                      <li>Источник данных сработает только если: 1) В запросе будут переданы якоря (id других товаров)</li>
                      </ul>
                      "
                    ],
                    [
                      'name'  => 'low_factor',
                      'label' => 'Нижний коэффициент',
                      'type'  => 'number',
                      'attributes' => ['step'=>'0.01','min'=>0],
                    ],
                    [
                      'name'  => 'high_factor',
                      'label' => 'Верхний коэффициент',
                      'type'  => 'number',
                      'attributes' => ['step'=>'0.01','min'=>0],
                    ],
                    [
                      'name'  => 'anchor_reference',
                      'label' => 'База по анкорам',
                      'type'  => 'select_from_array',
                      'options' => ['max'=>'Максимум','median'=>'Медиана','avg'=>'Средняя'],
                      'default' => 'max',
                    ],
                    [
                      'name'  => 'only_more_expensive',
                      'label' => 'Только дороже (для Up)',
                      'type'  => 'checkbox',
                      'default' => false,
                    ],
                  ],
                ],

                'base' => [
                  'fields' => [
                    [
                      'name'    => 'desc',
                      'type'    => 'custom_html',
                      'value'   => "
                      <label>Описание источника</label>
                      <ul class='mb-0'>
                      <li>Источник данных - все товары на сайте</li>
                      <li>С помощью фильтров можно конкретизировать выборку товаров</li>
                      <li>Источник данных не зависит от якорей</li>
                      </ul>
                      "
                    ],
                    [
                      ...$this->addFilterFields(),
                      'hint' => $this->hints['filters']
                    ]
                  ],
                ],
              ],
            ],
            [
              'name'=>'capacity','label'=>'Макс. кол-во карточек на источник','type'=>'number','attributes'=>['min'=>1,'max'=>200],
              'wrapper'=>['class'=>'form-group col-md-6'],
              'hint'=>'Макс. кол-во карточек, которое может быть заполнено из данного источника. Пустое поле или 0 = без ограничений.',
            ],
            $this->getGroupField()
          ],
        ]);
    }

    private function addSortingFields() {
        CRUD::addField([
            'name'=>'sort_order','label'=>'Сортировки (по приоритету)','type'=>'repeatable_conditional','tab'=>'Поведение',
            'new_item_label'=>'Добавить критерий','min_rows'=>0,'init_rows'=>0,'max_rows'=>5,
            'fields'=>[
                [
                    'name'=>'criterion','label'=>'Критерий','type'=>'select_from_array',
                    'options'=>$this->sortOptions(), 'allows_null'=>false,
                    'wrapper'=>['class'=>'form-group col-md-6'],
                ],
                [
                    'name'=>'direction','label'=>'Направление','type'=>'select_from_array','allows_null'=>true,
                    'options'=>['asc'=>'ASC','desc'=>'DESC'],'wrapper'=>['class'=>'form-group col-md-6'],
                ],
            ],
            'value'=>$this->entry ? $this->sortAssocToRows($this->entry->sort_order ?? []) : [],
            'hint'=>'Если направление пустое — используется направление по умолчанию для критерия.',
        ]);
    }

    private function getFilterDirectionFields() {
      $field = [
        'name'  => 'direction',
        'label' => 'Направление фильтра',
        'type'  => 'select_from_array',
        'options' => ['include'=>'Включить','exclude'=>'Исключить'],
        'allows_null' => false,
        'hint' => 'Включить - будут выбраны только товары соответствующие фильтру. Исключить - будут выбрать все товары, кроме соответствующих фильтру.',
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
        'orders'            => 'Заказы',
      ];

      if(!empty($exclude))
        $filter_options = array_filter($filter_options, fn($key) => !in_array($key, $exclude), ARRAY_FILTER_USE_KEY);

      return $filter_options;
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
                'label'   => 'Логика',
                'type'    => 'select_from_array',
                'options' => ['AND'=>'AND','OR'=>'OR'],
                'default' => 'AND',
              ],
              [
                'name'   => 'rules',
                'label'  => 'Правила',
                'type'   => 'repeatable_conditional',
                'min_rows' => 1,
                'init_rows'=> 1,
                'fields' => [
                  [
                    'name'        => 'attribute',
                    'label'       => 'Атрибут',
                    'type'        => 'select2_from_ajax',
                    'data_source' => route('backpack.helpers.fetch', ['key' => 'attribute']),
                    'attribute'   => 'uniqHtml',
                    'model'       => \Backpack\Store\app\Models\Attribute::class,
                    'placeholder' => "Выберите атрибут",
                    'minimum_input_length' => 2,
                  ],
                  [
                    'name'    => 'operator',
                    'label'   => 'Оператор',
                    'type'    => 'select_from_array',
                    'options' => ['IN'=>'IN','EQ'=>'=','GTE'=>'>=','LTE'=>'<='],
                    'default' => 'IN',
                    'allows_null' => false
                  ],
                  [
                    'name'  => 'values',
                    'label' => 'Значения',
                    'type'  => 'textarea',
                    'hint'  => 'Для IN — список через запятую; для EQ/GTE/LTE — одно значение',
                  ],
                ],
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

          // --- orders ---
          'orders' => [
            'fields' => [
              [
                'name'    => 'metric',
                'label'   => 'Метрика',
                'type'    => 'select_from_array',
                'options' => [
                  'orders'   => 'Кол-во заказов',
                  'quantity' => 'Кол-во товаров',
                ],
                'default' => 'orders',
              ],
              [
                'name'  => 'min_count',
                'label' => 'Мин. значение',
                'type'  => 'number',
                'default' => 1,
                'attributes' => ['min'=>1],
              ],
              [
                'name'  => 'period_days',
                'label' => 'За период (дней)',
                'type'  => 'number',
                'attributes' => ['min'=>0],
                'hint' => '0 = учитывать всю историю заказов',
              ],
              [
                'name'    => 'scope',
                'label'   => 'Источник заказов',
                'type'    => 'select_from_array',
                'options' => [
                  'current_country' => 'Только текущая страна',
                  'global'          => 'Все страны',
                ],
                'default' => 'current_country',
              ],
              $this->getFilterDirectionFields()
            ],
          ],
          
        ];

      if(!empty($exclude))
        $options = array_filter($options, fn($key) => !in_array($key, $exclude), ARRAY_FILTER_USE_KEY);

      return $options;
    }

    private function addFilterFields(array $exclude = [], string $name = '', string $key = 'filters') {

      $name = empty($name)? 'Фильтрация результатов' :$name;

      
      return [
        'name'  => $key,
        'label' => $name,
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

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    // ---------- helpers (локальные) ----------

    protected function pageOptions(): array
    {
        // можно вынести в config
        return [
            'product'  => 'Страница товара',
            'cart'          => 'Корзина',
            'checkout'      => 'Оформление заказа',
            'main'          => 'Главная',
            'category' => 'Страница категории',
            'brand'    => 'Страница бренда',
            'article'    => 'Статья',
        ];
    }

    protected function countryOptions(): array
    {
        return \Store::countryOptions();
    }

    protected function sourceOptionsAnchored(): array
    {
        // базовые алиасы; расширяются через реестр в рантайме
        return [
            'links'             => 'Ассоциированные записи (от якорей)',
            'bought_together'   => 'Покупают вместе (от товарных якорей)',
            'tags'              => 'Теги (от товарных якорей)',
            'category'          => 'Категории (от товарных  якорей)',
            'brand'             => 'Бренды (от товарных якорей)',
            'attributes'        => 'Атрибуты (от товарных якорей)',
            'price_band'        => 'Цена (от товарных якорей)',
            'manual_list_items' => 'Выбрать вручную',
            'base'              => 'Все товары',
        ];
    }

    protected function sortOptions(): array
    {
        return [
            'source'         => 'Источники данных (по приоритету)',
            'discount_first' => 'Наличие скидок',
            'random'         => 'Случайный',
            'price'          => 'Цена',
            'orders_count'   => 'Кол-во заказов',
        ];
    }

    // ----- преобразование значений для repeatable (assoc <-> rows) -----

    protected function assocToRows(?array $assoc): array
    {
        // {'uk':'Заголовок', 'ru':'Заголовок'} => [{locale:'uk',value:'…'}, ...]
        if (!$assoc) return [];
        $out = [];
        foreach ($assoc as $k=>$v) {
            $out[] = ['locale'=>$k, 'value'=>$v];
        }
        return $out;
    }

    protected function rowsToAssoc(?array $rows): array
    {
        // [{locale:'uk',value:'…'}] => {'uk':'…'}
        if (!$rows) return [];
        $out = [];
        foreach ($rows as $row) {
            if (!empty($row['locale'])) {
                $out[$row['locale']] = $row['value'] ?? null;
            }
        }
        return $out;
    }

    protected function sourcesAssocToRows(?array $arr): array
    {
        // ['links', 'bought_together'] OR [{'alias':'links','params':{}}] =>
        // rows [{alias:'links', params:''}, ...]
        if (!$arr) return [];
        $out = [];
        foreach ($arr as $row) {
            if (is_string($row)) {
                $out[] = ['alias'=>$row, 'params'=>null];
            } elseif (is_array($row)) {
                $out[] = ['alias'=>$row['alias'] ?? '', 'params'=> isset($row['params']) ? (is_string($row['params'])?$row['params']:json_encode($row['params'])) : null];
            }
        }
        return $out;
    }

    protected function sortAssocToRows(?array $arr): array
    {
        // ['discount_first','price_asc'] OR [{'criterion':'price','direction':'asc'}] -> rows
        if (!$arr) return [];
        $out = [];
        foreach ($arr as $row) {
            if (is_string($row)) {
                $normalized = $this->normalizeSortCriterionAndDirection($row, null);
                if (!empty($normalized['criterion'])) {
                    $out[] = $normalized;
                }
            } elseif (is_array($row)) {
                $normalized = $this->normalizeSortCriterionAndDirection(
                    $row['criterion'] ?? null,
                    $row['direction'] ?? null
                );
                if (!empty($normalized['criterion'])) {
                    $out[] = $normalized;
                }
            }
        }
        return $out;
    }

    protected function normalizeSortCriterionAndDirection(?string $criterion, ?string $direction): array
    {
        $criterion = strtolower(trim((string) $criterion));
        $direction = strtolower(trim((string) $direction));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : null;

        $legacy = [
            'price_asc' => ['criterion' => 'price', 'direction' => 'asc'],
            'price_desc' => ['criterion' => 'price', 'direction' => 'desc'],
            'orders' => ['criterion' => 'orders_count', 'direction' => 'desc'],
            'orders_desc' => ['criterion' => 'orders_count', 'direction' => 'desc'],
            'orders_asc' => ['criterion' => 'orders_count', 'direction' => 'asc'],
        ];

        $normalized = $legacy[$criterion] ?? [
            'criterion' => $criterion,
            'direction' => null,
        ];

        if ($direction !== null) {
            $normalized['direction'] = $direction;
        }

        return $normalized;
    }

    protected function foundationAssocToRows(?array $assoc): array
    {
        if (!$assoc) return [];
        $rows = [];
        foreach ($assoc as $k=>$v) {
            $rows[] = ['key'=>$k, 'value'=> is_string($v) ? $v : json_encode($v)];
        }
        return $rows;
    }

    // --------- HOOKs: преобразуем repeatable -> JSON перед сохранением ---------

    public function store()
    {
        // $this->massageInputs();
        return $this->traitStore();
    }

    public function update()
    {
        // $this->massageInputs();
        return $this->traitUpdate();
    }

    protected function massageInputs(): void
    {
        $req = $this->crud->getRequest();
        // foreach (['title','button_text','full_url'] as $field) {
        //     $req->request->set($field, $this->rowsToAssoc($req->input($field)));
        // }

        // priority_sources: оставим массив вида [{'alias':'links','params':<raw>}]
        $srcRows = $req->input('priority_sources', []);
        $srcRows = is_array($srcRows)? $srcRows: json_decode($srcRows, true);
        dd($srcRows);

        $norm = [];
        foreach ($srcRows as $r) {
            if (!empty($r['alias'])) {
                $params = $r['params'] ?? null;
                // если это JSON — попытаемся декоднуть
                if (is_string($params)) {
                    $decoded = json_decode($params, true);
                    $params = $decoded ?? $params;
                }
                $norm[] = ['alias'=>$r['alias'], 'params'=>$params];
            }
        }
        $req->request->set('priority_sources', $norm);

        // sort_order
        $sortRows = $req->input('sort_order', []);
        $srcRows = is_array($srcRows)? $srcRows: json_decode($srcRows, true);
        
        $sNorm = [];
        foreach ($sortRows as $r) {
            if (!empty($r['criterion'])) {
                $row = ['criterion'=>$r['criterion']];
                if (!empty($r['direction'])) $row['direction'] = $r['direction'];
                $sNorm[] = $row;
            }
        }
        $req->request->set('sort_order', $sNorm);

        // foundation (key/value строки -> assoc или числовые)
        $foundRows = $req->input('foundation', []);
        $fAssoc = [];
        foreach ($foundRows as $r) {
            if (!empty($r['key'])) {
                $val = $r['value'] ?? null;
                $decoded = is_string($val) ? json_decode($val, true) : $val;
                $fAssoc[$r['key']] = $decoded ?? $val;
            }
        }
        $req->request->set('foundation', $fAssoc);

        $this->crud->setRequest($req);
        $this->crud->setRequest($this->crud->getRequest());
    }
}
