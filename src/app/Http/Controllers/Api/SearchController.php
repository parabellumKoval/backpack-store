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
    use \Backpack\Store\app\Traits\Resources;


    public function __construct() {
      self::resources_init();
    }
    
    /**
     * Поиск товаров (Meilisearch -> fallback DB)
     */
    public function products(SearchProductsRequest $r): JsonResponse
    {
        $q = trim((string)$r->get('q', ''));
        $resource = $r->get('resource', 'medium');
        $perPage = (int)$r->get('per_page', 20);

        $inStockOnly = $r->has('in_stock')
            ? (bool) $r->boolean('in_stock')
            : null;

        $country = \Store::context()->country;

        $res = app(SearchService::class)
                ->searchProducts($q, $country, $perPage, $inStockOnly);

        $dataResourced = !empty($res['data'])? self::$resources['product'][$resource]::collection($res['data']): [];

        return response()->json([
            'meta' => $res['meta'],
            'data' => $dataResourced
        ]);
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
