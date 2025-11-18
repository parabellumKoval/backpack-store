<?php

namespace Backpack\Store\app\Services;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminDashboardWidgetService
{
    protected Order $orderPrototype;
    protected Product $productPrototype;
    protected string $ordersTable;
    protected string $productsTable;
    protected string $pivotTable = 'ak_order_product';
    protected string $baseCurrency;
    protected string $baseCurrencySymbol;

    public function __construct()
    {
        $this->orderPrototype = new Order();
        $this->productPrototype = new Product();
        $this->ordersTable = $this->orderPrototype->getTable();
        $this->productsTable = $this->productPrototype->getTable();
        $this->baseCurrency = strtoupper((string) config('dress.store.base_currency', 'USD'));
        $this->baseCurrencySymbol = (string) (config('dress.store.currency.symbol') ?? $this->baseCurrency);
    }

    public function bestSellingProducts(int $limit = 10): Collection
    {
        $limit = max(1, $limit);
        $valueExpr = "CAST(NULLIF(REPLACE(REPLACE(op.value, ' ', ''), ',', '.'), '') AS DECIMAL(18,4))";
        $unitPriceExpr = "COALESCE({$valueExpr}, 0)";
        $fxExpr = "COALESCE(NULLIF(o.fx_rate, 0), 1)";

        $rows = DB::table("{$this->pivotTable} as op")
            ->join("{$this->ordersTable} as o", 'o.id', '=', 'op.order_id')
            ->join("{$this->productsTable} as p", 'p.id', '=', 'op.product_id')
            ->select([
                'p.id',
                'p.name',
                'p.slug',
                'p.price',
                'p.images',
            ])
            ->selectRaw('SUM(op.amount) as total_units')
            ->selectRaw("SUM(op.amount * {$unitPriceExpr}) as total_revenue_order_currency")
            ->selectRaw("SUM(op.amount * {$unitPriceExpr} / {$fxExpr}) as total_revenue_base")
            ->where('op.amount', '>', 0)
            ->whereNotIn('o.status', ['canceled', 'failed'])
            ->groupBy('p.id', 'p.name', 'p.slug', 'p.price', 'p.images')
            ->orderByDesc('total_units')
            ->limit($limit)
            ->get();

        $productIds = $rows->pluck('id')->filter()->unique();
        $products = $productIds->isEmpty()
            ? collect()
            : $this->productPrototype->newQuery()->whereIn('id', $productIds)->get()->keyBy('id');

        $reviewStats = $this->reviewStatsForProducts($productIds);

        return $rows->map(function ($row) use ($reviewStats, $products) {
            $imageUrl = $this->extractImageUrl($row->images);
            $stats = $reviewStats->get($row->id);
            $ratingAvg = $stats->rating_avg ?? null;
            $ratingsCount = (int) ($stats->rating_count ?? 0);
            $productModel = $products->get($row->id);
            [$priceValue, $priceCurrency, $priceDisplay] = $this->resolveProductPriceData(
                $productModel,
                (float) ($row->price ?? 0)
            );

            return [
                'id' => (int) $row->id,
                'name' => $this->resolveProductName($productModel, $row->name),
                'slug' => $row->slug,
                'image_url' => $imageUrl,
                'price_value' => $priceValue,
                'price_currency' => $priceCurrency,
                'price_display' => $priceDisplay,
                'rating' => $ratingAvg !== null ? round((float) $ratingAvg, 2) : null,
                'ratings_count' => $ratingsCount,
                'units_sold' => (int) ($row->total_units ?? 0),
                'revenue_value' => (float) ($row->total_revenue_base ?? 0),
                'revenue_display' => $this->formatMoney($row->total_revenue_base ?? 0),
            ];
        });
    }

    public function ordersWidgetPayload(?string $country = null, int $limit = 10): array
    {
        $country = $this->normalizeCountry($country);
        $limit = max(1, $limit);

        $orders = $this->collectOrders($country, $limit);

        return [
            'orders' => $this->formatOrders($orders),
            'countries' => $this->countriesForFilters(),
            'active' => $country,
        ];
    }

