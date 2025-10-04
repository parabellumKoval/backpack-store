<?php

namespace Backpack\Store\app\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;

class SlugMapCache
{
    protected string $catKey   = 'store:map:category:slug2id';
    protected string $brandKey = 'store:map:brand:slug2id';
    protected int $ttl; // секунды

    public function __construct()
    {
        $this->ttl = (int) \Settings::get('dress.store.slug_map_ttl', 3600);
    }

    /** ====== public API ====== */

    public function categoryIdBySlug(?string $slug): ?int
    {
        if (!$slug) return null;
        $map = $this->getCatMap();
        return isset($map[$slug]) ? (int) $map[$slug] : null;
    }

    public function brandIdBySlug(?string $slug): ?int
    {
        if (!$slug) return null;
        $map = $this->getBrandMap();
        return isset($map[$slug]) ? (int) $map[$slug] : null;
    }

    /** Принудительно перестроить обе карты */
    public function refreshAll(): void
    {
        $this->refreshCategories();
        $this->refreshBrands();
    }

    public function refreshCategories(): void
    {
        $map = Category::query()->pluck('id', 'slug')->all(); // [slug => id]
        Cache::put($this->catKey, $map, $this->ttl);
    }

    public function refreshBrands(): void
    {
        $map = Brand::query()->pluck('id', 'slug')->all(); // [slug => id]
        Cache::put($this->brandKey, $map, $this->ttl);
    }

    /** ====== internal ====== */

    protected function getCatMap(): array
    {
        return Cache::remember($this->catKey, $this->ttl, function () {
            return Category::query()->pluck('id', 'slug')->all();
        });
    }

    protected function getBrandMap(): array
    {
        return Cache::remember($this->brandKey, $this->ttl, function () {
            return Brand::query()->pluck('id', 'slug')->all();
        });
    }
}
