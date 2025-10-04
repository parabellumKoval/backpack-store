<?php

namespace Backpack\Store\app\Models\Traits;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\ProductLink;

trait UpsellProductTrait {  

    // все связи (любой kind)
    public function links()
    {
        return $this->morphMany(ProductLink::class, 'linkable');
    }

    public function crossLinks()
    {
        return $this->links()->where('kind','cross');
    }

    public function upLinks()
    {
        return $this->links()->where('kind','up');
    }

    // удобный «джойн» к самим товарам (через список ссылок)
    public function linkedProducts()
    {
        // Eloquent не даёт belongsToMany c morph pivot «из коробки»,
        // поэтому делаем «ручной» построитель:
        $linkTable = (new ProductLink)->getTable();

        return Product::query()
            ->select('ak_products.*')
            ->join($linkTable, $linkTable.'.product_id', '=', 'ak_products.id')
            ->where($linkTable.'.linkable_type', static::class)
            ->where($linkTable.'.linkable_id', $this->getKey());
    }

    // аксессор для бэка/форм
    public function getLinksDataAttribute()
    {
        return $this->links()
            ->orderByDesc('priority')
            ->get(['product_id','kind','priority'])
            ->map(fn($l) => [
                'product_id' => $l->product_id,
                'kind'       => $l->kind,
                'priority'   => $l->priority,
            ])->toArray();
    }
}
