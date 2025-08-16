<?php

// src/Services/Vertical/VariantAvailability.php
namespace Backpack\Store\app\Services\Variant\Vertical;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Contracts\VariantAvailability as Contract;

class VariantAvailability implements Contract {
    // public function apply(Builder $q, \Closure $self, \Closure $children): Builder {
    //     $t = $q->getModel()->getTable();
    //     return $q->where(function ($w) use ($t, $self, $children) {
    //         // БАЗОВЫЕ: доступны, если есть доступная модификация
    //         $w->whereNull("$t.parent_id");
    //         $children($w); // проверка детей
    //     })->orWhere(function ($w) use ($t, $self) {
    //         // МОДИФИКАЦИИ: проверка самой записи
    //         $w->whereNotNull("$t.parent_id");
    //         $self($w);
    //     });
    // }
    public function apply(Builder $q, \Closure $self, \Closure $children): Builder
    {
        $t = $q->getModel()->getTable();

        return $q
            // БАЗОВЫЕ С ДЕТЬМИ: базовый активен И есть хотя бы один доступный ребёнок.
            ->where(function (Builder $w) use ($t, $children) {
                $w->whereNull("$t.parent_id")
                  ->where("$t.is_active", 1);

                // children() ДОЛЖЕН навесить exists(...) по c.* (is_active + suppliers/region)
                $children($w);
            })

            // БАЗОВЫЕ БЕЗ ДЕТЕЙ: проверяем самого (его suppliers и т.д.)
            ->orWhere(function (Builder $w) use ($t, $self) {
                $w->whereNull("$t.parent_id")
                  ->whereNotExists(function ($sub) use ($t) {
                      $sub->selectRaw('1')
                          ->from("$t as c")
                          ->whereColumn('c.parent_id', "$t.id");
                  });

                $self($w); // здесь учитываются его собственные suppliers
            })

            // МОДИФИКАЦИИ: проверяем саму запись
            ->orWhere(function (Builder $w) use ($t, $self) {
                $w->whereNotNull("$t.parent_id");
                $self($w);
            });
    }
}
