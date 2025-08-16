<?php

// src/Services/AvailabilityService.php
namespace Backpack\Store\app\Services\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Contracts\SupplierFilter;
use Backpack\Store\app\Contracts\VariantAvailability;

class AvailabilityFilter
{
    public function __construct(
        private SupplierFilter $suppliers,
        private VariantAvailability $variants,
    ) {}

    public function scopeAvailable(Builder $q, ?string $country = null): Builder
    {
        $t = $q->getModel()->getTable();

        // как проверить САМ товар
        $self = function (Builder $b) use ($t, $country) {
            $b->where("$t.is_active", 1);
            $this->suppliers->existsFor($b, "$t.id", $country);
            return $b;
        };

        // как проверить ДЕТЕЙ (для вертикального режима)
        $children = function (Builder $b) use ($t, $country) {
            return $b->whereExists(function ($sub) use ($t, $country) {
                $sub->select(DB::raw(1))
                    ->from("$t as c")
                    ->whereColumn('c.parent_id', "$t.id");
                // у ребёнка должны выполниться условия "сам товар доступен"
                $this->suppliers->existsFor($sub, 'c.id', $country);
                $sub->where('c.is_active', 1);
            });
        };

        return $this->variants->apply($q, $self, $children);
    }
}
