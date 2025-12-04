<?php

namespace Backpack\Store\app\Observers;

use Illuminate\Support\Facades\Log;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Services\Catalog\CatalogCacheService;

/**
 * Триггерит точечную пересборку кеша каталога при любых операциях с товаром:
 * - создание/изменение/восстановление товара;
 * - удаление товара;
 * - манипуляции с модификациями: если товар — вариант, дополнительно трогаем базовый;
 *   если товар — базовый, его изменения пересоберут групповые атрибуты.
 */
class CatalogObserver
{
    protected CatalogCacheService $catalogCacheService;

    public function __construct(CatalogCacheService $catalogCacheService)
    {
        $this->catalogCacheService = $catalogCacheService;
    }

    public function created(Product $product): void
    {
        $this->handle($product, 'created');
    }

    public function saved(Product $product): void
    {
        $this->handle($product, 'saved');
    }

    public function updated(Product $product): void
    {
        $this->handle($product, 'updated');
    }

    public function deleted(Product $product): void
    {
        $this->handle($product, 'deleted');
    }

    public function restored(Product $product): void
    {
        $this->handle($product, 'restored');
    }

    public function forceDeleted(Product $product): void
    {
        $this->handle($product, 'forceDeleted');
    }

    protected function handle(Product $product, string $event): void
    {
        try {
            // 1) Всегда пересобираем сам товар
            $this->catalogCacheService->syncProduct((int) $product->getKey());

            // 2) Если это ВАРИАНТ (есть parent_id) — тронем ещё и БАЗОВЫЙ товар (меняется «листьяность» группы)
            if (!empty($product->parent_id)) {
                $this->catalogCacheService->syncProduct((int) $product->parent_id);
            }

            // 3) Если это БАЗОВЫЙ — его изменения уже учтутся внутри (групповые атрибуты)
        } catch (\Throwable $e) {
            Log::warning("ProductCatalogObserver {$event} failed for product {$product->getKey()}: ".$e->getMessage());
        }
    }
}
