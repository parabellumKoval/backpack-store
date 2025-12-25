<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class DeliverySettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {

        $countryOptions = \Store::countryOptions();
        $registry->group('delivery', function ($group) use ($countryOptions) {

            $group->title('Доставка')->icon('la la-store')

                // ────────────────────────────────────────────────
                // Вкладка: ОБЩЕЕ
                // ────────────────────────────────────────────────
                ->page('Общее', function ($page) {
                    $page->add(Field::make('shipping.add_to_order_enabled', 'checkbox')
                        ->label('Добавлять стоимость доставки к заказу')
                        ->hint('Если выключено, стоимость доставки не рассчитывается и не добавляется к сумме заказа.')
                        ->default(false)
                        ->regionable(true)
                        ->cast('bool')
                        ->tab('Основное')
                    );

                    $page->add(Field::make('shipping.free_enabled', 'checkbox')
                        ->label('Бесплатная доставка')
                        ->hint('Активировать бесплатную доставку от определенной суммы заказа?')
                        ->default(false)
                        ->regionable(true)
                        ->cast('bool')
                        ->tab('Бесплатная доставка')
                    );

                    $page->add(Field::make('shipping.free_min_price', 'number')
                        ->label('Сумма заказа')
                        ->hint('Сумма заказа необходимая для активации бесплатной доставки в валюте страны.')
                        ->regionable(true)
                        ->cast('float')
                        ->tab('Бесплатная доставка')
                    );

                    $page->add(Field::make('shipping.free_include_promocode', 'checkbox')
                        ->label('Учитывать скидку по промокоду')
                        ->hint('Высчитывать возможность бесплатной доствки с учетом скидки по промокоду.')
                        ->cast('bool')
                        ->tab('Бесплатная доставка')
                    );

                    $page->add(Field::make('shipping.free_include_bonuses', 'checkbox')
                        ->label('Учитывать оплату бонусами')
                        ->hint('Высчитывать возможность бесплатной доствки с учетом оплаты заказа бонусами.')
                        ->cast('bool')
                        ->tab('Бесплатная доставка')
                    );

                    $page->add(Field::make('shipping.free_include_personal_discount', 'checkbox')
                        ->label('Учитывать персональную скидку')
                        ->hint('Высчитывать возможность бесплатной доствки с учетом персональной скидки.')
                        ->cast('bool')
                        ->tab('Бесплатная доставка')
                    );

                    $deliveries = \Settings::get('dress.delivery.methods', []);

                    $result_name_type_label = array_reduce($deliveries, function ($carry, $item) {
                        $key = $item['name'] . '_' . $item['type'];
                        $carry[$key] = $item['label'];
                        return $carry;
                    }, []);

                    $page->add(Field::make("shipping.methods", 'select2_from_array')
                        ->label('Способы доставки')
                        ->options($result_name_type_label)
                        ->allows_multiple(true)
                        ->cast('array')
                        ->hint('Выберите способы доставки доступные в стране.')
                        ->regionable(true)
                        ->tab('Основное')
                    );
                })

                // ────────────────────────────────────────────────
                // Вкладка: НАЛОГИ И НДС
                // ────────────────────────────────────────────────
                ->page('Настройки Zasilkovna', function ($page) {
                    // Налоги и НДС
                    $page->add(Field::make('shipping.zasilkovna.vat_rate', 'number')
                        ->label('Ставка НДС, %')
                        ->default(21)
                        ->cast('float')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.vat_included', 'checkbox')
                        ->label('Цены содержат НДС')
                        ->default(false)
                        ->cast('bool')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.vat_mode', 'select_from_array')
                        ->label('Режим применения НДС')
                        ->options([
                            'domestic' => 'Чешская ставка (Domestic)',
                            'destination' => 'Ставка страны назначения',
                            'oss' => 'OSS / One Stop Shop режим',
                        ])
                        ->default('destination')
                        ->cast('string')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    // Наложенный платёж (COD)
                    $page->add(Field::make('shipping.zasilkovna.cod.enabled', 'checkbox')
                        ->label('Разрешить наложенный платёж')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.cod.surcharge_fixed', 'number')
                        ->label('Фиксированная доплата за наложенный платёж')
                        ->suffix('CZK / EUR')
                        ->default(21)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.cod.surcharge_percent', 'number')
                        ->label('Процент от суммы заказа (%)')
                        ->default(0)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.cod.max_amount', 'number')
                        ->label('Максимальная сумма COD (CZK)')
                        ->default(20000)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.cod.allowed_for', 'select2_from_array')
                        ->label('Разрешённые типы доставки для COD')
                        ->options([
                            'pickup' => 'Пункт выдачи',
                            'home'   => 'Доставка на дом',
                            'box'    => 'Z-Box / Parcel locker',
                        ])
                        ->allows_multiple(true)
                        ->default(['pickup','home'])
                        ->cast('array')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    // Тарифы
                    $page->add(Field::make('shipping.zasilkovna.currency', 'select_from_array')
                        ->label('Валюта тарифов')
                        ->options([
                            'CZK' => 'CZK',
                            'EUR' => 'EUR',
                        ])
                        ->default('CZK')
                        ->cast('string')
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.pickup_rates', 'repeatable_pure')
                        ->label('Тарифы на пункты выдачи')
                        ->fields([
                            ['name' => 'max_weight_g', 'type' => 'number', 'label' => 'Макс. вес (г)'],
                            ['name' => 'price', 'type' => 'number', 'label' => 'Цена'],
                        ])
                        ->default([
                            ['max_weight_g' => 5000, 'price' => 62],
                            ['max_weight_g' => 15000, 'price' => 120],
                        ])
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.zasilkovna.home_rates', 'repeatable_pure')
                        ->label('Тарифы на доставку домой')
                        ->fields([
                            ['name' => 'max_weight_g', 'type' => 'number', 'label' => 'Макс. вес (г)'],
                            ['name' => 'price', 'type' => 'number', 'label' => 'Цена'],
                        ])
                        ->default([
                            ['max_weight_g' => 5000, 'price' => 89],
                            ['max_weight_g' => 15000, 'price' => 130],
                        ])
                        ->tab('Тарифы')
                        ->regionable(true));
                })

                // ────────────────────────────────────────────────
                // Вкладка: Новая Почта
                // ────────────────────────────────────────────────
                ->page('Настройки Нова пошта', function ($page) {
                    // Налоги и НДС (в Украине обычно 20%)
                    $page->add(Field::make('shipping.novaposhta.vat_rate', 'number')
                        ->label('Ставка НДС, %')
                        ->default(20)
                        ->cast('float')
                        ->tab('Налоги и НДС'));

                    $page->add(Field::make('shipping.novaposhta.vat_included', 'checkbox')
                        ->label('Цены тарифов содержат НДС')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Налоги и НДС'));

                    // Наложенный платёж (COD)
                    $page->add(Field::make('shipping.novaposhta.cod.enabled', 'checkbox')
                        ->label('Разрешить наложенный платёж (COD)')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Наложенный платёж (COD)'));

                    $page->add(Field::make('shipping.novaposhta.cod.surcharge_fixed', 'number')
                        ->label('Фиксированная доплата за COD')
                        ->suffix('UAH')
                        ->default(20)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)'));

                    $page->add(Field::make('shipping.novaposhta.cod.surcharge_percent', 'number')
                        ->label('Процент от суммы заказа (%)')
                        ->default(0)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)'));

                    $page->add(Field::make('shipping.novaposhta.cod.max_amount', 'number')
                        ->label('Максимальная сумма COD (UAH)')
                        ->default(30000)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)'));

                    $page->add(Field::make('shipping.novaposhta.cod.allowed_for', 'select2_from_array')
                        ->label('Разрешённые типы доставки для COD')
                        ->options([
                            'branch'  => 'Отделение',
                            'locker'  => 'Почтомат',
                            'courier' => 'Курьером',
                        ])
                        ->allows_multiple(true)
                        ->default(['branch','courier','locker'])
                        ->cast('array')
                        ->tab('Наложенный платёж (COD)'));

                    // Страховка / Оценочная стоимость
                    $page->add(Field::make('shipping.novaposhta.insurance.enabled', 'checkbox')
                        ->label('Страховка (оценочная стоимость)')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Страховка/Оценочная'));

                    $page->add(Field::make('shipping.novaposhta.insurance.percent', 'number')
                        ->label('Процент от объявленной стоимости (%)')
                        ->default(0.5)
                        ->cast('float')
                        ->tab('Страховка/Оценочная'));

                    $page->add(Field::make('shipping.novaposhta.insurance.min_amount', 'number')
                        ->label('Минимальный платёж за страховку')
                        ->suffix('UAH')
                        ->default(5)
                        ->cast('float')
                        ->tab('Страховка/Оценочная'));

                    // Габариты и вес (для расчёта по объёмному весу)
                    $page->add(Field::make('shipping.novaposhta.dimensions.use_volumetric', 'checkbox')
                        ->label('Учитывать объёмный вес')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Габариты и вес'));

                    $page->add(Field::make('shipping.novaposhta.dimensions.volumetric_divisor', 'number')
                        ->label('Делитель для объёмного веса (L×W×H / делитель)')
                        ->suffix('см³/кг')
                        ->default(4000)
                        ->cast('float')
                        ->tab('Габариты и вес'));

                    $page->add(Field::make('shipping.novaposhta.dimensions.min_billable_weight_g', 'number')
                        ->label('Минимальный учитываемый вес (г)')
                        ->default(1000)
                        ->cast('int')
                        ->tab('Габариты и вес'));

                    // Валюта и тарифы
                    $page->add(Field::make('shipping.novaposhta.currency', 'select_from_array')
                        ->label('Валюта тарифов')
                        ->options([
                            'UAH' => 'UAH',
                        ])
                        ->default('UAH')
                        ->cast('string')
                        ->tab('Тарифы'));

                    // Отделение
                    $page->add(Field::make('shipping.novaposhta.branch_rates', 'repeatable_pure')
                        ->label('Тарифы: доставка на отделение')
                        ->fields([
                            ['name' => 'max_weight_g', 'type' => 'number', 'label' => 'Макс. вес (г)'],
                            ['name' => 'price',        'type' => 'number', 'label' => 'Цена'],
                        ])
                        ->default([
                            ['max_weight_g' => 1000, 'price' => 75],
                            ['max_weight_g' => 5000, 'price' => 95],
                            ['max_weight_g' => 10000, 'price' => 140],
                            ['max_weight_g' => 20000, 'price' => 220],
                        ])
                        ->tab('Тарифы'));

                    // Почтомат
                    $page->add(Field::make('shipping.novaposhta.locker_rates', 'repeatable_pure')
                        ->label('Тарифы: почтомат')
                        ->fields([
                            ['name' => 'size',         'type' => 'select_from_array', 'label' => 'Размер ячейки', 'options' => [
                                'S' => 'S', 'M' => 'M', 'L' => 'L'
                            ]],
                            ['name' => 'max_weight_g', 'type' => 'number', 'label' => 'Макс. вес (г)'],
                            ['name' => 'price',        'type' => 'number', 'label' => 'Цена'],
                        ])
                        ->default([
                            ['size' => 'S', 'max_weight_g' => 1000,  'price' => 70],
                            ['size' => 'M', 'max_weight_g' => 5000,  'price' => 90],
                            ['size' => 'L', 'max_weight_g' => 10000, 'price' => 120],
                        ])
                        ->tab('Тарифы'));

                    // Курьер
                    $page->add(Field::make('shipping.novaposhta.courier_rates', 'repeatable_pure')
                        ->label('Тарифы: курьером до двери')
                        ->fields([
                            ['name' => 'max_weight_g', 'type' => 'number', 'label' => 'Макс. вес (г)'],
                            ['name' => 'price',        'type' => 'number', 'label' => 'Цена'],
                        ])
                        ->default([
                            ['max_weight_g' => 1000,  'price' => 95],
                            ['max_weight_g' => 5000,  'price' => 130],
                            ['max_weight_g' => 10000, 'price' => 180],
                            ['max_weight_g' => 20000, 'price' => 280],
                        ])
                        ->tab('Тарифы'));
                });

        });
    }
}
