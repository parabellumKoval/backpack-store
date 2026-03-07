<?php

namespace Backpack\Store\app\Services\Campaign;

use Backpack\Store\app\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignResolverService
{
    /** @var array<string, array<int, Campaign|null>> */
    protected array $resolvedByCountry = [];

    public function forProduct(int $productId, ?string $countryCode = null): ?Campaign
    {
        $map = $this->forProducts([$productId], $countryCode);
        return $map[$productId] ?? null;
    }

    /**
     * @param  int[] $productIds
     * @return array<int, Campaign|null>
     */
    public function forProducts(array $productIds, ?string $countryCode = null): array
    {
        $country = $this->normalizeCountry($countryCode);
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (empty($ids)) {
            return [];
        }

        if (!isset($this->resolvedByCountry[$country])) {
            $this->resolvedByCountry[$country] = [];
        }

        $missing = array_values(array_filter($ids, fn($id) => !array_key_exists($id, $this->resolvedByCountry[$country])));

        if (!empty($missing)) {
            $resolved = $this->queryActiveCampaignsByProducts($missing, $country);
            foreach ($missing as $productId) {
                $this->resolvedByCountry[$country][$productId] = $resolved[$productId] ?? null;
            }
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->resolvedByCountry[$country][$id] ?? null;
        }

        return $result;
    }

    public function applyPricing(
        int $productId,
        float $price,
        ?float $oldPrice = null,
        ?string $countryCode = null,
        ?int $decimals = null
    ): array {
        $campaign = $this->forProduct($productId, $countryCode);
        $decimals = $decimals ?? (int) \Settings::get('dress.pricing.product_price_decimal_places', 2);

        $payload = [
            'price' => round($price, $decimals),
            'old_price' => $oldPrice !== null ? round($oldPrice, $decimals) : null,
            'base_price' => round($price, $decimals),
            'campaign_discount_amount' => 0.0,
            'campaign' => null,
        ];

        if (!$campaign || $price <= 0 || $campaign->discount_percent <= 0) {
            return $payload;
        }

        $discountAmount = round($price * ((float) $campaign->discount_percent / 100), $decimals);
        $discountedPrice = round(max(0, $price - $discountAmount), $decimals);

        $payload['price'] = $discountedPrice;
        $payload['old_price'] = round($price, $decimals);
        $payload['campaign_discount_amount'] = $discountAmount;
        $payload['campaign'] = $campaign;

        return $payload;
    }

    /**
     * @return array<int, Campaign>
     */
    protected function queryActiveCampaignsByProducts(array $productIds, string $countryCode): array
    {
        if (empty($productIds)) {
            return [];
        }

        $moment = Carbon::now();

        $rows = DB::table('ak_campaign_product as cp')
            ->join('ak_campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->where('cp.country_code', $countryCode)
            ->whereIn('cp.product_id', $productIds)
            ->where('c.is_active', 1)
            ->where('c.discount_percent', '>', 0)
            ->where(function ($q) use ($countryCode) {
                $q->whereNull('c.countries')
                    ->orWhereJsonContains('c.countries', $countryCode);
            })
            ->where(function ($q) use ($moment) {
                $q->where('c.is_timed', 0)
                    ->orWhere(function ($range) use ($moment) {
                        $range->where('c.is_timed', 1)
                            ->where(function ($from) use ($moment) {
                                $from->whereNull('c.starts_at')
                                    ->orWhere('c.starts_at', '<=', $moment);
                            })
                            ->where(function ($to) use ($moment) {
                                $to->whereNull('c.ends_at')
                                    ->orWhere('c.ends_at', '>=', $moment);
                            });
                    });
            })
            ->orderByDesc('c.priority')
            ->orderByDesc('c.discount_percent')
            ->orderByDesc('c.id')
            ->select('cp.product_id', 'c.*')
            ->get();

        $resolved = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            if (isset($resolved[$productId])) {
                continue;
            }

            $campaign = new Campaign();
            $campaign->exists = true;
            $campaign->setRawAttributes((array) $row, true);
            $campaign->id = (int) $row->id;
            $campaign->syncOriginal();

            $resolved[$productId] = $campaign;
        }

        return $resolved;
    }

    /**
     * @return Collection<int, Campaign>
     */
    public function listActive(?string $countryCode = null, ?string $placement = null): Collection
    {
        $country = $this->normalizeCountry($countryCode);

        $query = Campaign::query()
            ->activeAt()
            ->activeForCountry($country)
            ->whereExists(function ($sub) use ($country) {
                $sub->selectRaw('1')
                    ->from('ak_campaign_product as cp')
                    ->whereColumn('cp.campaign_id', 'ak_campaigns.id')
                    ->where('cp.country_code', $country);
            });

        if ($placement === 'home') {
            $query->where('add_to_main_banner', true);
        }

        if ($placement === 'catalog') {
            $query->where('add_banner_to_catalog', true);
        }

        return $query
            ->orderByDesc('priority')
            ->orderByDesc('discount_percent')
            ->orderByDesc('id')
            ->get();
    }

    protected function normalizeCountry(?string $countryCode = null): string
    {
        $country = $countryCode ?: \Store::context()->country ?: \Store::country();
        return strtolower((string) $country);
    }
}
