<?php
namespace Backpack\Store\app\Job;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Backpack\Store\app\Services\Catalog\CatalogProductSync;


class SyncCatalogProductJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $productId;

    public function __construct(int $productId, int $delaySeconds = 5)
    {
        $this->productId = $productId;

        // этот вызов безопасно выставляет флаг "после коммита"
        $this->afterCommit();

        // опционально очередь
        // $this->onQueue('catalog');

        // небольшой дебаунс, чтобы схлопывать каскадные изменения
        $this->delay(now()->addSeconds($delaySeconds));
    }

    // Уникальность (чтобы не плодить дубликаты для одного product_id)
    public function uniqueId(): string
    {
        return 'catalog-sync:product:'.$this->productId;
    }

    // В течение минуты новые события будут схлопываться в один job
    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(CatalogProductSync $sync): void
    {
        $sync->sync($this->productId);
    }
}
