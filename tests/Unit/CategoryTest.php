<?php

namespace Backpack\Store\Tests\Unit;

require_once __DIR__.'/../TestCase.php';

use Backpack\Store\app\Models\Category;
use Backpack\Store\Tests\TestCase;

class CategoryTest extends TestCase
{
    /** @test */
    public function children_for_country_ignores_circular_relations()
    {
        $first = Category::factory()->create(['parent_id' => null]);
        $second = Category::factory()->create(['parent_id' => $first->id]);

        $first->parent_id = $second->id;
        $first->save();

        $first->refresh();
        $children = $first->childrenForCountry();

        $this->assertCount(1, $children);
        $firstChild = $children->first();
        $this->assertNotNull($firstChild);
        $this->assertEquals($second->id, $firstChild->id);
        $this->assertTrue($firstChild->relationLoaded('children'));
        $this->assertCount(0, $firstChild->getRelation('children'));
    }
}
