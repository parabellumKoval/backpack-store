<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;
use Illuminate\Support\Facades\DB;

class PriceBandResolver implements SourceResolver
{
    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'price_band';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $anchorPrices = $this->anchorPrices($anchors->ids, $context->country);
        if (empty($anchorPrices)) {
            return new SourceResult($definition, []);
        }

        $reference = $this->referencePrice($anchorPrices, (string) $definition->param('anchor_reference', 'max'));
        $lowFactor = (float) ($definition->param('low_factor') ?? 0.0);
        $highFactor = (float) ($definition->param('high_factor') ?? 0.0);
        $onlyMoreExpensive = (bool) $definition->param('only_more_expensive', false);
        $limit = $definition->param('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $minPrice = $lowFactor > 0 ? $reference * $lowFactor : 0.0;
        $maxPrice = $highFactor > 0 ? $reference * $highFactor : null;

        if ($onlyMoreExpensive) {
            $minPrice = max($minPrice, $reference);
        }

        $candidates = $this->fetchCandidates(
            $context->country,
            $anchors->ids,
            $reference,
            $minPrice,
            $maxPrice,
            $onlyMoreExpensive,
            $limit
        );

        if (!empty($definition->filters)) {
            $candidates = $this->filterEngine->apply($candidates, $definition->filters, $context);
        }

        return new SourceResult($definition, $candidates);
    }

    protected function anchorPrices(array $anchorIds, string $country): array
    {
        return DB::table('ak_catalog')
            ->where('country_code', $country)
            ->whereIn('product_id', $anchorIds)
            ->pluck('price')
            ->filter(fn($value) => $value !== null)
            ->map(fn($value) => (float) $value)
            ->values()
            ->all();
    }

    protected function referencePrice(array $prices, string $mode): float
    {
        sort($prices);
        $mode = strtolower($mode);
        if ($mode === 'median') {
            $count = count($prices);
            $middle = (int) floor(($count - 1) / 2);
            if ($count % 2 === 0) {
                return ($prices[$middle] + $prices[$middle + 1]) / 2;
            }
            return $prices[$middle];
        }
        if ($mode === 'avg' || $mode === 'average') {
            return array_sum($prices) / max(count($prices), 1);
        }
        return max($prices);
    }

    protected function fetchCandidates(
        string $country,
        array $anchorIds,
        float $reference,
        float $minPrice,
        ?float $maxPrice,
        bool $onlyMoreExpensive,
        ?int $limit
    ): array {
        $query = DB::table('ak_catalog as c')
            ->where('c.country_code', $country)
            ->whereNotIn('c.product_id', $anchorIds)
            ->where('c.price', '>=', $minPrice);

        if ($maxPrice !== null && $maxPrice > 0) {
            $query->where('c.price', '<=', $maxPrice);
        }

        if ($onlyMoreExpensive) {
            $query->where('c.price', '>=', $reference);
        }

        $query->select(
            'c.product_id',
            'c.price',
            DB::raw('ABS(c.price - ' . (float) $reference . ') as distance')
        )
        ->orderBy('distance')
        ->orderBy('c.price');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row) use ($reference) {
            return new ResolvedItem((int) $row->product_id, [
                'source' => 'price_band',
                'price' => (float) $row->price,
                'distance' => abs((float)$row->price - $reference),
            ]);
        })->all();
    }
}
