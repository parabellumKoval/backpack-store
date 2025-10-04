<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class SearchSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('search', function ($group) {
            $group->title('Настройки поиска')->icon('la la-store')

                ->page('Общее', function ($page) {
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
                });
        });
    }
}
