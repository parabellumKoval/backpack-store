<?php
namespace Backpack\Store\app\Services\Variant\Vertical;

use Backpack\Store\app\Contracts\VariantAvailability as Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as Qb;

class VariantAvailability implements Contract
{
    public function idsSubquery(Qb $base, \Closure $existsSelf, \Closure $existsChild, string $t): Qb
    {
        // A) базовые с детьми: базовый активен + есть доступный ребёнок
        $A = (clone $base)
            ->whereNull('p.parent_id')
            ->whereExists(function($sub) use ($t, $existsChild){
                $sub->selectRaw('1')
                    ->from("$t as c")
                    ->whereColumn('c.parent_id','p.id')
                    ->where('c.is_active',1);
                $existsChild($sub);
            })
            ->select('p.id');

        // B) базовые без детей: проверяем самого
        $B = (clone $base)
            ->whereNull('p.parent_id')
            ->whereNotExists(function($s) use ($t){
                $s->selectRaw('1')->from("$t as c")->whereColumn('c.parent_id','p.id');
            })
            ->whereExists(function($s) use ($existsSelf){ $existsSelf($s); })
            ->select('p.id');

        // C) модификации: проверяем саму запись
        $C = (clone $base)
            ->whereNotNull('p.parent_id')
            ->whereExists(function($s) use ($existsSelf){ $existsSelf($s); })
            ->select('p.id');

        return DB::query()->fromSub($A->unionAll($B)->unionAll($C), 'u')->select('u.id');
    }
}