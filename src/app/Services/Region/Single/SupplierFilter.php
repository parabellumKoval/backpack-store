<?php

namespace Backpack\Store\app\Services\Region\Single;

use Backpack\Store\app\Contracts\SupplierFilter as Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as Qb;

class SupplierFilter implements SupplierFilter
{
    public function spOk(?string $country = null): Qb
    {
        return DB::table('ak_supplier_product as sp')
            ->where('sp.is_active', 1)
            ->distinct()
            ->select('sp.product_id');
    }
}
