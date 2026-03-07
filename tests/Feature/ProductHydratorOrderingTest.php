<?php

namespace Backpack\Store\Tests\Feature;

use Backpack\Store\app\Services\ProductLists\ProductHydrator;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\TestCase;

class ProductHydratorOrderingTest extends TestCase
{
    public function test_hydrate_preserves_requested_ids_order(): void
    {
        $rowsById = [
            1 => (object) ['product_id' => 1],
            2 => (object) ['product_id' => 2],
            3 => (object) ['product_id' => 3],
        ];

        $hydrator = new class($rowsById) extends ProductHydrator {
            public function __construct(private array $rowsById)
            {
                self::$resources['product']['small'] = ProductHydratorOrderingDummyResource::class;
            }

            public function fetchCatalogRows(array $ids, string $country): array
            {
                return $this->rowsById;
            }
        };

        $resource = $hydrator->hydrate([3, 1, 2], 'ua', 'uk');
        $orderedIds = $resource->collection
            ->map(fn (ProductHydratorOrderingDummyResource $item) => (int) ($item->resource->product_id ?? 0))
            ->all();

        $this->assertSame([3, 1, 2], $orderedIds);
    }
}

class ProductHydratorOrderingDummyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'product_id' => (int) ($this->resource->product_id ?? 0),
        ];
    }
}
