<?php

namespace Backpack\Store\app\Services\Search;

class MeiliSettingsBuilder
{
    public static function build(): array
    {
        $default = config('dress.search.meilisearch.settings', []);
        // $settings = \Settings::get('dress.search.meilisearch.settings', $default);
        $settings = config('dress.search.meilisearch.settings', []);

        // $settings = array_replace_recursive($default, $settings);
        $settings = self::ensureFilterableAttributes($settings);
        $settings = self::expandSearchableAttributes($settings);

        $settings = array_filter([
            'searchableAttributes' => $settings['searchableAttributes'] ?? null,
            'filterableAttributes' => $settings['filterableAttributes'] ?? null,
            'sortableAttributes'   => $settings['sortableAttributes'] ?? null,
            'distinctAttribute'    => $settings['distinctAttribute'] ?? null,
            'rankingRules'         => $settings['rankingRules'] ?? null,
            'stopWords'            => $settings['stopWords'] ?? null,
            'synonyms'             => $settings['synonyms'] ?? null,
            'typoTolerance'        => self::getToleranceLevel() ?? null,
            'pagination'           => $settings['pagination'] ?? null,
        ]);

        return $settings;
    }

    protected static function ensureFilterableAttributes(array $settings): array
    {
        $filterable = $settings['filterableAttributes'] ?? [];
        $modelFilterable = [];

        $productModel = config('dress.search.models.products');
        if (is_string($productModel)
            && class_exists($productModel)
            && method_exists($productModel, 'filterableAttributes')) {
            $modelFilterable = $productModel::filterableAttributes();
        }

        $required = ['in_stock', 'country_code', 'storefront_code'];
        $filterable = array_unique(array_merge($filterable, $modelFilterable, $required));

        $settings['filterableAttributes'] = array_values($filterable);

        return $settings;
    }

    protected static function expandSearchableAttributes(array $settings): array
    {
        $searchableAttrs = $settings['searchableAttributes'] ?? [];
        
        if (empty($searchableAttrs)) {
            return $settings;
        }

        $locales = array_keys(\Settings::get('backpack.crud.locales', []));
        $productModel = config('dress.search.models.products');
        
        // Получаем переводимые атрибуты из модели
        $translatableFields = [];
        if (is_string($productModel) && class_exists($productModel) && method_exists($productModel, 'searchableTranslatableAttributes')) {
            $translatableMap = $productModel::searchableTranslatableAttributes();
            foreach ($translatableMap as $k => $v) {
                $translatableFields[] = is_int($k) ? $v : $k;
            }
            
        }

        $expandedAttrs = [];
        
        foreach ($searchableAttrs as $attr) {
            if (in_array($attr, $translatableFields)) {
                // Добавляем поля с суффиксами локалей
                foreach ($locales as $locale) {
                    $expandedAttrs[] = "{$attr}_{$locale}";
                }
            } else {
                // Обычное поле без суффиксов
                $expandedAttrs[] = $attr;
            }
        }

        $settings['searchableAttributes'] = array_values(array_unique($expandedAttrs));

        return $settings;
    }

    private static function getToleranceLevel() {
        $toleranceLevel = \Settings::get('dress.search.typo.tolerance', 'medium');
        $typo = match ($toleranceLevel) {
            'off'  => ['enabled' => false],
            'low'  => ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo'=>7,'twoTypos'=>255]],
            'high' => ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo'=>3,'twoTypos'=>5]],
            default=> ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo'=>5,'twoTypos'=>9]],
        };

        return $typo;
    }
}
