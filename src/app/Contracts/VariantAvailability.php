<?php
namespace Backpack\Store\app\Contracts;

use Illuminate\Database\Query\Builder as Qb;

interface VariantAvailability {
    /**
     * @param Qb     $base        FROM ak_products p WHERE p.is_active=1
     * @param \Closure $existsSelf   fn(Qb $b): Qb   — EXISTS(sp_ok WHERE product_id = p.id)
     * @param \Closure $existsChild  fn(Qb $b): Qb   — EXISTS( child c … AND EXISTS(sp_ok WHERE c.id) )
     * @param string  $t          имя таблицы продуктов
     * @return Qb     подзапрос SELECT id (u.id)
     */
    public function idsSubquery(Qb $base, \Closure $existsSelf, \Closure $existsChild, string $t): Qb;
}
