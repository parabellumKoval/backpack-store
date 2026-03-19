<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Http\Requests\Admin\CampaignRequest;
use Backpack\Store\app\Models\Campaign;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Throwable;

class CampaignCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup(): void
    {
        CRUD::setModel(Campaign::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/campaign');
        CRUD::setEntityNameStrings('акция', 'акции');
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn(['name' => 'id', 'label' => 'ID', 'type' => 'number']);
        CRUD::addColumn(['name' => 'name', 'label' => 'Название']);
        CRUD::addColumn(['name' => 'discount_percent', 'label' => 'Скидка %']);
        CRUD::addColumn(['name' => 'priority', 'label' => 'Приоритет']);
        CRUD::addColumn(['name' => 'is_active', 'label' => 'Активна', 'type' => 'boolean']);
        CRUD::addColumn(['name' => 'is_timed', 'label' => 'По времени', 'type' => 'boolean']);
        CRUD::addColumn([
            'name' => 'period',
            'label' => 'Период',
            'type' => 'model_function',
            'function_name' => 'getPeriodLabelAttribute',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(CampaignRequest::class);
        $this->crud->setOperationSetting('saveAllInputsExcept', [
            '_token',
            '_method',
            'http_referrer',
            'current_tab',
            'save_action',
            'selection',
        ]);

        CRUD::addField([
            'name' => 'is_active',
            'label' => 'Активна',
            'type' => 'checkbox',
            'default' => 1,
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'hint' => 'Можно оставить пустым, сгенерируется автоматически.',
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'discount_percent',
            'label' => 'Скидка (%)',
            'type' => 'number',
            'attributes' => ['min' => 0, 'max' => 99.99, 'step' => '0.01'],
            'default' => 0,
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'priority',
            'label' => 'Приоритет',
            'type' => 'number',
            'attributes' => ['min' => -1000, 'max' => 1000, 'step' => 1],
            'default' => 0,
            'hint' => 'Если товар попадает в несколько акций, применяется акция с большим приоритетом.',
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'countries',
            'label' => 'Страны',
            'type' => 'select2_from_array',
            'allows_multiple' => true,
            'options' => $this->countryOptions(),
            'allows_null' => true,
            'hint' => 'Пусто = акция активна для всех стран.',
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'short_description',
            'label' => 'Краткое описание',
            'type' => 'textarea',
            'attributes' => ['rows' => 4],
            'tab' => 'Описание',
        ]);

        CRUD::addField([
            'name' => 'conditions_html',
            'label' => 'Подробные условия',
            'type' => 'ckeditor',
            'tab' => 'Описание',
        ]);

        CRUD::addField([
            'name' => 'is_timed',
            'label' => 'Ограничена по времени',
            'type' => 'checkbox',
            'default' => 0,
            'tab' => 'Период',
        ]);

        CRUD::addField([
            'name' => 'starts_at',
            'label' => 'Дата старта',
            'type' => 'datetime_picker',
            'hint' => 'Время в UTC.',
            'tab' => 'Период',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'ends_at',
            'label' => 'Дата завершения',
            'type' => 'datetime_picker',
            'hint' => 'Время в UTC.',
            'tab' => 'Период',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'show_timer_card',
            'label' => 'Показывать таймер на карточках',
            'type' => 'checkbox',
            'tab' => 'Видимость',
        ]);

        CRUD::addField([
            'name' => 'show_timer_product',
            'label' => 'Показывать таймер на странице товара',
            'type' => 'checkbox',
            'tab' => 'Видимость',
        ]);

        CRUD::addField([
            'name' => 'horizontal_banner',
            'label' => 'Горизонтальный баннер',
            'type' => 'image',
            'crop' => false,
            'hint' => 'Поле переводимое: выберите язык формы и загрузите баннер для этого языка.',
            'tab' => 'Баннеры',
        ]);

        CRUD::addField([
            'name' => 'vertical_banner',
            'label' => 'Вертикальный баннер',
            'type' => 'image',
            'crop' => false,
            'hint' => 'Поле переводимое: выберите язык формы и загрузите баннер для этого языка.',
            'tab' => 'Баннеры',
        ]);

        CRUD::addField([
            'name' => 'add_to_main_banner',
            'label' => 'Добавить акцию в главный баннер',
            'type' => 'checkbox',
            'tab' => 'Баннеры',
        ]);

        CRUD::addField([
            'name' => 'add_banner_to_catalog',
            'label' => 'Добавить баннер в каталог',
            'type' => 'checkbox',
            'tab' => 'Баннеры',
        ]);

        CRUD::addField([
            'name' => 'catalog_banner_frequency',
            'label' => 'Частота баннера в каталоге (каждые N товаров)',
            'type' => 'number',
            'attributes' => ['min' => 1, 'max' => 500, 'step' => 1],
            'tab' => 'Баннеры',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'catalog_banner_position',
            'label' => 'Позиция баннера в каталоге',
            'type' => 'number',
            'attributes' => ['min' => 1, 'max' => 500, 'step' => 1],
            'tab' => 'Баннеры',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'selection',
            'label' => 'Подбор товаров',
            'type' => 'conditional_fields',
            'tab' => 'Товары',
            'driver' => [
                'name' => 'product_source',
                'label' => 'Источник',
                'type' => 'select_from_array',
                'options' => [
                    'filters' => 'Все товары + фильтры',
                    'manual' => 'Выбрать вручную',
                    'mixed' => 'Смешанный (фильтры + вручную)',
                ],
                'default' => 'filters',
            ],
            'branches' => [
                'filters' => [
                    'fields' => [
                        $this->addFilterFields([], 'Фильтры товаров', 'product_filters'),
                    ],
                ],
                'manual' => [
                    'fields' => [
                        $this->manualProductsField(),
                    ],
                ],
                'mixed' => [
                    'fields' => [
                        $this->manualProductsField(),
                        $this->addFilterFields([], 'Фильтры товаров', 'product_filters'),
                    ],
                ],
            ],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    public function store()
    {
        $this->massageInputs();

        return $this->traitStore();
    }

    public function update()
    {
        $this->massageInputs();

        return $this->traitUpdate();
    }

    protected function manualProductsField(): array
    {
        return [
            'name' => 'manual_products',
            'label' => 'Товары (ручной набор)',
            'type' => 'select2_from_ajax_multiple',
            'data_source' => route('backpack.helpers.fetch', ['key' => 'product']),
            'attribute' => 'uniqHtml',
            'model' => \Backpack\Store\app\Models\Product::class,
            'allows_multiple' => true,
            'minimum_input_length' => 2,
            'placeholder' => 'Выберите товары',
        ];
    }

    private function getFilterDirectionFields(): array
    {
        return [
            'name' => 'direction',
            'label' => 'Направление',
            'type' => 'select_from_array',
            'options' => ['include' => 'Включить', 'exclude' => 'Исключить'],
            'default' => 'include',
        ];
    }

    private function getFilterOptions(array $exclude = []): array
    {
        $filterOptions = [
            'categories' => 'Категории',
            'brands' => 'Бренды',
            'tags' => 'Теги',
            'attributes' => 'Атрибуты',
            'price_range' => 'Диапазон цены',
            'stock' => 'Склад',
            'sale' => 'Скидка',
            'products' => 'Товары',
            'orders' => 'Заказы',
        ];

        if (!empty($exclude)) {
            $filterOptions = array_filter(
                $filterOptions,
                fn($key) => !in_array($key, $exclude, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        return $filterOptions;
    }

    private function getFilterBranchOptions(array $exclude = []): array
    {
        $options = [
            'categories' => [
                'fields' => [
                    [
                        'name' => 'categories',
                        'label' => 'Категории',
                        'type' => 'select2_from_ajax_multiple',
                        'data_source' => route('backpack.helpers.fetch', ['key' => 'category']),
                        'attribute' => 'uniqHtml',
                        'model' => \Backpack\Store\app\Models\Category::class,
                        'minimum_input_length' => 2,
                        'placeholder' => 'Выберите категории',
                    ],
                    [
                        'name' => 'include_children',
                        'label' => 'Включать подкатегории',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'brands' => [
                'fields' => [
                    [
                        'name' => 'brands',
                        'label' => 'Бренды',
                        'type' => 'select2_from_ajax_multiple',
                        'data_source' => route('backpack.helpers.fetch', ['key' => 'brand']),
                        'attribute' => 'uniqHtml',
                        'model' => \Backpack\Store\app\Models\Brand::class,
                        'minimum_input_length' => 2,
                        'placeholder' => 'Выберите бренды',
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'tags' => [
                'fields' => [
                    [
                        'name' => 'tags',
                        'label' => 'Теги',
                        'type' => 'select2_from_ajax_multiple',
                        'data_source' => route('backpack.helpers.fetch', ['key' => 'tag']),
                        'attribute' => 'uniqHtml',
                        'model' => \Backpack\Tag\app\Models\Tag::class,
                        'minimum_input_length' => 2,
                        'placeholder' => 'Выберите теги',
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'attributes' => [
                'fields' => [
                    [
                        'name' => 'logic',
                        'label' => 'Логика',
                        'type' => 'select_from_array',
                        'options' => ['AND' => 'AND', 'OR' => 'OR'],
                        'default' => 'AND',
                    ],
                    [
                        'name' => 'rules',
                        'label' => 'Правила',
                        'type' => 'repeatable_conditional',
                        'min_rows' => 1,
                        'init_rows' => 1,
                        'fields' => [
                            [
                                'name' => 'attribute',
                                'label' => 'Атрибут',
                                'type' => 'select2_from_ajax',
                                'data_source' => route('backpack.helpers.fetch', ['key' => 'attribute']),
                                'attribute' => 'uniqHtml',
                                'model' => \Backpack\Store\app\Models\Attribute::class,
                                'minimum_input_length' => 2,
                                'placeholder' => ''
                            ],
                            [
                                'name' => 'operator',
                                'label' => 'Оператор',
                                'type' => 'select_from_array',
                                'options' => ['IN' => 'IN', 'EQ' => '=', 'GTE' => '>=', 'LTE' => '<='],
                                'default' => 'IN',
                            ],
                            [
                                'name' => 'values',
                                'label' => 'Значения',
                                'type' => 'textarea',
                                'hint' => 'Для IN - список через запятую; для EQ/GTE/LTE - одно значение.',
                            ],
                        ],
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'price_range' => [
                'fields' => [
                    [
                        'name' => 'price_min',
                        'label' => 'Мин. цена',
                        'type' => 'number',
                        'attributes' => ['min' => 0, 'step' => '0.01'],
                    ],
                    [
                        'name' => 'price_max',
                        'label' => 'Макс. цена',
                        'type' => 'number',
                        'attributes' => ['min' => 0, 'step' => '0.01'],
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'stock' => [
                'fields' => [
                    [
                        'name' => 'only_in_stock',
                        'label' => 'Только в наличии',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                    [
                        'name' => 'stock_min',
                        'label' => 'Мин. остаток',
                        'type' => 'number',
                        'attributes' => ['min' => 0],
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'sale' => [
                'fields' => [
                    [
                        'name' => 'min_discount_percent',
                        'label' => 'Мин. скидка %',
                        'type' => 'number',
                        'attributes' => ['min' => 0, 'max' => 100, 'step' => 1],
                    ],
                    [
                        'name' => 'require_old_price',
                        'label' => 'Только товары со скидкой',
                        'type' => 'checkbox',
                        'default' => true,
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'products' => [
                'fields' => [
                    [
                        'name' => 'include_product_ids',
                        'label' => 'Товары',
                        'type' => 'select2_from_ajax_multiple',
                        'data_source' => route('backpack.helpers.fetch', ['key' => 'product']),
                        'attribute' => 'uniqHtml',
                        'model' => \Backpack\Store\app\Models\Product::class,
                        'minimum_input_length' => 2,
                        'placeholder' => 'Выберите товары',
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
            'orders' => [
                'fields' => [
                    [
                        'name' => 'metric',
                        'label' => 'Метрика',
                        'type' => 'select_from_array',
                        'options' => [
                            'orders' => 'Кол-во заказов',
                            'quantity' => 'Кол-во товаров',
                        ],
                        'default' => 'orders',
                    ],
                    [
                        'name' => 'min_count',
                        'label' => 'Мин. значение',
                        'type' => 'number',
                        'default' => 1,
                        'attributes' => ['min' => 1],
                    ],
                    [
                        'name' => 'period_days',
                        'label' => 'За период (дней)',
                        'type' => 'number',
                        'attributes' => ['min' => 0],
                    ],
                    [
                        'name' => 'scope',
                        'label' => 'Источник заказов',
                        'type' => 'select_from_array',
                        'options' => [
                            'current_country' => 'Только текущая страна',
                            'global' => 'Все страны',
                        ],
                        'default' => 'current_country',
                    ],
                    $this->getFilterDirectionFields(),
                ],
            ],
        ];

        if (!empty($exclude)) {
            $options = array_filter($options, fn($key) => !in_array($key, $exclude, true), ARRAY_FILTER_USE_KEY);
        }

        return $options;
    }

    private function addFilterFields(array $exclude = [], string $name = '', string $key = 'filters'): array
    {
        $name = $name === '' ? 'Фильтрация результатов' : $name;

        return [
            'name' => $key,
            'label' => $name,
            'type' => 'repeatable_conditional',
            'min_rows' => 0,
            'init_rows' => 0,
            'new_item_label' => 'Добавить правило',
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'conditional_fields',
                    'label' => 'Правило',
                    'driver' => [
                        'name' => 'key',
                        'label' => 'Тип правила',
                        'type' => 'select_from_array',
                        'options' => $this->getFilterOptions($exclude),
                        'allows_null' => false,
                    ],
                    'branches' => $this->getFilterBranchOptions($exclude),
                ],
            ],
        ];
    }

    protected function countryOptions(): array
    {
        return \Store::countryOptions();
    }

    protected function massageInputs(): void
    {
        $req = $this->crud->getRequest();

        $source = strtolower((string) $req->input('product_source', 'filters'));
        if (!in_array($source, ['filters', 'manual', 'mixed'], true)) {
            $source = 'filters';
        }

        $filters = $this->decodeArrayInput($req->input('product_filters', []));
        $filters = $this->normalizeFilterRules($filters);
        $manualProducts = $this->normalizeIdList($req->input('manual_products', []));

        if ($source === 'manual') {
            $filters = [];
        } elseif ($source === 'filters') {
            $manualProducts = [];
        }

        $req->request->set('product_source', $source);
        $req->request->set('product_filters', is_array($filters) ? array_values($filters) : []);
        $req->request->set('manual_products', $manualProducts);
        $req->request->set('countries', $this->normalizeCountryCodes($req->input('countries', null)));
        $req->request->set(
            'catalog_banner_position',
            $this->normalizeCatalogBannerPosition(
                $req->input('catalog_banner_position'),
                $req->input('add_banner_to_catalog')
            )
        );
        $req->request->set('horizontal_banner', $this->normalizeBannerInput($req->input('horizontal_banner')));
        $req->request->set('vertical_banner', $this->normalizeBannerInput($req->input('vertical_banner')));

        $this->crud->setRequest($req);
        $this->crud->setRequest($this->crud->getRequest());
    }

    protected function decodeArrayInput(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        return is_array($value) ? $value : [];
    }

    protected function normalizeFilterRules(mixed $value): array
    {
        $rows = $this->decodeArrayInput($value);
        $rules = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rule = $row['rule'] ?? $row;
            if (!is_array($rule)) {
                continue;
            }

            $type = $rule['type'] ?? $rule['key'] ?? null;
            if (!is_string($type) || trim($type) === '') {
                continue;
            }

            $rule['type'] = trim($type);
            $rule['key'] = trim($type);
            $rules[] = $rule;
        }

        return array_values($rules);
    }

    protected function normalizeIdList(mixed $value): array
    {
        $rows = $this->decodeArrayInput($value);
        $ids = [];

        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['product_id'] ?? null) : $row;
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
    }

    protected function normalizeCountryCodes(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $codes = $this->decodeArrayInput($value);
        if (empty($codes) && is_string($value)) {
            $codes = [$value];
        }

        $normalized = [];
        foreach ($codes as $code) {
            if (!is_scalar($code)) {
                continue;
            }

            $country = strtolower(trim((string) $code));
            if ($country === '') {
                continue;
            }

            $normalized[] = $country;
        }

        $normalized = array_values(array_unique($normalized));

        return empty($normalized) ? null : $normalized;
    }

    protected function normalizeBannerInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!str_starts_with($value, 'data:image')) {
            return $value;
        }

        try {
            $stored = app(ImageUploader::class)->uploadFromBase64(
                $value,
                new ImageUploadOptions(folder: 'campaigns')
            );

            return $stored->url;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    protected function normalizeCatalogBannerPosition(mixed $value, mixed $enabled): ?int
    {
        $position = is_numeric($value) ? (int) $value : null;
        if ($position !== null && $position > 0) {
            return $position;
        }

        $isEnabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $isEnabled ? 1 : null;
    }
}
