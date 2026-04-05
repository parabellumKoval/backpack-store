<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\ListEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\AnchorResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductListsController extends Controller
{

    public function __construct(protected AnchorResolver $anchorResolver)
    {
    }

    /** GET /api/lists/{page} */
    public function index(Request $request, string $page, ListEngine $engine)
    {
        $country = (string) ($request->query('country') ?: \Store::country());
        $storefront = (string) \Store::storefront();
        $lang = (string) ($request->query('lang') ?: app()->getLocale());
        $capacityOverride = $this->queryInt($request, 'capacity_override');
        $pageNumber = $this->queryInt($request, 'page');
        $perPage = $this->queryInt($request, 'per_page');

        $anchors = $this->resolveAnchors($request);

        $context = new ListRequestContext(
            $page,
            $country,
            $storefront,
            $lang,
            $anchors,
            $capacityOverride,
            $pageNumber,
            $perPage
        );

        $lists = ProductList::query()
            ->activeForCountry($country)
            ->where('page', $page)
            ->orderByRaw('COALESCE(lft, 0) ASC')
            ->orderBy('id')
            ->get();

        $response = [];
        foreach ($lists as $list) {
            $result = $engine->build($list, $context);
            $response[] = $this->buildPayload($list, $lang, $result);
        }

        return response()->json($response);
    }

    /** GET /api/lists/{page}/{slug} */
    public function show(Request $request, string $page, string $slug, ListEngine $engine)
    {
        $country = (string) ($request->query('country') ?: \Store::country());
        $storefront = (string) \Store::storefront();
        $lang = (string) ($request->query('lang') ?: app()->getLocale());
        $capacityOverride = $this->queryInt($request, 'capacity_override');
        $pageNumber = $this->queryInt($request, 'page');
        $perPage = $this->queryInt($request, 'per_page');

        $list = ProductList::query()
            ->activeForCountry($country)
            ->where('page', $page)
            ->where('slug', $slug)
            ->firstOrFail();

        $anchors = $this->resolveAnchors($request);

        $context = new ListRequestContext(
            $page,
            $country,
            $storefront,
            $lang,
            $anchors,
            $capacityOverride,
            $pageNumber,
            $perPage
        );

        $result = $engine->build($list, $context);

        return response()->json($this->buildPayload($list, $lang, $result));
    }

    protected function resolveAnchors(Request $request)
    {
        $model = data_get($request->query(), 'anchors.model');
        $ids = data_get($request->query(), 'anchors.ids');

        return $this->anchorResolver->resolve($model, $ids);
    }

    protected function buildPayload(ProductList $list, string $lang, array $result): array
    {
        return [
            'slug' => $list->slug,
            'title' => $this->translateField($list->title, $lang),
            'button_text' => $this->translateField($list->button_text, $lang),
            'full_url' => $this->translateField($list->full_url, $lang),
            'capacity' => $list->capacity,
            'items' => $result['items'],
            'meta' => $result['meta'] ?? [],
        ];
    }

    protected function translateField($value, string $lang): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                return $value;
            }
        }
        if (is_array($value)) {
            $fallback = app()->getFallbackLocale();
            return $value[$lang] ?? ($fallback && isset($value[$fallback]) ? $value[$fallback] : (reset($value) ?: null));
        }
        return null;
    }

    protected function queryInt(Request $request, string $key): ?int
    {
        if (!$request->has($key)) {
            return null;
        }
        $value = $request->query($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }
}
