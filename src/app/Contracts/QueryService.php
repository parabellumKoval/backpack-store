<?php
namespace Backpack\Store\app\Contracts;

interface QueryService {
    public function startQuery(): self;
    public function filterBySelections(): self;
    public function filterByAttributes($except_attribute_id = null): self;
    public function filterByCategories(): self;
    public function filterByBrands(): self;
    public function filterByBrandSlug(): self;
    public function filterByPrice(): self;
    public function filterBySearch(): self;

    public function sorting(): self;
    public function getProducts();
    public function getQuery();
}
