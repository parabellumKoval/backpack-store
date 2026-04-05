<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Backpack\Store\app\Services\Catalog\CatalogCacheService;
use Backpack\Store\app\Job\RebuildCatalogCacheJob;

class RebuildCatalogCache extends Command
{
    protected $signature = 'store:catalog:rebuild 
                            {--country=* : Ограничить странами (можно несколько)} 
                            {--storefront=* : Ограничить storefront-ами (можно несколько)}
                            {--chunk=1000 : Размер чанка}
                            {--queue : Отправить задачу в очередь, чтобы выполнить в фоне}
                            ';
    protected $description = 'Пересобрать кеш каталога (ak_catalog) для мультирегионального режима';

    public function handle(CatalogCacheService $builder): int
    {
        $countries = $this->option('country');
        $storefronts = $this->option('storefront');
        $chunk = (int) $this->option('chunk');
        $useQueue = (bool) $this->option('queue');

        if ($useQueue) {
            RebuildCatalogCacheJob::dispatch(!empty($countries) ? $countries : null, $chunk, !empty($storefronts) ? $storefronts : null);
            $this->info('Catalog rebuild job dispatched to the queue.');

            return self::SUCCESS;
        }

        $this->info('Rebuilding ak_catalog...');
        $builder->rebuildAll(!empty($countries) ? $countries : null, $chunk, null, !empty($storefronts) ? $storefronts : null);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
