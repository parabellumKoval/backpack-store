<?php

namespace Backpack\Store\app\Services\Region\Multi;

use Backpack\Store\app\Contracts\SupplierFilter as Contract;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

use Illuminate\Support\Facades\DB;

class SupplierFilter implements Contract {
    public function existsFor(EloquentBuilder|QueryBuilder $outer, string $productIdColumn, ?string $country = null): EloquentBuilder|QueryBuilder {
        $country = $country ?: \Store::context()->country;
        return $outer->whereExists(function ($sub) use ($productIdColumn, $country) {
            $sub->select(DB::raw(1))
                ->from('ak_supplier_product as sp')
                ->whereColumn('sp.product_id', $productIdColumn)
                ->where('sp.is_active', 1)
                ->whereIn('sp.supplier_id', function ($sq) use ($country) {
                    $sq->select('supplier_id')->from('ak_supplier_country')->where('country_code', $country);
                });
        });
    }
}
