<?php

namespace Backpack\Store\app\Contracts;

use Illuminate\Database\Query\Builder as Qb;

interface SupplierFilter {
    /** DISTINCT product_id, где есть активный supplier (в стране, если multi) */
    public function spOk(?string $country = null): Qb;
}
