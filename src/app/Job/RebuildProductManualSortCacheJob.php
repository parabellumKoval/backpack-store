<?php

namespace Backpack\Store\app\Job;

use Backpack\Store\app\Services\Product\ProductManualSortCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildProductManualSortCacheJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(int $delaySeconds = 5)
    {
        $this->afterCommit();
        $this->delay(now()->addSeconds($delaySeconds));
    }

    public function uniqueId(): string
    {
        return 'product-manual-sort-cache-rebuild';
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(ProductManualSortCacheService $cacheService): void
    {
        $cacheService->rebuild();
    }
}

