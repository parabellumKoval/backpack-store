<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class StoreSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('store', function ($group) {
            $group->title('Магазин')->icon('la la-store')

                // Вкладка "Общее"
                ->page('Общее', function ($page) {
                    // $page->add(Field::make('multistore.enabled', 'checkbox')
                    //     ->label('Включить мультистор')
                    //     ->default(false)
                    //     ->cast('bool')
                    //     ->tab('Основное')
                    // );

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
                    // пример кастомного поля (в пакете есть field view: resources/views/fields/toggle.blade.php)
                    $page->add(Field::make('store.payment.cod_enabled', 'toggle')
                        ->label('Наложенный платеж')
                        ->default(true)
                        ->cast('bool')
                    );
                });
        });
    }
}