    protected function collectOrders(?string $country, int $limit): EloquentCollection
    {
        $baseQuery = $this->baseOrderQuery($country);

        $newOrders = (clone $baseQuery)
            ->where('status', 'new')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($newOrders->count() >= $limit) {
            return $newOrders;
        }

        $missing = $limit - $newOrders->count();

        $latestOrders = $this->baseOrderQuery($country)
            ->whereNotIn('id', $newOrders->pluck('id'))
            ->orderByDesc('created_at')
            ->limit($missing)
            ->get();

        return $newOrders->concat($latestOrders)
            ->sortByDesc(fn ($order) => $order->created_at)
            ->values();
    }

    protected function baseOrderQuery(?string $country): Builder
    {
        $query = $this->orderPrototype->newQuery()
            ->select([
                'id',
                'code',
                'status',
                'country_code',
                'currency_code',
                'grand_total',
                'price',
                'info',
                'orderable_id',
                'created_at',
            ]);

        if ($country) {
            $query->where('country_code', $country);
        }

        return $query;
    }

    protected function formatOrders(EloquentCollection $orders): array
    {
        return $orders->map(function (Order $order) {
            $status = $this->formatStatus($order->status);
            $payStatus = $this->formatPayStatus($order->pay_status);
            $countryCode = strtoupper((string) ($order->country_code ?? ''));
            $customer = $this->formatCustomer($order);
            $itemsCount = $this->orderItemsCount($order);

            return [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $status['value'],
                'status_label' => $status['label'],
                'status_badge_class' => $this->statusBadgeClass($status['value']),
                'pay_status' => $payStatus['value'],
                'pay_status_label' => $payStatus['label'],
                'pay_status_badge_class' => $this->payStatusBadgeClass($payStatus['value']),
                'country_code' => $countryCode,
                'country_flag' => $this->countryFlagHtml($countryCode),
                'country_label' => $this->countryLabel($countryCode),
                'total' => $this->formatOrderTotal($order),
                'currency' => $order->currency_code ?? $this->baseCurrency,
                'customer' => $customer['name'],
                'contacts' => $customer['contacts'],
                'items_count' => $itemsCount,
                'items_label' => trans_choice(
                    'backpack-store::dashboard.widgets.orders_widget_items',
                    $itemsCount,
                    ['count' => $itemsCount]
                ),
                'created_at' => optional($order->created_at)->format('d.m.Y H:i'),
                'created_human' => optional($order->created_at)->diffForHumans(),
                'created_at_iso' => optional($order->created_at)->toAtomString(),
                'show_url' => backpack_url('order/' . $order->id . '/show'),
            ];
        })->all();
    }

    protected function formatCustomer(Order $order): array
    {
        $user = (array) ($order->user ?? []);
        $infoUser = (array) data_get($order->info, 'user', []);
        $data = array_filter(array_merge($infoUser, $user));

        $nameParts = array_filter([
            $data['first_name'] ?? $data['firstname'] ?? null,
            $data['last_name'] ?? $data['lastname'] ?? null,
        ]);

        $name = trim(implode(' ', $nameParts));
        $name = $name !== '' ? $name : ($data['name'] ?? null);
        $name = $name ?: ($data['email'] ?? $data['phone'] ?? ('ID #' . $order->id));

        $contacts = collect([
            $data['email'] ?? null,
            $data['phone'] ?? null,
        ])->filter()->implode(' · ');

        return [
            'name' => $name,
            'contacts' => $contacts,
        ];
    }

    protected function formatOrderTotal(Order $order): string
    {
        $amount = $order->grand_total ?? $order->price ?? 0;
        $currency = $order->currency_code ?? $this->baseCurrency;

        return number_format((float) $amount, 2, '.', ' ') . ' ' . strtoupper($currency);
    }

    protected function formatStatus(?string $status): array
    {
        $value = $status ?: 'new';
        $translations = trans('backpack-store::shop.order_status.' . $value);
        $label = is_string($translations) ? $translations : Str::ucfirst($value);

        return [
            'value' => $value,
            'label' => $label,
        ];
    }

    protected function formatPayStatus(?string $status): array
    {
        $value = $status ?: 'waiting';
        $translations = trans('backpack-store::shop.pay_status.' . $value);
        $label = is_string($translations) ? $translations : Str::ucfirst($value);

        return [
            'value' => $value,
            'label' => $label,
        ];
    }

    protected function countriesForFilters(): array
    {
        $countries = \Store::countries();

        return collect($countries)
            ->map(function ($country, $code) {
                $code = strtoupper((string) $code);

                return [
                    'code' => $code,
                    'label' => $country['country'] ?? $code,
                    'flag' => $this->countryFlagHtml($code),
                ];
            })
            ->values()
            ->all();
    }

