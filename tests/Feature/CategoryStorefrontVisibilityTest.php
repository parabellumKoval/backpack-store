<?php

namespace Backpack\Store\Tests\Feature;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Services\Store;
use Backpack\Store\Tests\TestCase;

class CategoryStorefrontVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dress.storefront.enabled', true);
        config()->set('dress.storefront.default', 'main');
        config()->set('dress.storefront.apply_unassigned_to_default', true);
        config()->set('dress.storefront.values', [
            'main' => [
                'enabled' => true,
                'code' => 'main',
                'label' => 'Main',
            ],
            'kratom' => [
                'enabled' => true,
                'code' => 'kratom',
                'label' => 'Kratom',
            ],
        ]);

        $ref = new \ReflectionClass(Store::class);
        $cache = $ref->getProperty('normalizedStorefrontsCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    }

    public function test_visible_ids_inherit_storefronts_from_parent_even_with_invalid_lft_order(): void
    {
        $root = Category::query()->create([
            'name' => ['en' => 'Kratom'],
            'slug' => 'kratom',
            'is_active' => true,
            'storefronts' => ['kratom'],
            'lft' => 20,
            'rgt' => 30,
        ]);

        $child = Category::query()->create([
            'name' => ['en' => 'Powder'],
            'slug' => 'powder',
            'is_active' => true,
            'parent_id' => $root->id,
            'lft' => 5,
            'rgt' => 6,
        ]);

        $grandChild = Category::query()->create([
            'name' => ['en' => 'Red Vein'],
            'slug' => 'red-vein',
            'is_active' => true,
            'parent_id' => $child->id,
            'lft' => 1,
            'rgt' => 2,
        ]);

        $kratomVisible = Category::visibleIdsForContext(null, 'kratom', false);
        $mainVisible = Category::visibleIdsForContext(null, 'main', false);

        $this->assertEqualsCanonicalizing([$root->id, $child->id, $grandChild->id], $kratomVisible);
        $this->assertSame([], array_values(array_intersect($mainVisible, [$root->id, $child->id, $grandChild->id])));
    }
}
