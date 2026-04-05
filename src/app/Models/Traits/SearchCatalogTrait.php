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
        $storefront = \Store::normalizeStorefrontCode($this->storefront_code ?? \Store::context()->storefront ?? \Store::storefront()) ?? \Store::defaultStorefront();
        $this->storefront_code = $storefront;

        return $base . '_' . $this->country_code . '_' . $storefront;
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
    public static function searchableAttributes(): array { return ['id', 'product_id', 'group_id', 'country_code', 'storefront_code', 'price', 'old_price', 'in_stock', 'brandName']; }
    public static function searchableTranslatableAttributes(): array { return ['name', 'short_name',  'categories' => 'resolveCategoryNamesArray']; }
    // public static function searchableTranslatableAttributes(): array { return ['name','brand','category','attrs_text']; }
    public static function filterableAttributes(): array { return ['in_stock','country_code','storefront_code','category_ids','brand_id']; }
    public static function sortableAttributes(): array { return ['price','popularity','created_at']; }
    public static function distinctAttribute(): ?string { return null; }
    public static function searchRankingRules(): array {
        return \Settings::get('dress.search.ranking.rules', ['words','typo','proximity','attribute','sort','exactness','desc(popularity)']);
    }
}
