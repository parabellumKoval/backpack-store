<?php

namespace Backpack\Store\app\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface SupplierFilter {
    /** Навешивает условие "существует активный склад/позиция для указанного product_id" */
    public function existsFor(EloquentBuilder|QueryBuilder $outer, string $productIdColumn, ?string $country = null): EloquentBuilder|QueryBuilder;
}
