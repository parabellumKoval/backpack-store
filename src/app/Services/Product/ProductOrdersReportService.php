<?php

namespace Backpack\Store\app\Services\Product;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProductOrdersReportService
{
    protected string $orderModelClass;

    protected Model $orderPrototype;

    protected string $orderTable;

    protected string $baseCurrency;

    protected string $baseCurrencySymbol;

    /**
     * @param  string|null  $orderModelClass
     */
    public function __construct(?string $orderModelClass = null)
    {
        $this->orderModelClass = $orderModelClass ?: \Settings::get('dress.order.model', \Backpack\Store\app\Models\Order::class);
        $this->orderPrototype = app($this->orderModelClass);
        $this->orderTable = $this->orderPrototype->getTable();
        $this->baseCurrency = strtoupper((string) config('dress.store.base_currency', 'USD'));
        $this->baseCurrencySymbol = (string) config('dress.store.currency.symbol', '$');
    }

    public function build(int $productId, array $options = []): array
    {
        $perPage = max(1, min(100, (int) ($options['per_page'] ?? 10)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $rangeMonths = max(1, min(24, (int) ($options['range_months'] ?? 12)));

        [$summary, $chart] = $this->summariesWithChart($productId, $rangeMonths);
        $ordersPaginator = $this->paginateOrders($productId, $perPage, $page);

        return [
            'summary' => $summary,
            'chart' => $chart,
            'orders' => $this->formatOrdersPaginator($ordersPaginator, $productId),
        ];
    }

    protected function summariesWithChart(int $productId, int $rangeMonths): array
    {
        $stats = [
            'orders_count' => 0,
            'total_quantity' => 0,
            'revenue_per_currency' => [],
            'revenue_base' => 0.0,
        ];

        $rangeMonths = max(1, $rangeMonths);
        $chartBuckets = $this->primeChartBuckets($rangeMonths);
        $chartFrom = count($chartBuckets) ? array_key_first($chartBuckets) : null;

        $this->chunkOrders($productId, function (Collection $orders) use (&$stats, &$chartBuckets, $productId, $chartFrom) {
            foreach ($orders as $order) {
                $lineStats = $this->calculateLineStats($order, $productId);
                if ($lineStats['quantity'] <= 0) {
                    continue;
                }

                $stats['orders_count']++;
                $stats['total_quantity'] += $lineStats['quantity'];

                if ($lineStats['line_total'] !== null) {
                    $currency = strtoupper($lineStats['currency'] ?: ($order->currency_code ?? $this->baseCurrency));
                    $stats['revenue_per_currency'][$currency] = ($stats['revenue_per_currency'][$currency] ?? 0) + $lineStats['line_total'];
                }

                if ($lineStats['line_total_base'] !== null) {
                    $stats['revenue_base'] += $lineStats['line_total_base'];
                }

                if ($chartFrom && $order->created_at instanceof Carbon) {
                    $bucketKey = $order->created_at->copy()->startOfMonth()->format('Y-m');
                    if (isset($chartBuckets[$bucketKey])) {
                        $chartBuckets[$bucketKey]['quantity'] += $lineStats['quantity'];
                        if ($lineStats['line_total_base'] !== null) {
                            $chartBuckets[$bucketKey]['revenue'] += $lineStats['line_total_base'];
                        }
                    }
                }
            }
        });

        ksort($stats['revenue_per_currency']);

        return [
            [
                'orders_count' => $stats['orders_count'],
                'total_quantity' => $stats['total_quantity'],
                'revenue_per_currency' => $this->formatRevenueMap($stats['revenue_per_currency']),
                'revenue_display' => $this->renderRevenueDisplay($stats['revenue_per_currency']),
                'revenue_base' => round($stats['revenue_base'], 2),
                'revenue_base_currency' => $this->baseCurrency,
                'revenue_base_symbol' => $this->baseCurrencySymbol,
            ],
            $this->transformChartBuckets($chartBuckets),
        ];
    }

    protected function chunkOrders(int $productId, callable $callback): void
    {
        $orderModel = $this->orderModelClass;
        $builder = $orderModel::query()
            ->select($this->orderTable . '.*')
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('ak_products.id', $productId);
            })
            ->with(['products' => function ($query) use ($productId) {
                $query->select('ak_products.id')->where('ak_products.id', $productId);
            }])
            ->orderBy($this->orderTable . '.id');

        $builder->chunkById(200, $callback, 'id');
    }

