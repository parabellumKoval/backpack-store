<?php

namespace Backpack\Store\app\Services\Catalog;

use Backpack\Store\app\Job\SyncCatalogProductJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

class CatalogSyncTouch
{
    public static function touch(int $productId, int $delaySeconds = 5): void
    {
        $useCachedTables = (bool) \Settings::get('dress.store.catalog_table_cache', false);

        if($useCachedTables) {
            DB::afterCommit(function () use ($productId, $delaySeconds) {
                // один уникальный job с небольшой задержкой (дебаунс)
                SyncCatalogProductJob::dispatch($productId)->delay(now()->addSeconds($delaySeconds));
            });
        }
    }
}
