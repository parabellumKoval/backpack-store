<?php

namespace Backpack\Store\app\Services\Resolvers;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

class SupplierProductResolver
{
    /**
     * Вернёт лучший SupplierProduct для товара с учётом режима,
     * т.к. Product::sp() уже применяет нужные фильтры (single/multi).
     */
    public function current(Product $product, ?string $countryCode = null): ?SupplierProduct
    {
        $relation = $product->sp();
        return $this->pick($relation);
    }

    /**
     * Универсальный выбор из связи/билдера по приоритетам.
     * При необходимости порядок можно переопределить в $order.
     */
    public function pick(HasMany|Builder $source, array $order = []): ?SupplierProduct
    {
        /** @var Builder $q */
        $q = $source instanceof HasMany ? $source->getQuery() : $source;

        return $q
          ->where('is_active', 1)
          // reduce integer value to boolean
          ->orderByRaw('IF(in_stock > ?, ?, ?) DESC', [0, 1, 0])
          ->orderBy('price')
          ->first();
    }
}
