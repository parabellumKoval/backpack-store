<?php

namespace Backpack\Store\app\Services\Region\Multi;

use Backpack\Store\app\Contracts\SupplierFilter as Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as Qb;

class SupplierFilter implements Contract
{
    public function spOk(?string $country = null): Qb
    {
        $country = $country ?: \Store::context()->country;
        return DB::table('ak_supplier_product as sp')
            ->join('ak_supplier_country as sc', function($j) use ($country){
                $j->on('sc.supplier_id','=','sp.supplier_id')
                  ->where('sc.country_code','=',$country);
            })
            ->where('sp.is_active', 1)
            ->distinct()
            ->select('sp.product_id');
    }
}
