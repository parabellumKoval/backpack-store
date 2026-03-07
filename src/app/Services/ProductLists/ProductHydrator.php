<?php

namespace Backpack\Store\app\Services\ProductLists;

use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Http\Resources\ProductCollection;
use Backpack\Store\app\Models\Catalog;

class ProductHydrator
{
    use \Backpack\Store\app\Traits\Resources;

    public function __construct() {
      self::resources_init();
    }

    public function fetchCatalogRows(array $ids, string $country): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        return Catalog::query()
            ->where('country_code', $country)
            ->whereIn('product_id', $ids)
            ->get()
            ->keyBy('product_id')
            ->all();
    }

    public function hydrate(array $ids, string $country, string $lang) {
        if (empty($ids)) {
            return [];
        }
        $rowsById = $this->fetchCatalogRows($ids, $country);

        // Preserve sorting resolved by ListEngine.
        $orderedRows = [];
        foreach ($ids as $id) {
            if (!isset($rowsById[$id])) {
                continue;
            }
            $orderedRows[] = $rowsById[$id];
        }

        return self::$resources['product']['small']::collection($orderedRows);
    }


    // public function fetchCatalogRows(array $ids, string $country): array
    // {
    //     if (empty($ids)) {
    //         return [];
    //     }

    //     $rows = DB::table('ak_catalog')
    //         ->where('country_code', $country)
    //         ->whereIn('product_id', $ids)
    //         ->get()
    //         ->keyBy('product_id');

    //     return $rows->all();
    // }

    // public function hydrate(array $ids, string $country, string $lang)
    // {
    //     if (empty($ids)) {
    //         return [];
    //     }

    //     $rows = $this->fetchCatalogRows($ids, $country);

    //     $fallback = config('app.fallback_locale');

    //     $result = [];
    //     foreach ($ids as $id) {
    //         if (!isset($rows[$id])) {
    //             continue;
    //         }
    //         $row = (array) $rows[$id];

    //         $result[] = [
    //             'id' => $id,
    //             'name' => $this->translate($row['name'] ?? null, $lang, $fallback),
    //             'short_name' => $this->translate($row['short_name'] ?? null, $lang, $fallback),
    //             'slug' => $row['slug'] ?? null,
    //             'price' => $this->toFloat($row['price'] ?? null),
    //             'old_price' => $this->toFloat($row['old_price'] ?? null),
    //             'inStock' => isset($row['in_stock']) ? (int) $row['in_stock'] : null,
    //             'images' => $this->decodeJson($row['images'] ?? null),
    //             'extras' => $this->decodeJson($row['extras'] ?? null),
    //         ];
    //     }

    //     return $result;
    // }

    // protected function translate(mixed $value, string $lang, ?string $fallback): ?string
    // {
    //     if ($value === null) {
    //         return null;
    //     }

    //     if (is_array($value)) {
    //         return $value[$lang] ?? ($fallback ? ($value[$fallback] ?? reset($value)) : reset($value));
    //     }

    //     if (is_string($value) && str_starts_with($value, '{')) {
    //         $decoded = json_decode($value, true);
    //         if (is_array($decoded)) {
    //             return $decoded[$lang] ?? ($fallback ? ($decoded[$fallback] ?? reset($decoded)) : reset($decoded));
    //         }
    //     }

    //     return is_string($value) ? $value : null;
    // }

    // protected function decodeJson(mixed $value): mixed
    // {
    //     if ($value === null || $value === '') {
    //         return null;
    //     }
    //     if (is_array($value)) {
    //         return $value;
    //     }
    //     if (is_string($value)) {
    //         $decoded = json_decode($value, true);
    //         return $decoded ?? $value;
    //     }
    //     return $value;
    // }

    // protected function toFloat(mixed $value): ?float
    // {
    //     if ($value === null) {
    //         return null;
    //     }
    //     if (is_numeric($value)) {
    //         return (float) $value;
    //     }
    //     return null;
    // }
}
