<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class ModulesSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('store-modules', function ($group) {
            $group->title('Настройки модулей')->icon('la la-store')

                ->page('Поиск', function ($page) {
                    $page->add(Field::make('bs.search.normalizer.strip_accents.enabled', 'checkbox')
                        ->label('Очищать диакритику')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Будет нормализовать символы с диакритическими знаками. Например "čaj" -> "caj"')
                        ->tab('Нормализация')
                    );

                    $page->add(Field::make('bs.search.normalizer.keyboard_fix.enabled', 'checkbox')
                        ->label('Исправлять неправильную раскладку')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Будет распознавать текст даже если он введен на неверной раскладке клавиатуры. Например "xfq" -> "чай"')
                        ->tab('Нормализация')
                    );

                    $page->add(Field::make('bs.search.normalizer.transliteration.enabled', 'checkbox')
                        ->label('Автоматическая транслитерация')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Автоматическая транслитерация кириллицы в латиницу. Например "chai" -> "чай"')
                        ->tab('Нормализация')
                    );

                    $page->add(Field::make('bs.search.multilanguage', 'checkbox')
                        ->label('Мультиязычный поиск')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Будет искать на всех языках во всех версиях сайта. Иначе будет искать только на основной локали конкретного региона.')
                        ->tab('Алгоритм поиска')
                    );

                    $page->add(Field::make('bs.search.typo.tolerance', 'select_from_array')
                        ->label('Толерантность к ошибкам')
                        ->options([
                            'off' => 'Строгое совпадение',
                            'low' => 'Низкая',
                            'medium' => 'Средняя', 
                            'high' => 'Высокая'
                        ])
                        ->cast('string')
                        ->allows_null(false)
                        ->hint('Какой уровень ошибок допустим при поиске')
                        ->tab('Алгоритм поиска')
                    );

                    // $page->add(Field::make('store.products.modifications.mode', 'select_from_array')
                    //     ->label('Режим модификаций')
                    //     ->options(['vertical' => 'Вертикальные', 'flat' => 'Плоские'])
                    //     ->default('vertical')
                    //     ->cast('string')
                    //     ->tab('Алгоритм поиска')
                    // );
                })

                // Вкладка "Оплата"
                ->page('Cross/up sell', function ($page) {
                    // пример кастомного поля (в пакете есть field view: resources/views/fields/toggle.blade.php)
                    $page->add(Field::make('dress.upsell.enabled', 'checkbox')
                        ->label('Включить модуль')
                        ->default(false)
                        ->cast('bool')
                        ->tab('Основное')
                    );
                    
                    $page->add(Field::make('dress.upsell.placements', 'select_from_array')
                        ->label('Расположение товаров')
                        ->options(config('dress.upsell.placements', []))
                        ->cast('array')
                        ->allows_null(true)
                        ->allows_multiple(true)
                        ->hint('Выберите из списка страницы на которых будут выводиться списки товаров Cross/up sell')
                        ->tab('Основное')
                    );

                    
                    $page->add(Field::make('dress.upsell.priority', 'select_and_order')
                        ->label('Общие алгоритмы')
                        ->options(config('dress.upsell.sources', []))
                        ->cast('array')
                        ->hint('Общие правила будут применены, если конкретные способы для Cross/up sell не заданы. Способы расположите в порядке по приоритетам.')
                        ->tab('Алгоритмы генерации списков')
                    );

                    $page->add(Field::make('dress.upsell.priority_per_kind.up', 'select_and_order')
                        ->label('Алгоритмы для списков Up sell')
                        ->options(config('dress.upsell.sources', []))
                        ->cast('array')
                        ->hint('Выберите какими способами будут заполняться списки Up sell.')
                        ->tab('Алгоритмы генерации списков')
                    );

                    $page->add(Field::make('dress.upsell.priority_per_kind.cross', 'select_and_order')
                        ->label('Алгоритмы для списков Cross sell')
                        ->options(config('dress.upsell.sources', []))
                        ->cast('array')
                        ->hint('Выберите какими способами будут заполняться списки Cross sell.')
                        ->tab('Алгоритмы генерации списков')
                    );
                });
        });
    }
}
