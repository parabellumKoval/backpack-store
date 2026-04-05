<?php

namespace Backpack\Store\app\Services\ProductLists;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class AvailabilityGate
{

    public function allowedIds(array $productIds, string $country, string $storefront, array $options = []): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = DB::table('ak_catalog')
            ->where('country_code', $country)
            ->where('storefront_code', $storefront)
            ->whereIn('product_id', $productIds);

        $query->where('is_available', 1);

        if (!empty($options['only_in_stock'])) {
            $query->where('in_stock', '>', 0);
        }

        if (isset($options['stock_min'])) {
            $query->where('in_stock', '>=', (int) $options['stock_min']);
        }

        return $query->pluck('product_id')->toArray();
    }

    public function applyQueryFilter(Builder $query, string $country, string $storefront, array $options = []): Builder
    {
        $query
            ->where('c.country_code', $country)
            ->where('c.storefront_code', $storefront)
            ->where('c.is_available', 1);

        if (!empty($options['only_in_stock'])) {
            $query->where('c.in_stock', '>', 0);
        }

        if (array_key_exists('stock_min', $options)) {
            $query->where('c.in_stock', '>=', (int) $options['stock_min']);
        }

        return $query;
    }

}
