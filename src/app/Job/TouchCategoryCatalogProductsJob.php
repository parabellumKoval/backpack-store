<?php

namespace Backpack\Store\app\Job;

use Backpack\Store\app\Services\Catalog\CategoryCatalogTouchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TouchCategoryCatalogProductsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public function __construct(public int $categoryId)
    {
        $this->onQueue((string) config('queue.names.ak_catalog', 'ak_catalog'));
        $this->afterCommit();
    }

    public function handle(CategoryCatalogTouchService $touchService): void
    {
        $touchService->touchByCategoryId($this->categoryId);
    }

    public function uniqueId(): string
    {
        return 'catalog-touch:category:'.$this->categoryId;
    }

    public function uniqueFor(): int
    {
        return 60;
    }
}
