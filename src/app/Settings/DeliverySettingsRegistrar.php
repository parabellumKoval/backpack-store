<?php

namespace Backpack\Store\app\Settings;

use Backpack\Store\app\Support\CheckoutMethodCatalog;
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

                    $deliveries = CheckoutMethodCatalog::deliveryMethods();

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

                ->page('Настройки Messenger.cz', function ($page) {
                    $page->add(Field::make('shipping.messenger.vat_rate', 'number')
                        ->label('Ставка НДС, %')
                        ->default(21)
                        ->cast('float')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.vat_included', 'checkbox')
                        ->label('Цены содержат НДС')
                        ->default(false)
                        ->cast('bool')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.fuel_surcharge_percent', 'number')
                        ->label('Топливная надбавка, %')
                        ->default(0)
                        ->cast('float')
                        ->tab('Налоги и НДС')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.currency', 'select_from_array')
                        ->label('Валюта тарифов')
                        ->options([
                            'CZK' => 'CZK',
                        ])
                        ->default('CZK')
                        ->cast('string')
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.shipment_weight_g', 'number')
                        ->label('Вес одного отправления (г)')
                        ->default(10000)
                        ->cast('int')
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.max_dimension_cm', 'number')
                        ->label('Максимальный размер одной стороны (см)')
                        ->default(40)
                        ->cast('int')
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.address_rates', 'repeatable_pure')
                        ->label('Тарифы на адресную доставку')
                        ->fields([
                            ['name' => 'shipments_count', 'type' => 'number', 'label' => 'Количество отправлений'],
                            ['name' => 'price', 'type' => 'number', 'label' => 'Цена без НДС'],
                        ])
                        ->default([
                            ['shipments_count' => 1, 'price' => 145],
                            ['shipments_count' => 2, 'price' => 220],
                            ['shipments_count' => 3, 'price' => 295],
                            ['shipments_count' => 4, 'price' => 370],
                            ['shipments_count' => 5, 'price' => 445],
                            ['shipments_count' => 6, 'price' => 520],
                            ['shipments_count' => 7, 'price' => 595],
                            ['shipments_count' => 8, 'price' => 670],
                            ['shipments_count' => 9, 'price' => 745],
                            ['shipments_count' => 10, 'price' => 820],
                            ['shipments_count' => 11, 'price' => 895],
                            ['shipments_count' => 12, 'price' => 970],
                            ['shipments_count' => 13, 'price' => 1045],
                            ['shipments_count' => 14, 'price' => 1120],
                            ['shipments_count' => 15, 'price' => 1195],
                            ['shipments_count' => 16, 'price' => 1270],
                            ['shipments_count' => 17, 'price' => 1345],
                            ['shipments_count' => 18, 'price' => 1420],
                            ['shipments_count' => 19, 'price' => 1495],
                            ['shipments_count' => 20, 'price' => 1570],
                        ])
                        ->tab('Тарифы')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.cod.enabled', 'checkbox')
                        ->label('Разрешить наложенный платёж')
                        ->default(true)
                        ->cast('bool')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.cod.cash_fee', 'number')
                        ->label('Доплата COD наличными')
                        ->suffix('CZK')
                        ->default(30)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.cod.card_fee_fixed', 'number')
                        ->label('Доплата COD картой: фиксированная часть')
                        ->suffix('CZK')
                        ->default(30)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
                        ->regionable(true));

                    $page->add(Field::make('shipping.messenger.cod.card_fee_percent', 'number')
                        ->label('Доплата COD картой: процент от суммы заказа')
                        ->default(1.25)
                        ->cast('float')
                        ->tab('Наложенный платёж (COD)')
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
