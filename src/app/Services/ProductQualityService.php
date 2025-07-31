<?php

namespace Backpack\Store\app\Services;

use Backpack\Store\app\Models\Product;

class ProductQualityService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('backpack.pq.parameters');
    }

    public function calculate(Product $product): array
    {
        $details = [];
        $totalScore = 0;

        foreach ($this->config as $key => $param) {
            $value = $this->getValue($product, $key);
            $rate = $this->calculateRate($value, $param['ideal']);
            $score = $rate * ($param['weight'] / 100);

            $details[$key] = [
                'value' => $value,
                'unit' => $param['unit'],
                'rate' => round($rate * 100, 1),
                'weight' => $param['weight']
            ];

            $totalScore += $score;
        }

        return [
            'total' => round($totalScore * 100, 1),
            'details' => $details
        ];
    }

    protected function calculateRate($value, $ideal): float
    {
        if ($ideal <= 0) return 1;
        return min($value / $ideal, 1); // не больше 100%
    }

    protected function getValue(Product $product, string $key): int
    {
        switch ($key) {
            case 'description':
                return strlen($product->content ?? '');
            case 'images':
                return count($product->images ?? []);
            case 'properties':
                return count($product->properties ?? []) + count($product->customProperties ?? []);
            case 'brand':
                return $product->brand ? 1 : 0;
            case 'category':
                return $product->categories->count() > 0 ? 1 : 0;
            case 'name':
                return strlen($product->name ?? '');
            default:
                return 0;
        }
    }
}
