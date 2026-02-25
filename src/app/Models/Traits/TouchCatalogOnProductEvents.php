<?php

namespace Backpack\Store\app\Models\Traits;

use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;
use Backpack\Store\app\Job\RebuildProductManualSortCacheJob;

trait TouchCatalogOnProductEvents
{
    public static function bootTouchCatalogOnProductEvents(): void
    {
        $cb = function ($model) {
            CatalogSyncTouch::touch((int) $model->getKey());
            if (!empty($model->parent_id)) {
                CatalogSyncTouch::touch((int) $model->parent_id);
            }
            RebuildProductManualSortCacheJob::dispatch();
        };

        static::saved($cb);
        static::deleted($cb);
    }
}
