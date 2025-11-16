<?php

namespace Backpack\Store\app\Models\Traits;

use Backpack\Store\app\Models\Category;

trait SearchCatalogTrait {  

    use \Laravel\Scout\Searchable;

    public function scoutShouldBeSearchable(): bool
    {
        return \Settings::get('dress.search.enabled', false)
            && \Settings::get('dress.search.driver', 'meilisearch') === 'meilisearch'
            && (int) $this->is_available === 1;
    }

    public function searchableAs(): string
    {
        $base = self::searchIndexBase();

        // $loc  = app()->getLocale();
        $this->country_code = \Store::context()->country;

        return $base . '_' . $this->country_code;
    }

    public function toSearchableArray(): array
    {
        $base = [
            // 'category_ids' => $this->category_ids ?? [],
            // 'created_at'   => optional($this->created_at)?->toAtomString(),
        ];

        return parent::toSearchableArray();
    }


    public function resolveCategoryNamesArray(string $locale): array
    {
        $v = $this->categories()
            ->map(fn (Category $c) => $c->getTranslation('name', $locale))
            ->filter()
            ->values()
            ->all();

        return $v;
    }

    public static function searchIndexBase(): string { return 'products'; }
    public static function searchableAttributes(): array { return ['id', 'product_id', 'group_id', 'country_code', 'price', 'old_price', 'in_stock', 'brandName']; }
    public static function searchableTranslatableAttributes(): array { return ['name', 'short_name',  'categories' => 'resolveCategoryNamesArray']; }
    // public static function searchableTranslatableAttributes(): array { return ['name','brand','category','attrs_text']; }
    public static function filterableAttributes(): array { return ['in_stock','country_code','category_ids','brand_id']; }
    public static function sortableAttributes(): array { return ['price','popularity','created_at']; }
    public static function distinctAttribute(): ?string { return 'group_id'; }
    public static function searchRankingRules(): array {
        return \Settings::get('dress.search.ranking.rules', ['words','typo','proximity','attribute','sort','exactness','desc(popularity)']);
    }
}