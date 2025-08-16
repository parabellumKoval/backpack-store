<?php

namespace Backpack\Store\app\Services\Region\Single;

use Backpack\Store\app\Contracts\SupplierFilter as Contract;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class SupplierFilter implements Contract {
    public function existsFor(EloquentBuilder|QueryBuilder $outer, string $productIdColumn, ?string $country = null): EloquentBuilder|QueryBuilder {
        return $outer->whereExists(function ($sub) use ($productIdColumn) {
            $sub->select(DB::raw(1))
                ->from('ak_supplier_product as sp')
                ->whereColumn('sp.product_id', $productIdColumn)
                ->where('sp.is_active', 1);
        });
    }
}
