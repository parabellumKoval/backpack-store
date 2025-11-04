<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class StoreSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('store', function ($group){
            $group->title('Магазин')->icon('la la-store')

                // Вкладка "Общее"
                ->page('Общее', function ($page) {
                    $page->add(Field::make('store.products.modifications.enabled', 'checkbox')
                        ->label('Включить модификации')
                        ->default(false)
                        ->cast('bool')
                        ->tab('Основное')
                    );
                    $page->add(Field::make('store.products.modifications.mode', 'select_from_array')
                        ->label('Режим модификаций')
                        ->options(['vertical' => 'Вертикальные', 'flat' => 'Плоские'])
                        ->default('vertical')
                        ->cast('string')
                        ->tab('Расширенные')
                    );
                })

                // Вкладка "Оплата"
                ->page('Оплата', function ($page) {
                    $page->add(Field::make('store.payment.cod_enabled', 'toggle')
                        ->label('Наложенный платеж')
                        ->default(true)
                        ->cast('bool')
                    );
                })

                ->page('Доставка', function ($page) {
                    $page->add(Field::make('store.delivery.free_enabled', 'checkbox')
                        ->label('Бесплатная доставка')
                        ->hint('Активировать бесплатную доставку от определенной суммы заказа?')
                        ->default(false)
                        ->regionable(true)
                        ->cast('bool')
                    );

                    $page->add(Field::make('store.delivery.free_min_price', 'number')
                        ->label('Сумма заказа')
                        ->hint('Сумма заказа необходимая для активации бесплатной доставки в валюте страны.')
                        ->regionable(true)
                        ->cast('float')
                    );

                    $deliveries = [
                        [
                            'name' => 'novaposhta',
                            'type' => 'address',
                            'label' => 'Адресная доставка Новая Почта'
                        ],[
                            'name' => 'novaposhta',
                            'type' => 'warehouse',
                            'label' => 'Отделение/почтомат Новая Почта'
                        ],[
                            'name' => 'packeta',
                            'type' => 'address',
                            'label' => 'Адресная доставка Zasilkovna'
                        ],[
                            'name' => 'packeta',
                            'type' => 'warehouse',
                            'label' => 'Отделение/почтомат Zasilkovna'
                        ],[
                            'name' => 'default',
                            'type' => 'pickup',
                            'label' => 'Самовывоз'
                        ],[
                            'name' => 'default',
                            'type' => 'address',
                            'label' => 'Адресная доставка'
                        ]
                    ];

                    $result_name_type_label = array_reduce($deliveries, function ($carry, $item) {
                        $key = $item['name'] . '_' . $item['type'];
                        $carry[$key] = $item['label'];
                        return $carry;
                    }, []);

                    $page->add(Field::make("store.delivery.methods", 'select2_from_array')
                        ->label('Способы доставки')
                        ->options($result_name_type_label)
                        ->allows_multiple(true)
                        ->cast('array')
                        ->hint('Выберите способы доставки доступные в стране.')
                        ->regionable(true)
                    );

                    // $countries = \Store::countryOptions();
                    // foreach($deliveries as $delivery) {

                    //     $page->add(Field::make("store.delivery.{$delivery['name']}.{$delivery['type']}", 'select2_from_array')
                    //         ->label('Страны')
                    //         ->options($countries)
                    //         ->allows_multiple(true)
                    //         ->cast('string')
                    //         ->hint('В каких странах доступен данный способ доставки.')
                    //         ->tab($delivery['label'])
                    //     );
                    // }
                });
        });
    }
}
