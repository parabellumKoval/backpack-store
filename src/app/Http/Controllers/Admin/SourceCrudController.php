<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\SourceRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class SourceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;


    use \App\Http\Controllers\Admin\Traits\SourceCrud;
    
    private $available_languages = [];
    private $brand_class = null;
    private $entry = null;

    public function setup()
    {
      $this->brand_class = config('backpack.store.source.class', 'Backpack\Store\app\Models\Source');

      $this->crud->setModel($this->brand_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/source');
      $this->crud->setEntityNameStrings('выгрузка', 'выгрузки');


      // CURRENT MODEL
      $this->setEntry();
      $this->setLanguages();
    }

    protected function setupListOperation()
    {
      $this->crud->addColumn([
        'name' => 'is_active',
        'label' => '✅',
        'type' => 'check'
      ]);

      $this->crud->addColumn([
        'name' => 'name',
        'label' => 'Название'
      ]);

      $this->crud->addColumn([
        'name' => 'history',
        'label' => 'Кол-во выгрузок',
        'type' => 'relationship_count',
        'suffix' => '',
      ]);

      $this->crud->addColumn([
        'name' => 'last_loading',
        'label' => 'Последняя выгрузка',
        'type' => 'datetime'
      ]);

      $this->listOperation();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(SourceRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      

        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
          'tab' => 'Основное'
        ]);
        
        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
          'tab' => 'Основное'
        ]);

        // Key
        $this->crud->addField([
          'name' => 'key',
          'label' => 'Ключ',
          'type' => 'text',
          'hint' => 'Уникальный идентификатор (название/ключ на латинице) этой выгрузки. Не должен совпадать с ключами других выгрузок. После создания не подлежит редактированию.',
          'tab' => 'Основное'
        ]);

        // LINK
        $this->crud->addField([
          'name' => 'supplier',
          'label' => 'Поставщик',
          'type' => 'relationship',
          'options'   => (function ($query) {
              return $query->where('is_active', 1)->get();
          }),
          'tab' => 'Основное'
        ]);
        
        // TYPE
        $this->crud->addField([
          'name' => 'type',
          'label' => 'Тип',
          'type' => 'select_from_array',
          'options' => [
            'xml_link' => 'XML-ссылка'
          ],
          'tab' => 'Основное'
        ]);

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

        // SETTINGS
        $this->crud->addField([
          'name' => 'delim_0',
          'type' => 'custom_html',
          'value' => '<h3>Источник данных</h3>',
          'tab' => 'Настройки'
        ]);

        // LINK
        $this->crud->addField([
          'name' => 'link',
          'label' => 'Ссылка',
          'type' => 'text',
          'hint' => 'Ссылка на xml-каталог для выгрузки данных',
          'tab' => 'Настройки'
        ]);

        // LINK
        $this->crud->addField([
          'name' => 'every_minutes',
          'label' => 'Обновлять данные каждые',
          'type' => 'number',
          'suffix' => 'мин.',
          'attributes' => [
              'min' => 60,
          ],
          'hint' => 'Укажите в минутах как часто необходимо обновлять данные из источника.',
          'tab' => 'Настройки'
        ]);

        // 
        $this->crud->addField([
          'name' => 'delim_1',
          'type' => 'custom_html',
          'value' => '<h3>Данные</h3>',
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'item',
          'label' => 'Путь к товару',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'hint' => 'Путь к товару в источнике данных.',
          'tab' => 'Настройки'
        ]);


        $this->crud->addField([
          'name' => 'delim_2',
          'type' => 'custom_html',
          'value' => '<h3>Настройка полей</h3>
            <p class="help-block">Введите точные названия полей из xml-каталога, которые соответвуют указанным данным. 
            Необходимо для того, чтобы установить соответствия между полями с данными из xml-кателога с аналогичными полями на сайте.
            </p>
          ',
          'tab' => 'Настройки'
        ]);

        // XML -> NAME
        $this->crud->addField([
          'name' => 'fieldName',
          'label' => 'Название',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);
        
        // XML -> CODE
        $this->crud->addField([
          'name' => 'fieldCode',
          'label' => 'Артикул',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'fieldBarcode',
          'label' => 'Код/баркод',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'fieldPrice',
          'label' => 'Цена',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'fieldBrand',
          'label' => 'Бренд',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'fieldCategory',
          'label' => 'Категория',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'fieldInStock',
          'label' => 'Наличие товара',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'settings',
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
          'tab' => 'Настройки'
        ]);

        $this->crud->addField([
          'name' => 'delim_3',
          'type' => 'custom_html',
          'value' => '<h3>Общие настройки</h3>',
          'tab' => 'Настройки'
        ]);


        $this->crud->addField([
          'name' => 'createNewBrand',
          'label' => 'Создавать новые бренды',
          'type' => 'checkbox',
          'default' => 1,
          'fake' => true,
          'store_in' => 'settings',
          'hint' => 'Создавать новый бренд, если он не найден в текущей Базе Данных?',
          'wrapper'   => [ 
            'class' => 'form-group col-md-12'
          ],
          'tab' => 'Настройки'
        ]);


        $this->crud->addField([
          'name' => 'language',
          'label' => 'Язык контента',
          'type' => 'select_from_array',
          'options' => $this->available_languages,
          'default' => 'ru',
          'allows_null' => false,
          'fake' => true,
          'store_in' => 'settings',
          'hint' => 'Укажите язык на котором представлена информация в источнике данных.',
          'wrapper'   => [ 
            'class' => 'form-group col-md-12'
          ],
          'tab' => 'Настройки'
        ]);


        $this->crud->addField([
          'name' => 'inStockRules',
          'label' => 'Правила наличия товара',
          'type' => 'repeatable',
          'fields' => [
            [
              'name' => 'key',
              'label' => 'Значение в xml-каталоге',
              'type' => 'text',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ]
            ],
            // [
            //   'name' => 'operator',
            //   'label' => 'Оператор',
            //   'type' => 'select_from_array',
            //   'options' => [
            //     'equal' => '=',
            //     'more' => '>',
            //     'less' => '<',
            //   ],
            //   'allows_null' => false,
            //   'default' => 'equal',
            //   'wrapper'   => [ 
            //     'class' => 'form-group col-md-4'
            //   ]
            // ],
            [
              'name' => 'value',
              'label' => 'Будет соответсвовать такому значению',
              'type' => 'text',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ]
            ]
          ],
          'fake' => true,
          'store_in' => 'settings',
          'new_item_label'  => 'Добавить правило',
          'init_rows' => 0,
          'min_rows' => 0,
          'hint' => 'Добавьте правила по которым будет интерпритироваться значения поля "Наличие товара"',
          'wrapper'   => [ 
            'class' => 'form-group col-md-12'
          ],
          'tab' => 'Настройки'
        ]);

      // $rules = json_decode($this->entry->rules, true);
      // dd($this->entry->rules);

        // RULES
        // $this->crud->addField([
        //   'name' => 'repeatable_helper',
        //   'type' => 'ak_repeatable_helper',
        //   'tab' => 'Правила выгрузки',
        //   'wrapper' => [
        //     'data-target' => 'repeatable-data',
        //     'data-value' => !empty($this->entry->rules)? json_encode($this->entry->rules): null,
        //   ],
        // ]);

        $this->crud->addField([
          'name' => 'rules',
          'label' => 'Правила выгрузки',
          'type' => 'ak_source_repeatable',
          'tab' => 'Правила выгрузки',
          'fields' => [
              ...$this->getTypeFields(),
              ...$this->getTargetFields(),  
          ],
          'wrapper' => [
            'data-target' => 'repeatable-el',
          ],
          'new_item_label'  => 'Добавить правило',
          'init_rows' => 0,
          'min_rows' => 0
        ]);

        // CATEGORIES
        $this->crud->addField([
          'name' => 'delim_cats',
          'type' => 'custom_html',
          'value' => '<h3>Настройки соответствия категорий</h3>
            <p class="help-block">Для того, чтобы товарам автоматически присваивались категории, 
            необходимо сперва установить соответствие между категориями из xml-выгрузки с 
            аналогичными категориями на сайте. Иначе категория присвоена не будет.</p>',
          'tab' => 'Настройки категорий'
        ]);

        $this->crud->addField([
          'name' => 'categoriesData',
          'label' => 'Соответствие категорий',
          'type' => 'repeatable',
          'fields' => [
            [
              'name' => 'category',
              'label' => 'Категория из xml-выгрузки',
              'type' => 'text',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],[
              'label'  => "Аналогичная категория на сайте",
              'type' => "select2_from_ajax",
              'name' => 'category_id',
              'entity' => 'categories',
              'placeholder' => "Select a category", 
              'model' => 'Backpack\Store\app\Models\Category',
              'attribute' => "name",
              'minimum_input_length' => 2,
              'data_source' => url("/admin/api/category"),
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ]
          ],
          'wrapper' => [
            'data-target' => 'repeatable-el',
          ],
          'new_item_label'  => 'Добавить правило',
          'init_rows' => 0,
          'min_rows' => 0,
          'tab' => 'Настройки категорий'
        ]);

        // BRANDS
        $this->crud->addField([
          'name' => 'delim_brands',
          'type' => 'custom_html',
          'value' => '<h3>Настройки соответствия брендов</h3>
            <p class="help-block">Бывают случае, когда название бренда в xml-каталоге и название бренда 
            на сайте отличаются или не распознаются как идентичные. Тогда на сайте будет создан 
            (при включенной соответствующей настройнке) дубль бренда с другим названими.
            Чтобы избежать таких случаев необходимо установить соответвие между названием 
            бренда в xml-каталоге и названием бренда на сайте. (Используется для редких случаев, все бренды заполнять не нужно.)</p>',
          'tab' => 'Настройки брендов'
        ]);

        $this->crud->addField([
          'name' => 'brandsData',
          'label' => 'Соответствие брендов',
          'type' => 'repeatable',
          'fields' => [
            [
              'name' => 'brand',
              'label' => 'Бренд из xml-выгрузки',
              'type' => 'text',
              'wrapper'   => [ 
                'class' => 'form-group col-md-4'
              ],
            ],[
              'label'  => "Аналогичный бренд на сайте",
              'type' => "select2_from_ajax",
              'name' => 'brand_id',
              'entity' => 'brands',
              'placeholder' => "Select a category", 
              'model' => 'Backpack\Store\app\Models\Brand',
              'attribute' => "name",
              'minimum_input_length' => 2,
              'data_source' => url("/admin/api/brand"),
              'wrapper'   => [ 
                'class' => 'form-group col-md-8'
              ],
            ]
          ],
          'wrapper' => [
            'data-target' => 'repeatable-el',
          ],
          'new_item_label'  => 'Добавить правило',
          'init_rows' => 0,
          'min_rows' => 0,
          'tab' => 'Настройки брендов'
        ]);

      $this->createOperation();
    }

    private function getTypeFields() {
      $js_attributes = [
        'data-value' => '',
        'onchange' => "
          const value = event.target.value
          
          let wrapper = null

          closeAll(event);

          if(value === 'overprice') {
            wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=overprice]');
          }else if(value === 'exchange') {
            wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=exchange_coff]');
          }

          if(wrapper) {
            wrapper.style.display = 'block';
          }

          function closeAll(event) {
            const fields = event.target.closest('.repeatable-element').querySelectorAll('[data-type=type-field]')
            fields.forEach((field) => {
              field.style.display = 'none';
            })
          } 
        "
      ];

      return [
        [
          'name'    => 'type',
          'type'    => 'select_from_array',
          'label'   => 'Тип действия',
          'options' => [
            'overprice' => 'Наценка',
            'exchange' => 'Конвертация валют',
            'blacklist' => 'Исключить из выгрузки',
            'whitelist' => 'Включить в выгрузку',
          ],
          'default' => null,
          'allows_null' => true,
          'wrapper' => ['class' => 'form-group col-md-4'],
          'attributes' => $js_attributes,
        ],
        [
          'name'    => 'overprice',
          'type'    => 'number',
          'label'   => 'Коэффициент наценки',
          'wrapper' => [
            'class' => 'form-group col-md-8',
            'data-target' => 'overprice',
            'data-type' => 'type-field',
            'style' => 'display: none;'
          ],
          'attributes' => [
            'step' => 0.0001
          ]
        ],
        [
          'name'    => 'exchange_coff',
          'type'    => 'number',
          'label'   => 'Дополнительный коэффициент конвертации',
          'wrapper' => [
            'class' => 'form-group col-md-8',
            'data-target' => 'exchange_coff',
            'data-type' => 'type-field',
            'style' => 'display: none;'
          ],
          'attributes' => [
            'step' => 0.0001
          ],
          'hint' => 'Конвертация производится по актуальному курсу PrivatBank <b>USD 1 = ' . $this->getExchangeRate() . ' UAH</b>.
            Курс может быть скорректирован дополнительным коэффициентом.'
        ]
      ];
    }

    private function getHint($field_name) {

      $hint = "<ol>
        <li>Чтобы найти точное совпадение просто введите {$field_name} товара.</li>
        <li>Чтобы искать только по начальным символам используйте <b>^</b> вначале строки.
        Например <b>^sale_</b> найдет все товары {$field_name} которых начинается с <b>sale_</b></li>
        <li>Чтобы найти фразу встречающуюся в любом месте добавьте символы <b>%</b> в начале и конце строки. 
        Например <b>%перчатки%</b> найдет все товары в который есть слово 'перчатки'. 
        (Кожаные перчатки, Перчатки для фитнеса и т.д.)</li> 
      </ol>";

      return $hint;
    }

    private function getTargetFields() {
      $js_attributes = [
        'data-value' => "
          loadData()
          function loadData() {
            console.log('load data)
          }
        ",
        // 'onfocus' => "this.setAttribute('data-value', this.value);",
        'onchange' => "
          const value = event.target.value

          closeAll(event)

          if(value === 'all') {

          }else if(value === 'brand') {
            const wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=brand-list]');
            wrapper.style.display = 'block';
          }else if(value === 'code') {
            const wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=code-list]');
            wrapper.style.display = 'block';
          }else if(value === 'price') {
            const minprice = event.target.closest('.repeatable-element').querySelector('[data-target=min-price]');
            minprice.style.display = 'block';

            const maxprice = event.target.closest('.repeatable-element').querySelector('[data-target=max-price]');
            maxprice.style.display = 'block';
          }else if(value === 'inStock') {
            const wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=in-stock]');
            wrapper.style.display = 'block';
          }else if(value === 'name') {
            const wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=name-list]');
            wrapper.style.display = 'block';
          }else if(value === 'category') {
            const wrapper = event.target.closest('.repeatable-element').querySelector('[data-target=category-list]');
            wrapper.style.display = 'block';
          }

          function closeAll(event) {
            const fields = event.target.closest('.repeatable-element').querySelectorAll('[data-type=target-field]')
            fields.forEach((field) => {
              field.style.display = 'none';
            })
          } 
        "
      ];

      return [
        [
          'name'    => 'target',
          'type'    => 'select_from_array',
          'label'   => 'Находить товар по:',
          'options' => [
            'all' => 'Все товары',
            'category' => 'Категория',
            'brand' => 'Бренд',
            'code' => 'Артикул',
            'price' => 'Стоимость',
            'inStock' => 'Наличие',
            'name' => 'Название'
          ],
          'default' => 'all',
          'attributes' => $js_attributes,
        ],[
          'name'    => 'categories',
          'label'   => 'Список категорий',
          'type'    => 'table',
          'columns' => [
            'name'  => 'Название категории'
          ],
          'entity_singular' => 'категорию',
          'wrapper' => [
            'data-target' => 'category-list',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ],
          'hint' => $this->getHint('бренд')
        ],[
          'name'    => 'brands',
          'label'   => 'Список брендов',
          'type'    => 'table',
          'columns' => [
            'name'  => 'Название бренда'
          ],
          'entity_singular' => 'бренд',
          'wrapper' => [
            'data-target' => 'brand-list',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ],
          'hint' => $this->getHint('бренд')
        ],[
          'name'    => 'codes',
          'label'   => 'Список артикулов',
          'type'    => 'table',
          'columns' => [
            'name'  => 'Артикул товара'
          ],
          'entity_singular' => 'артикул',
          'wrapper' => [
            'data-target' => 'code-list',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ],
          'hint' => $this->getHint('артикул')
        ],[
          'name'    => 'names',
          'label'   => 'Список названий',
          'type'    => 'table',
          'columns' => [
            'name'  => 'Название товара'
          ],
          'entity_singular' => 'название',
          'wrapper' => [
            'data-target' => 'name-list',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ],
          'hint' => $this->getHint('название')
        ],
        [
          'name'    => 'in_stock',
          'type'    => 'text',
          'label'   => 'Значение поля "В наличие"',
          'wrapper' => [
            'data-target' => 'in-stock',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ]
        ],
        [
          'name'    => 'min_price',
          'type'    => 'number',
          'label'   => 'Минимальная цена',
          'wrapper' => [
            'class' => 'form-group col-md-6',
            'data-target' => 'min-price',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ]
        ],
        [
          'name'    => 'max_price',
          'type'    => 'number',
          'label'   => 'Максимальная цена',
          'wrapper' => [
            'class' => 'form-group col-md-6',
            'data-target' => 'max-price',
            'data-type' => 'target-field',
            'style' => 'display: none;'
          ]
        ]
      ];
    }


    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        $this->crud->removeField('key');

        // Key
        $this->crud->addField([
          'name' => 'key',
          'label' => 'Ключ',
          'type' => 'text',
          'hint' => 'Уникальный идентификатор (название/ключ на латинице) этой выгрузки. Не должен совпадать с ключами других выгрузок. После создания не подлежит редактированию.',
          'tab' => 'Основное',
          'attributes' => [
            'readonly' => 'readonly'
          ]
        ])->afterField('name');
    }


    /**
     * setLanguages
     *
     * @return void
     */
    private function setLanguages() {
      $this->available_languages = config('backpack.crud.locales');
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
    
}
