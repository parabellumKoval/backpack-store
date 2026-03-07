<?php

namespace Backpack\Store\app\Services\Campaign;

use Backpack\Store\app\Models\Campaign;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Illuminate\Support\Facades\DB;

class CampaignProductSyncService
{
    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function syncCampaign(Campaign $campaign, ?array $countries = null): void
    {
        if (!$campaign->exists) {
            return;
        }

        $countryCodes = $this->resolveCountryCodes($campaign, $countries);
        if (empty($countryCodes)) {
            if ($countries === null) {
                $this->detachCampaign($campaign);
            } else {
                $requestedCodes = $this->normalizeCountryCodes($countries);
                if (empty($requestedCodes)) {
                    return;
                }

                DB::table('ak_campaign_product')
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('country_code', $requestedCodes)
                    ->delete();
            }
            return;
        }

        $cleanupQuery = DB::table('ak_campaign_product')
            ->where('campaign_id', $campaign->id);

        if ($countries === null) {
            $cleanupQuery->delete();
        } else {
            $cleanupQuery
                ->whereIn('country_code', $countryCodes)
                ->delete();
        }

        foreach ($countryCodes as $countryCode) {
            $resolvedIds = $this->resolveProductIdsForCountry($campaign, $countryCode);

            if (empty($resolvedIds)) {
                continue;
            }

            $rows = array_map(function (int $productId) use ($campaign, $countryCode) {
                return [
                    'campaign_id' => $campaign->id,
                    'product_id' => $productId,
                    'country_code' => $countryCode,
                ];
            }, $resolvedIds);

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('ak_campaign_product')->insert($chunk);
            }
        }
    }

    public function syncAll(?array $countries = null): void
    {
        Campaign::query()
            ->select('id')
            ->chunkById(100, function ($campaigns) use ($countries) {
                foreach ($campaigns as $campaign) {
                    $this->syncCampaign($campaign, $countries);
                }
            });
    }

    public function detachCampaign(Campaign $campaign): void
    {
        DB::table('ak_campaign_product')
            ->where('campaign_id', $campaign->id)
            ->delete();
    }

    protected function resolveProductIdsForCountry(Campaign $campaign, string $countryCode): array
    {
        $source = strtolower((string) ($campaign->product_source ?: 'filters'));
        $source = in_array($source, ['filters', 'manual', 'mixed'], true) ? $source : 'filters';

        $manualIds = $this->filterAvailableProductIds($countryCode, $campaign->manual_product_ids);
        $filterIds = [];

        if (in_array($source, ['filters', 'mixed'], true)) {
            $rules = $campaign->product_filter_rules;

            if (empty($rules)) {
                $filterIds = $this->allAvailableProductIds($countryCode);
            } else {
                $context = new ListRequestContext(
                    page: 'campaign',
                    country: $countryCode,
                    lang: app()->getLocale()
                );

                $limit = $this->resolveMaxProducts();
                $items = $this->filterEngine->collect($rules, $context, $limit);
                $filterIds = array_map(fn($item) => (int) $item->productId, $items);
            }
        }

        $ids = match ($source) {
            'manual' => $manualIds,
            'mixed' => array_merge($manualIds, $filterIds),
            default => $filterIds,
        };

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        $limit = $this->resolveMaxProducts();
        if ($limit > 0 && count($ids) > $limit) {
            $ids = array_slice($ids, 0, $limit);
        }

        return $ids;
    }

    protected function allAvailableProductIds(string $countryCode): array
    {
        $limit = $this->resolveMaxProducts();

        $query = DB::table('ak_catalog')
            ->where('country_code', $countryCode)
            ->where('is_available', 1)
            ->orderBy('product_id')
            ->select('product_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->pluck('product_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    protected function filterAvailableProductIds(string $countryCode, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        return DB::table('ak_catalog')
            ->where('country_code', $countryCode)
            ->where('is_available', 1)
            ->whereIn('product_id', $ids)
            ->orderBy('product_id')
            ->pluck('product_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    protected function resolveCountryCodes(Campaign $campaign, ?array $countries = null): array
    {
        $campaignCountries = $this->normalizeCountryCodes($campaign->countries ?? null);

        if ($countries !== null) {
            $requested = $this->normalizeCountryCodes($countries);
            if (empty($requested)) {
                return [];
            }

            if (empty($campaignCountries)) {
                return $requested;
            }

            return array_values(array_intersect($requested, $campaignCountries));
        }

        if (!empty($campaignCountries)) {
            return $campaignCountries;
        }

        $all = \Store::countries();
        if (!is_array($all) || empty($all)) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn($code) => strtolower((string) $code),
            array_keys($all)
        )));
    }

    protected function normalizeCountryCodes(mixed $codes): array
    {
        if ($codes === null || $codes === '') {
            return [];
        }

        if (is_string($codes)) {
            $decoded = json_decode($codes, true);
            $codes = json_last_error() === JSON_ERROR_NONE ? $decoded : [$codes];
        }

        if (!is_array($codes)) {
            return [];
        }

        $prepared = [];
        foreach ($codes as $code) {
            if (!is_scalar($code)) {
                continue;
            }

            $country = strtolower(trim((string) $code));
            if ($country === '') {
                continue;
            }

            $prepared[] = $country;
        }

        return array_values(array_unique($prepared));
    }

    protected function resolveMaxProducts(): int
    {
        $max = \Settings::get('dress.campaign.max_products', 50000);
        $max = is_numeric($max) ? (int) $max : 50000;

        return max($max, 1);
    }
}
