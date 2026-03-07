<?php

namespace Backpack\Store\app\Services\Faq;

use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\FaqTemplate;
use Backpack\Store\app\Models\Product;
use Illuminate\Support\Collection;

class ProductFaqResolver
{
    public function resolveForProduct($resource, ?string $locale = null): array
    {
        $product = $this->resolveProduct($resource);

        if (!$product) {
            return [];
        }

        $locale = $this->resolveLocale($locale);
        $categories = $this->resolveCategoriesWithAncestors($product);

        $rows = [];
        $loadedTemplateIds = [];

        $baseProduct = $this->resolveBaseProduct($product);

        if ($baseProduct && (int) $baseProduct->id !== (int) $product->id) {
            $rows = array_merge($rows, $baseProduct->getFaqItems($locale));
            $rows = array_merge($rows, $this->collectTemplateItems(
                $baseProduct->getFaqTemplateIds(),
                $loadedTemplateIds,
                $locale
            ));
        }

        $rows = array_merge($rows, $product->getFaqItems($locale));
        $rows = array_merge($rows, $this->collectTemplateItems(
            $product->getFaqTemplateIds(),
            $loadedTemplateIds,
            $locale
        ));

        foreach ($categories as $category) {
            $rows = array_merge($rows, $category->getFaqItems($locale));
            $rows = array_merge($rows, $this->collectTemplateItems(
                $category->getFaqTemplateIds(),
                $loadedTemplateIds,
                $locale
            ));
        }

        return $this->groupRows($rows);
    }

    protected function resolveProduct($resource): ?Product
    {
        if ($resource instanceof Product && !($resource instanceof Catalog)) {
            $resource->loadMissing('categories');
            return $resource;
        }

        $productId = (int) ($resource->product_id ?? $resource->id ?? 0);
        if ($productId <= 0) {
            return null;
        }

        return Product::query()
            ->with('categories')
            ->find($productId);
    }

    protected function resolveBaseProduct(Product $product): ?Product
    {
        $parentId = (int) ($product->parent_id ?? 0);

        if ($parentId <= 0) {
            return $product;
        }

        return Product::query()
            ->with('categories')
            ->find($parentId);
    }

    protected function resolveCategoriesWithAncestors(Product $product): Collection
    {
        $product->loadMissing('categories');

        $categoryIds = [];
        $seen = [];

        foreach ($product->categories as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            if (!isset($seen[$category->id])) {
                $seen[$category->id] = true;
                $categoryIds[] = (int) $category->id;
            }

            if (!method_exists($category, 'getParentNode')) {
                continue;
            }

            $nodes = $category->getParentNode($category);
            foreach ($nodes as $node) {
                $id = (int) ($node->id ?? 0);
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $categoryIds[] = $id;
                }
            }
        }

        if (empty($categoryIds)) {
            return collect();
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('lft')
            ->get();
    }

    protected function collectTemplateItems(array $ids, array &$loadedTemplateIds, string $locale): array
    {
        $ordered = [];

        foreach ($ids as $id) {
            $templateId = (int) $id;
            if ($templateId <= 0 || in_array($templateId, $loadedTemplateIds, true)) {
                continue;
            }

            $loadedTemplateIds[] = $templateId;
            $ordered[] = $templateId;
        }

        if (empty($ordered)) {
            return [];
        }

        $templates = FaqTemplate::query()
            ->where('is_active', true)
            ->whereIn('id', $ordered)
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($ordered as $id) {
            /** @var FaqTemplate|null $template */
            $template = $templates->get($id);
            if (!$template) {
                continue;
            }

            $rows = array_merge($rows, $template->getFaqItems($locale));
        }

        return $rows;
    }

    protected function groupRows(array $rows): array
    {
        $groups = [];
        $groupCounter = 0;
        $itemCounter = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $question = trim((string) ($row['question'] ?? ($row['q'] ?? '')));
            $answer = (string) ($row['answer'] ?? ($row['a'] ?? ''));

            if ($question === '' || trim(strip_tags($answer)) === '') {
                continue;
            }

            $title = trim((string) ($row['group_title'] ?? ''));
            if ($title === '') {
                $title = 'FAQ';
            }

            $groupKey = mb_strtolower($title);

            if (!isset($groups[$groupKey])) {
                $groupCounter++;
                $groups[$groupKey] = [
                    'id' => 'g'.$groupCounter,
                    'title' => $title,
                    'items' => [],
                ];
            }

            $itemCounter++;
            $groups[$groupKey]['items'][] = [
                'id' => 'i'.$itemCounter,
                'q' => $question,
                'a' => $answer,
            ];
        }

        return array_values($groups);
    }

    protected function resolveLocale(?string $locale = null): string
    {
        if (is_string($locale) && $locale !== '') {
            return $this->normalizeLocaleKey($locale);
        }

        $fromBackpack = function_exists('backpack_translatable_request_locale')
            ? backpack_translatable_request_locale(null)
            : null;

        if (is_string($fromBackpack) && $fromBackpack !== '') {
            return $this->normalizeLocaleKey($fromBackpack);
        }

        return $this->normalizeLocaleKey((string) app()->getLocale());
    }

    protected function normalizeLocaleKey(string $locale): string
    {
        $normalized = trim(mb_strtolower($locale));
        if ($normalized === '') {
            return (string) config('app.fallback_locale', 'en');
        }

        if (str_contains($normalized, '-')) {
            $normalized = explode('-', $normalized)[0] ?? $normalized;
        }

        if (str_contains($normalized, '_')) {
            $normalized = explode('_', $normalized)[0] ?? $normalized;
        }

        return $normalized;
    }
}
