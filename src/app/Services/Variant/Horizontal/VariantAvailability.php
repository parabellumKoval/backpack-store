<?php
namespace Backpack\Store\app\Services\Variant\Horizontal;

use Backpack\Store\app\Contracts\VariantAvailability as Contract;
use Illuminate\Database\Query\Builder as Qb;

class VariantAvailability implements VariantAvailability
{
    public function idsSubquery(Qb $base, \Closure $existsSelf, \Closure $existsChild, string $t): Qb
    {
        $H = (clone $base)
            ->whereExists(function($s) use ($existsSelf){ $existsSelf($s); })
            ->select('p.id');

        return \DB::query()->fromSub($H, 'u')->select('u.id');
    }
}