    protected function paginateOrders(int $productId, int $perPage, int $page): LengthAwarePaginator
    {
        $orderModel = $this->orderModelClass;

        return $orderModel::query()
            ->select($this->orderTable . '.*')
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('ak_products.id', $productId);
            })
            ->with(['products' => function ($query) use ($productId) {
                $query->select('ak_products.id')->where('ak_products.id', $productId);
            }])
            ->orderByDesc($this->orderTable . '.created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    protected function formatOrdersPaginator(LengthAwarePaginator $paginator, int $productId): array
    {
        $items = $paginator->getCollection()->map(function ($order) use ($productId) {
            $lineStats = $this->calculateLineStats($order, $productId);
            $currency = $lineStats['currency'] ?: ($order->currency_code ?? $this->baseCurrency);

            return [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $this->formatStatus($order->status, 'order_status'),
                'pay_status' => $this->formatStatus($order->pay_status, 'pay_status'),
                'delivery_status' => $this->formatStatus($order->delivery_status, 'delivery_status'),
                'customer' => $this->formatCustomer($order),
                'quantity' => $lineStats['quantity'],
                'unit_price' => $lineStats['unit_price'],
                'total' => $lineStats['line_total'],
                'currency' => $currency,
                'created_at' => optional($order->created_at)->toAtomString(),
                'created_at_human' => optional($order->created_at)->format('d.m.Y H:i'),
                'edit_url' => backpack_url('order/' . $order->id . '/edit'),
            ];
        })->values();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    protected function calculateLineStats(Model $order, int $productId): array
    {
        $quantity = 0;
        $lineTotal = 0.0;
        $currency = null;
        $missingAmount = 0;

        foreach ($order->products as $product) {
            if ((int) $product->id !== $productId) {
                continue;
            }

            $amount = (int) ($product->pivot->amount ?? 0);
            $quantity += $amount;

            $unit = $this->toFloat($product->pivot->value ?? null);
            if ($unit !== null) {
                $lineTotal += $unit * $amount;
                $currency = $product->pivot->currency_code ?? $currency;
            } else {
                $missingAmount += $amount;
            }
        }

        if ($missingAmount > 0) {
            $infoPrice = $this->extractPriceFromInfo($order, $productId);
            if ($infoPrice !== null) {
                $lineTotal += $infoPrice * $missingAmount;
            }
        }

        $currency = $currency ?: ($order->currency_code ?? $this->baseCurrency);
        $lineTotal = $quantity > 0 ? $lineTotal : null;

        return [
            'quantity' => $quantity,
            'unit_price' => $lineTotal !== null && $quantity > 0 ? round($lineTotal / $quantity, 2) : null,
            'line_total' => $lineTotal !== null ? round($lineTotal, 2) : null,
            'currency' => $currency,
            'line_total_base' => $lineTotal !== null ? $this->convertToBase($lineTotal, $currency, $order->fx_rate ?? null) : null,
        ];
    }

    protected function extractPriceFromInfo(Model $order, int $productId): ?float
    {
        $products = data_get($order->info, 'products', []);
        if (!is_iterable($products)) {
            return null;
        }

        foreach ($products as $product) {
            if ((int) ($product['id'] ?? 0) === $productId) {
                return $this->toFloat($product['price'] ?? null);
            }
        }

        return null;
    }

    protected function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], $value);
            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    protected function convertToBase(?float $amount, ?string $currency, ?float $fxRate): ?float
    {
        if ($amount === null) {
            return null;
        }

        $currency = strtoupper($currency ?: $this->baseCurrency);
        if ($currency === $this->baseCurrency || !$fxRate) {
            return round($amount, 2);
        }

        if ((float) $fxRate === 0.0) {
            return null;
        }

        return round($amount / (float) $fxRate, 2);
    }

    protected function formatStatus(?string $value, string $group): array
    {
        $value = $value ?: 'unknown';
        $translations = trans("backpack-store::shop.$group.$value");
        $label = is_string($translations) ? $translations : $value;

        return [
            'value' => $value,
            'label' => $label,
        ];
    }

    protected function formatCustomer(Model $order): string
    {
        $user = $order->user ?? [];
        $name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (!empty($user['email'])) {
            return (string) $user['email'];
        }

        if (!empty($user['phone'])) {
            return (string) $user['phone'];
        }

        return 'ID #' . $order->id;
    }

    protected function primeChartBuckets(int $months): array
    {
        $buckets = [];
        $end = Carbon::now()->startOfMonth();
        $start = (clone $end)->subMonths($months - 1);

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = [
                'label' => $cursor->translatedFormat('M Y'),
                'quantity' => 0,
                'revenue' => 0.0,
            ];
            $cursor->addMonth();
        }

        return $buckets;
    }

    protected function transformChartBuckets(array $buckets): array
    {
        if (empty($buckets)) {
            return [
                'labels' => [],
                'quantity' => [],
                'revenue' => [],
                'currency' => $this->baseCurrency,
            ];
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'quantity' => array_map(fn ($bucket) => (int) $bucket['quantity'], $buckets),
            'revenue' => array_map(fn ($bucket) => round($bucket['revenue'], 2), $buckets),
            'currency' => $this->baseCurrency,
        ];
    }

    protected function formatRevenueMap(array $map): array
    {
        $formatted = [];
        foreach ($map as $currency => $value) {
            $formatted[] = [
                'currency' => $currency ?: $this->baseCurrency,
                'amount' => round($value, 2),
            ];
        }

        return $formatted;
    }

    protected function renderRevenueDisplay(array $map): string
    {
        if (empty($map)) {
            return trans('backpack-store::product.orders_tab.summary.revenue_empty');
        }

        $segments = [];
        foreach ($map as $currency => $value) {
            $segments[] = sprintf('%s %s', $currency ?: $this->baseCurrency, number_format(round($value, 2), 2, '.', ' '));
        }

        return implode(' / ', $segments);
    }
}
