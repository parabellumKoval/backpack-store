<?php

namespace Backpack\Store\app\Services\Variant\Vertical;

use Backpack\Store\app\Models\Product;

class EffectiveProduct
{
    public function __construct(private Product $p, private AttributeResolver $r) {}

    public function __get($key) { return $this->r->value($this->p, $key); }
    public function __call($m, $a) { return $this->p->$m(...$a); } // пробрасываем вызовы
    public function presentation(): array { return $this->r->presentation($this->p); }
}
