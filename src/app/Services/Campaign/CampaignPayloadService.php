<?php

namespace Backpack\Store\app\Services\Campaign;

use Backpack\Store\app\Models\Campaign;

class CampaignPayloadService
{
    public function make(?Campaign $campaign): ?array
    {
        if (!$campaign) {
            return null;
        }

        $catalogPosition = (int) ($campaign->catalog_banner_position ?? 0);
        $catalogFrequency = (int) ($campaign->catalog_banner_frequency ?? 0);
        if ((bool) $campaign->add_banner_to_catalog && $catalogPosition <= 0) {
            $catalogPosition = 1;
        }

        return [
            'id' => (int) $campaign->id,
            'name' => (string) $campaign->name,
            'slug' => (string) $campaign->slug,
            'short_description' => $campaign->short_description,
            'conditions_html' => $campaign->conditions_html,
            'discount_percent' => (float) $campaign->discount_percent,
            'priority' => (int) $campaign->priority,
            'is_timed' => (bool) $campaign->is_timed,
            'starts_at' => $campaign->starts_at?->toAtomString(),
            'ends_at' => $campaign->ends_at?->toAtomString(),
            'show_timer_card' => (bool) $campaign->show_timer_card,
            'show_timer_product' => (bool) $campaign->show_timer_product,
            'horizontal_banner' => $campaign->horizontal_banner,
            'vertical_banner' => $campaign->vertical_banner,
            'add_to_main_banner' => (bool) $campaign->add_to_main_banner,
            'add_banner_to_catalog' => (bool) $campaign->add_banner_to_catalog,
            'catalog_banner_frequency' => $catalogFrequency > 0 ? $catalogFrequency : null,
            'catalog_banner_position' => $catalogPosition > 0 ? $catalogPosition : null,
            'is_active_now' => (bool) $campaign->is_active_now,
            'catalog_url' => '/catalog?campaign=' . urlencode((string) $campaign->slug),
        ];
    }
}
