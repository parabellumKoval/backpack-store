<?php

namespace Backpack\Store\app\Services\Faq;

use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;
use Backpack\Store\Facades\Store;
use Illuminate\Support\Facades\DB;

class FaqTemplateCatalogTouchService
{
    public function touchByTemplateId(int $templateId): void
    {
        if ($templateId <= 0) {
            return;
        }

        if (!Store::isCacheTable()) {
            return;
        }

        $productIds = $this->collectProductIdsByTemplate($templateId);
        foreach ($productIds as $productId) {
            CatalogSyncTouch::touch((int) $productId);
        }

        $categoryIds = $this->collectCategoryIdsByTemplate($templateId);
        if (!empty($categoryIds)) {
            $this->touchProductsByCategories($categoryIds);
        }
    }

    protected function collectProductIdsByTemplate(int $templateId): array
    {
        $ids = [];

        DB::table('ak_products')
            ->select('id', 'parent_id', 'extras')
            ->whereNotNull('extras')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($templateId, &$ids) {
                foreach ($rows as $row) {
                    $templateIds = $this->extractTemplateIdsFromExtras($row->extras ?? null);
                    if (!in_array($templateId, $templateIds, true)) {
                        continue;
                    }

                    $ids[] = (int) $row->id;
                    if (!empty($row->parent_id)) {
                        $ids[] = (int) $row->parent_id;
                    }
                }

                return true;
            });

        return array_values(array_unique(array_filter($ids)));
    }

    protected function collectCategoryIdsByTemplate(int $templateId): array
    {
        $ids = [];

        DB::table('ak_product_categories')
            ->select('id', 'extras')
            ->whereNotNull('extras')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($templateId, &$ids) {
                foreach ($rows as $row) {
                    $templateIds = $this->extractTemplateIdsFromExtras($row->extras ?? null);
                    if (!in_array($templateId, $templateIds, true)) {
                        continue;
                    }

                    $ids[] = (int) $row->id;
                }

                return true;
            });

        return array_values(array_unique(array_filter($ids)));
    }

    protected function touchProductsByCategories(array $categoryIds): void
    {
        $allCategoryIds = $this->collectSubtreeCategoryIds($categoryIds);
        if (empty($allCategoryIds)) {
            return;
        }

        DB::table('ak_category_product')
            ->select('id', 'product_id')
            ->whereIn('category_id', $allCategoryIds)
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $productIds = collect($rows)
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if (empty($productIds)) {
                    return true;
                }

                $childIds = DB::table('ak_products')
                    ->whereIn('parent_id', $productIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $allProductIds = array_values(array_unique(array_merge($productIds, $childIds)));

                foreach ($allProductIds as $productId) {
                    CatalogSyncTouch::touch($productId);
                }

                return true;
            });
    }

    protected function collectSubtreeCategoryIds(array $rootIds): array
    {
        $roots = array_values(array_unique(array_map('intval', $rootIds)));
        $roots = array_values(array_filter($roots, fn ($id) => $id > 0));

        if (empty($roots)) {
            return [];
        }

        $rows = DB::table('ak_product_categories')
            ->select('id', 'parent_id', 'lft', 'rgt')
            ->get();

        if ($rows->isEmpty()) {
            return $roots;
        }

        $byId = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $parentId = $row->parent_id ? (int) $row->parent_id : null;

            $byId[$id] = [
                'id' => $id,
                'parent_id' => $parentId,
                'lft' => $row->lft !== null ? (int) $row->lft : null,
                'rgt' => $row->rgt !== null ? (int) $row->rgt : null,
            ];

            if ($parentId !== null) {
                $childrenByParent[$parentId][] = $id;
            }
        }

        $result = [];

        foreach ($roots as $rootId) {
            if (!isset($byId[$rootId])) {
                continue;
            }

            $root = $byId[$rootId];
            $lft = $root['lft'];
            $rgt = $root['rgt'];

            if ($lft !== null && $rgt !== null && $lft <= $rgt) {
                foreach ($byId as $id => $node) {
                    if ($node['lft'] === null || $node['rgt'] === null) {
                        continue;
                    }

                    if ($node['lft'] >= $lft && $node['rgt'] <= $rgt) {
                        $result[] = $id;
                    }
                }

                continue;
            }

            $queue = [$rootId];
            $visited = [];

            while (!empty($queue)) {
                $current = array_shift($queue);
                if (isset($visited[$current])) {
                    continue;
                }

                $visited[$current] = true;
                $result[] = $current;

                foreach ($childrenByParent[$current] ?? [] as $childId) {
                    $queue[] = $childId;
                }
            }
        }

        return array_values(array_unique($result));
    }

    protected function extractTemplateIdsFromExtras($extras): array
    {
        $payload = $this->normalizeArrayPayload($extras);
        $rows = $this->normalizeArrayPayload($payload['faq_template_links'] ?? []);

        $ids = [];

        foreach ($rows as $row) {
            $candidate = is_array($row) ? ($row['template_id'] ?? null) : $row;
            if (!is_numeric($candidate)) {
                continue;
            }

            $id = (int) $candidate;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function normalizeArrayPayload($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : [];
    }
}