    protected function countryFlagHtml(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        if (strlen($code) !== 2) {
            return null;
        }

        $offset = 127397;
        $chars = str_split($code);

        $entities = array_map(function ($char) use ($offset) {
            return '&#' . (ord($char) + $offset) . ';';
        }, $chars);

        return implode('', $entities);
    }

    protected function extractImageUrl($images): ?string
    {
        $decoded = $this->decodeImages($images);
        $path = data_get($decoded, '0.src');

        if (!$path) {
            return null;
        }

        return $this->productPrototype->formatImageUrlForAttribute('images', $path);
    }

    protected function reviewStatsForProducts(Collection $productIds): Collection
    {
        $productIds = $productIds->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return collect();
        }

        return DB::table('ak_reviews')
            ->select('reviewable_id')
            ->selectRaw('AVG(rating) as rating_avg')
            ->selectRaw('COUNT(rating) as rating_count')
            ->whereIn('reviewable_id', $productIds)
            // ->where('reviewable_type', Product::class)
            ->where('reviewable_type', \App\Models\Product::class)
            ->where('is_moderated', 1)
            ->groupBy('reviewable_id')
            ->get()
            ->keyBy('reviewable_id');
    }

    protected function resolveProductPriceData(?Product $product, float $fallbackValue): array
    {
        if ($product) {
            $value = (float) ($product->price ?? $fallbackValue);
            $currency = strtoupper($product->currency ?? $this->baseCurrency);

            return [$value, $currency, $this->formatPriceWithCurrency($value, $currency)];
        }

        $currency = $this->baseCurrency;

        return [$fallbackValue, $currency, $this->formatPriceWithCurrency($fallbackValue, $currency)];
    }

    protected function resolveProductName(?Product $product, $rawName): string
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');

        if ($product) {
            if (method_exists($product, 'getTranslation')) {
                return $product->getTranslation('name', $locale, false)
                    ?: $product->getTranslation('name', $fallback, false)
                    ?: (string) $product->name;
            }

            return (string) $product->name;
        }

        $decoded = $this->decodeTranslatableString($rawName);

        if (is_array($decoded)) {
            return $decoded[$locale] ?? $decoded[$fallback] ?? reset($decoded) ?? '';
        }

        return (string) $rawName;
    }

    protected function decodeTranslatableString($value): ?array
    {
        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    protected function formatPriceWithCurrency(float $value, string $currency): string
    {
        return number_format($value, 2, '.', ' ') . ' ' . strtoupper($currency);
    }

    protected function statusBadgeClass(string $status): string
    {
        return [
            'new' => 'badge-warning',
            'completed' => 'badge-success',
            'approved' => 'badge-success',
            'failed' => 'badge-danger',
            'canceled' => 'badge-secondary',
        ][$status] ?? 'badge-secondary';
    }

    protected function payStatusBadgeClass(string $status): string
    {
        return [
            'waiting' => 'badge-light',
            'failed' => 'badge-danger',
            'paied' => 'badge-success',
        ][$status] ?? 'badge-light';
    }

    protected function countryLabel(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }

        return \Store::countryLabel(strtolower($countryCode));
    }

    protected function orderItemsCount(Order $order): int
    {
        $products = data_get($order->info, 'products', []);
        if (is_iterable($products)) {
            return collect($products)->sum(fn ($item) => (int) ($item['amount'] ?? 0));
        }

        if ($order->relationLoaded('products')) {
            return $order->products->sum(fn ($product) => (int) ($product->pivot->amount ?? 0));
        }

        return 0;
    }

    protected function decodeImages($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function formatMoney($value, bool $includeCurrency = false): string
    {
        $amount = number_format((float) $value, 2, '.', ' ');

        if (!$includeCurrency) {
            return $amount . ' ' . $this->baseCurrency;
        }

        $symbol = $this->baseCurrencySymbol ?: $this->baseCurrency;

        return $symbol . ' ' . $amount;
    }

    protected function normalizeCountry(?string $country): ?string
    {
        $country = $country ? strtolower(trim($country)) : null;

        if (!$country) {
            return null;
        }

        $countries = array_map('strtolower', array_keys(\Store::countries() ?? []));

        return in_array($country, $countries, true) ? $country : null;
    }
}
