<?php

namespace Backpack\Store\app\Services\Catalog;

use Backpack\Store\app\Job\SyncCatalogProductJob;
use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Services\Catalog\CatalogCacheService;

class CatalogSyncTouch
{
    public static function touch(int $productId, int $delaySeconds = 5, bool $forceImmediate = false): void
    {
        if ($productId <= 0) {
            return;
        }

        if (!\Store::isCacheTable()) {
            return;
        }

        DB::afterCommit(function () use ($productId, $delaySeconds, $forceImmediate) {
            $shouldSyncInline = $forceImmediate
                || app()->environment('local', 'testing')
                || config('queue.default') === 'sync';

            if ($shouldSyncInline) {
                app(CatalogCacheService::class)->syncProduct($productId);
                return;
            }

            // один уникальный job с небольшой задержкой (дебаунс)
            $job = SyncCatalogProductJob::dispatch($productId);
            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($delaySeconds));
            }
        });
    }
}
