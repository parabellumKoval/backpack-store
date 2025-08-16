<?php

// src/Services/Horizontal/VariantAvailability.php
namespace Backpack\Store\app\Services\Variant\Horizontal;

use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Contracts\VariantAvailability as Contract;

class VariantAvailability implements Contract {
    public function apply(Builder $q, \Closure $self, \Closure $children): Builder {
        // Все записи равноправны — применяем только $self
        return $self($q);
    }
}
