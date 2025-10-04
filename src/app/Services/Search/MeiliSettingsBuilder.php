<?php

namespace Backpack\Store\app\Services\Search;

class MeiliSettingsBuilder
{
    public static function build(): array
    {
        
        $settings = \Settings::get('dress.search.meilisearch.settings', []);

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
