<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

// use Backpack\Store\app\Models\Product;
// use Backpack\Store\app\Models\Catalog;

use Backpack\Store\app\Http\Requests\Api\SearchProductsRequest;
use \Backpack\Store\app\Services\Search\SearchService;

class SearchController extends Controller
{
    /**
     * Поиск товаров (Meilisearch -> fallback DB)
     */
    public function products(SearchProductsRequest $r): JsonResponse
    {
        $q = trim((string)$r->get('q', ''));
        $country = \Store::context()->country;

        $res = app(SearchService::class)
                ->searchProducts($q, $country, (int)$r->get('per_page', 20));

        return response()->json($res);
    }

    /**
     * Мини-мэппер ответа под фронт (быстро и без отдельного Resource)
     */
    // protected function mapProduct(Catalog $p, string $locale, ?string $country): array
    // {
    //     return [
    //         'id'           => $p->id,
    //         'name'         => $p->getTranslation('name', $locale),
    //         'brand'        => optional($p->brand)->name,
    //         'category'     => optional($p->category)->name,
    //         'price'        => $p->price,
    //         'in_stock'     => (bool) $p->in_stock,
    //         'country_code' => $country,
    //         'slug'         => $p->slug ?? null,
    //     ];
    // }

    /**
     * Лёгкая проверка доступности Meili (без падений запроса)
     */
    // protected function meiliAvailable(): bool
    // {
    //     try {
    //         // Если ключа нет — клиент создаём без него
    //         $host = \Settings::get('dress.search.meilisearch.host');
    //         $key  = \Settings::get('dress.search.meilisearch.key');

    //         $client = $key
    //             ? new \Meilisearch\Client($host, $key)
    //             : new \Meilisearch\Client($host);

    //         $client->health(); // ping
    //         return true;
    //     } catch (\Throwable $e) {
    //         // Логируем и спокойно уходим на БД
    //         \Log::warning('Meilisearch not available: '.$e->getMessage());
    //         return false;
    //     }
    // }
}
