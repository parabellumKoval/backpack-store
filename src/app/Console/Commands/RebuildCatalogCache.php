<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
// use Backpack\Store\app\Services\Catalog\CatalogCacheBuilder;
use Backpack\Store\app\Services\Catalog\CatalogCacheService;

class RebuildCatalogCache extends Command
{
    protected $signature = 'store:catalog:rebuild 
                            {--country=* : Ограничить странами (можно несколько)} 
                            {--chunk=1000 : Размер чанка}
                            ';
    protected $description = 'Пересобрать кеш каталога (ak_catalog) для мультирегионального режима';

    public function handle(CatalogCacheService $builder): int
    {
        $countries = $this->option('country');
        $chunk = (int) $this->option('chunk');

        $this->info('Rebuilding ak_catalog...');
        // $builder->rebuild(!empty($countries) ? $countries : null, $chunk);
        $builder->rebuildAll(!empty($countries) ? $countries : null, $chunk);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
