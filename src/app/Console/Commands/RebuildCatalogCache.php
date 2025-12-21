<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Backpack\Store\app\Services\Catalog\CatalogCacheService;
use Backpack\Store\app\Job\RebuildCatalogCacheJob;

class RebuildCatalogCache extends Command
{
    protected $signature = 'store:catalog:rebuild 
                            {--country=* : Ограничить странами (можно несколько)} 
                            {--chunk=1000 : Размер чанка}
                            {--queue : Отправить задачу в очередь, чтобы выполнить в фоне}
                            ';
    protected $description = 'Пересобрать кеш каталога (ak_catalog) для мультирегионального режима';

    public function handle(CatalogCacheService $builder): int
    {
        $countries = $this->option('country');
        $chunk = (int) $this->option('chunk');
        $useQueue = (bool) $this->option('queue');

        if ($useQueue) {
            RebuildCatalogCacheJob::dispatch(!empty($countries) ? $countries : null, $chunk);
            $this->info('Catalog rebuild job dispatched to the queue.');

            return self::SUCCESS;
        }

        $this->info('Rebuilding ak_catalog...');
        // $builder->rebuild(!empty($countries) ? $countries : null, $chunk);
        $builder->rebuildAll(!empty($countries) ? $countries : null, $chunk);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
