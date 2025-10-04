<?php

namespace Backpack\Store\app\Services\Upsell;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Backpack\Store\Facades\Settings;

/**
 * Заполняет N слотов апсейла в порядке приоритета источников:
 * dress.upsell.priority или dress.upsell.priority_per_kind.{up|cross}
 *
 * Источники: links | bought_together | tags | category
 * Фильтр по стране: ak_catalog(country_code, is_visible=1)
 * Логика up: только дороже (если включено)
 */
class UpsellService
{
    /**
     * @param int[]  $anchors      якоря (на карточке это [productId], в корзине — все id)
     * @param string $country      код страны
     * @param string $kind         'up' | 'cross'
     * @param string $placement    mini_cart | cart | checkout (берём лимит из dress.upsell.placements.{placement})
     * @param int[]  $exclude      кого исключить дополнительно (например, содержимое корзины; по умолчанию = anchors)
     */
    public function fill(array $anchors, string $country, string $kind, string $placement, array $exclude = []): Collection
    {
        if (!Settings::get('dress.upsell.enabled', true)) {
            return collect();
        }

        $placements = (array) Settings::get('dress.upsell.placements', [
            'mini_cart' => 8, 'cart' => 12, 'checkout' => 12,
        ]);
        $capacity = (int) ($placements[$placement] ?? 12);

        $priority = Settings::get("dress.upsell.priority_per_kind.{$kind}")
                  ?? Settings::get('dress.upsell.priority', ['links','bought_together','tags','category']);
        $priority = array_values(array_filter((array) $priority, fn($s) => in_array($s, ['links','bought_together','tags','category'], true)));

        $results = [];
        $seen    = array_fill_keys($exclude ?: $anchors, true);

        foreach ($priority as $source) {
            if (count($results) >= $capacity) break;

            $candidates = $this->fetchFrom($source, $anchors, $country, $kind, $capacity);

            // доступность в стране (батчем)
            $available = $this->availableInCountry($candidates, $country);

            // логика up (дороже)
            $filtered = [];
            foreach ($candidates as $pid) {
                if (isset($seen[$pid])) continue;
                if (!isset($available[$pid])) continue;
                if (in_array($pid, $anchors, true)) continue;

                if ($kind === 'up' && Settings::get('dress.upsell.only_more_expensive', false)) {
                    if (!$this->isMoreExpensive($pid, $anchors, $country)) continue;
                }

                $filtered[] = $pid;
            }

            foreach ($filtered as $pid) {
                $results[] = $pid;
                $seen[$pid] = true;
                if (count($results) >= $capacity) break;
            }
        }

        return $this->hydrate($results, $country);
    }

    /** Возвращает отсортированный список кандидатов для источника. */
    protected function fetchFrom(string $source, array $anchors, string $country, string $kind, int $capacity): array
    {
        return match ($source) {
            'links'           => $this->fromLinks($anchors, $kind, $capacity * 3),
            'bought_together' => $this->fromBoughtTogether($anchors, $country, $capacity * 5),
            'tags'            => $this->fromTags($anchors, $capacity * 5),
            'category'        => $this->fromCategory($anchors, $capacity * 5),
            default           => [],
        };
    }

    /** Ручные связи (kind = up|cross), сортировка по priority DESC */
    protected function fromLinks(array $anchors, string $kind, int $limit): array
    {
        return DB::table('ak_product_links')
            ->whereIn('product_id', $anchors)
            ->where('kind', $kind)
            ->orderByDesc('priority')
            ->limit($limit)
            ->pluck('linked_product_id')
            ->toArray();
    }

    /**
     * Покупали вместе: сперва страновые, затем добор глобальными (NULL).
     * Сортировка score DESC. Возвращаем объединённый список без дублей.
     */
    protected function fromBoughtTogether(array $anchors, string $country, int $limit): array
    {
        $local = DB::table('ak_bought_together')
            ->whereIn('product_id', $anchors)
            ->where('country_code', $country)
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('with_product_id')
            ->toArray();

        if (count($local) >= $limit) {
            return $local;
        }

        $global = DB::table('ak_bought_together')
            ->whereIn('product_id', $anchors)
            ->whereNull('country_code')
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('with_product_id')
            ->toArray();

        // объединяем с сохранением порядка: локальные первее
        $seen = [];
        $out  = [];
        foreach ([$local, $global] as $chunk) {
            foreach ($chunk as $pid) {
                if (isset($seen[$pid])) continue;
                $out[] = $pid;
                $seen[$pid] = true;
                if (count($out) >= $limit) break 2;
            }
        }
        return $out;
    }

    /** Кандидаты по совпадающим тегам, сортировка по числу совпадений (DESC). */
    protected function fromTags(array $anchors, int $limit): array
    {
        return DB::table('ak_product_tag as t1')
            ->join('ak_product_tag as t2','t1.tag_id','=','t2.tag_id')
            ->whereIn('t1.product_id', $anchors)
            ->whereNotIn('t2.product_id', $anchors)
            ->select('t2.product_id', DB::raw('COUNT(*) as matches'))
            ->groupBy('t2.product_id')
            ->orderByDesc('matches')
            ->limit($limit)
            ->pluck('t2.product_id')
            ->toArray();
    }

    /** Кандидаты по общим категориям, сортировка по числу совпадений (DESC). */
    protected function fromCategory(array $anchors, int $limit): array
    {
        return DB::table('ak_product_category as c1')
            ->join('ak_product_category as c2','c1.category_id','=','c2.category_id')
            ->whereIn('c1.product_id', $anchors)
            ->whereNotIn('c2.product_id', $anchors)
            ->select('c2.product_id', DB::raw('COUNT(*) as matches'))
            ->groupBy('c2.product_id')
            ->orderByDesc('matches')
            ->limit($limit)
            ->pluck('c2.product_id')
            ->toArray();
    }

    /** Карта доступных в стране product_id => true (ak_catalog). */
    protected function availableInCountry(array $ids, string $country): array
    {
        if (!$ids) return [];
        $list = DB::table('ak_catalog')
            ->where('country_code', $country)
            ->where('is_visible', 1)
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->toArray();
        return $list ? array_fill_keys($list, true) : [];
    }

    /** Проверка «дороже якоря» для up-sell: price(candidate) >= max(anchorPrice) * factor */
    protected function isMoreExpensive(int $candidateId, array $anchors, string $country): bool
    {
        $factor = (float) Settings::get('dress.upsell.more_expensive_factor', 1.0);
        if ($factor <= 1.0) return true; // включено, но фактор не повышает — пропускаем

        $anchorMax = DB::table('ak_catalog')
            ->where('country_code', $country)
            ->whereIn('product_id', $anchors)
            ->max('price');

        if (!$anchorMax) return true;

        $candidate = DB::table('ak_catalog')
            ->where('country_code', $country)
            ->where('product_id', $candidateId)
            ->value('price');

        if ($candidate === null) return false;

        return $candidate >= $anchorMax * $factor;
    }

    /** Возвращаем карточки из ak_catalog в том же порядке, что и ids. */
    protected function hydrate(array $ids, string $country): Collection
    {
        if (!$ids) return collect();

        $rows = DB::table('ak_catalog')
            ->where('country_code', $country)
            ->whereIn('product_id', $ids)
            ->select('product_id','title','slug','price','old_price','image_main','in_stock')
            ->get()
            ->keyBy('product_id');

        return collect($ids)
            ->map(fn($id) => $rows->get($id))
            ->filter()
            ->values();
    }
}
