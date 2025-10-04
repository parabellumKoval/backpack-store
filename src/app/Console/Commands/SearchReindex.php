<?php
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Backpack\Store\app\Models\Catalog;

class SearchReindex extends Command
{
    protected $signature = 'store:search:reindex 
                            {--flush : Очистить индекс перед заливкой} 
                            {--chunk=500 : Размер пачки}';

    protected $description = 'Переиндексировать все товары (через ->searchable(), без scout:import)';

    public function handle(): int
    {
        if (!\Settings::get('dress.search.enabled')
            || \Settings::get('dress.search.driver', 'meilisearch') !== 'meilisearch') {
            $this->warn('Поиск отключён или драйвер не meilisearch.');
            return 0;
        }

        // опционально чистим индекс
        if ($this->option('flush')) {
            $this->info('Очистка индекса…');
            // flush безопасно сработает, если трейт Scout есть; если нет — просто пропусти
            try {
                Catalog::query()->unsearchable();
            } catch (\Throwable $e) {
                $this->warn('unsearchable() недоступен, пропускаю очистку: '.$e->getMessage());
            }
        }

        $chunk = (int)$this->option('chunk');

        $this->info('Индексация всех продуктов батчами по '.$chunk.' …');

        $countries = Catalog::query()
            ->whereNotNull('country_code')
            ->distinct()
            ->pluck('country_code')
            ->all();

        foreach ($countries as $code) {
            // по желанию: вывести "создаётся индекс {products}_{code}"
            Catalog::query()
                ->where('country_code', $code)
                ->where('is_available', 1)
                ->orderBy('id')
                ->chunkById(1000, function ($chunk) {
                    $chunk->searchable();
                });
        }

        $this->info('Готово.');
        return 0;
    }
}
