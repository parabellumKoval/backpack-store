<?php
namespace Backpack\Store\app\Services\Product;

use Illuminate\Database\Eloquent\Builder as Eb;
use Illuminate\Database\Query\Builder as Qb;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Contracts\SupplierFilter;
use Backpack\Store\app\Contracts\VariantAvailability;
use Backpack\Store\app\Models\Category;

class AvailabilityFilter
{
    public function scopeAvailable(Eb $q, ?string $country = null): Eb
    {
        $t       = $q->getModel()->getTable();
        $country = $country ?: \Store::context()->country;
        $visibleCategoryIds = Category::visibleIdsForContext($country, null, true);

        if (empty($visibleCategoryIds)) {
            return $q->whereRaw('1=0');
        }

        /** @var \Backpack\Store\Contracts\SupplierFilter $sup */
        $sup = app(SupplierFilter::class);

        /** @var \Backpack\Store\Contracts\VariantAvailability $variants */
        $variants = app(VariantAvailability::class);

        // sp_ok(product_id): активные поставщики (и страна — если мульти)
        $spOk = $sup->spOk($country);

        // активные категории, доступные для страны (или все активные, если страна не задана)
        // База для веток: FROM ak_products p WHERE p.is_active=1
        /** @var Qb $base */
        $base = DB::table("$t as p")
            ->where('p.is_active', 1)
            ->whereExists(function (Qb $cat) use ($visibleCategoryIds) {
                $cat->selectRaw('1')
                    ->from('ak_category_product as cp')
                    ->whereIn('cp.category_id', $visibleCategoryIds)
                    ->where(function ($where) {
                        $where->whereColumn('cp.product_id', 'p.id')
                            ->orWhere(function ($parent) {
                                $parent->whereNotNull('p.parent_id')
                                    ->whereColumn('cp.product_id', 'p.parent_id');
                            });
                    });
            });

        // EXISTS(sp_ok WHERE product_id = p.id)
        $existsSelf = function (Qb $b) use ($spOk) {
            $b->whereExists(function ($s) use ($spOk) {
                $s->selectRaw('1')->fromSub($spOk, 'sp_ok')->whereColumn('sp_ok.product_id','p.id');
            });
            return $b;
        };

        // EXISTS( child c … AND EXISTS(sp_ok WHERE c.id) )
        $existsChild = function (Qb $b) use ($spOk) {
            $b->whereExists(function ($s) use ($spOk) {
                $s->selectRaw('1')->fromSub($spOk, 'sp_ok')->whereColumn('sp_ok.product_id','c.id');
            });
            return $b;
        };

        // Построить UNION-подзапрос id в зависимости от варианта (vertical/horizontal)
        $idsSub = $variants->idsSubquery($base, $existsSelf, $existsChild, $t);

        // Ограничить основной Eloquent-запрос
        return $q->whereIn("$t.id", DB::query()->fromSub($idsSub, 'w')->select('w.id'));
    }
}
