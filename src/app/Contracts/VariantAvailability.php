<?php

namespace Backpack\Store\app\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface VariantAvailability {
    /**
     * Применяет правило наличия для товаров:
     * $self — как проверить сам товар; $children — как проверить детей.
     */
    public function apply(Builder $q, \Closure $self, \Closure $children): Builder;
}
