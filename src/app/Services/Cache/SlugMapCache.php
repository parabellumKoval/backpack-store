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
        $slug = $this->normalizeSlug($slug);
        if (!$slug) return null;

        $map = $this->getCatMap();

        if (isset($map[$slug])) {
            return (int) $map[$slug];
        }

        $resolved = Category::query()
            ->where('slug', $slug)
            ->value('id');

        if (!$resolved) {
            return null;
        }

        $map[$slug] = (int) $resolved;
        Cache::put($this->catKey, $map, $this->ttl);

        return (int) $resolved;
    }

    public function brandIdBySlug(?string $slug): ?int
    {
        $slug = $this->normalizeSlug($slug);
        if (!$slug) return null;

        $map = $this->getBrandMap();

        if (isset($map[$slug])) {
            return (int) $map[$slug];
        }

        $resolved = Brand::query()
            ->where('slug', $slug)
            ->value('id');

        if (!$resolved) {
            return null;
        }

        $map[$slug] = (int) $resolved;
        Cache::put($this->brandKey, $map, $this->ttl);

        return (int) $resolved;
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

    protected function normalizeSlug(?string $slug): ?string
    {
        $normalized = is_string($slug) ? trim($slug) : '';

        return $normalized !== '' ? $normalized : null;
    }
}
