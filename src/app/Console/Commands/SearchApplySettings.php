<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client as MeiliClient;

use \Backpack\Store\app\Services\Search\MeiliSettingsBuilder;
use \Backpack\Store\app\Job\ApplyMeiliSettingsJob;

class SearchApplySettings extends Command
{
    protected $signature = 'store:search:apply {--reindex : Очистить и переиндексировать продукты}';
    protected $description = 'Применяет настройки Meilisearch и (опц.) переиндексирует данные';

    public function handle(): int
    {
        // 1) Собираем настройки индекс(ов) через готовый билдер
        $settings = MeiliSettingsBuilder::build();

        // 2) Запускаем фоновую джобу, которая сама применит настройки ко всем нужным индексам
        //    (внутри джобы: updateSettings + waitForTask)
        dispatch(new ApplyMeiliSettingsJob($settings));

        $this->info('Meilisearch settings apply job dispatched.');

        // 3) Опционально — полная переиндексация отдельной твоей командой
        if ($this->option('reindex')) {
            $this->warn('Reindex flag is ON. Running: store:search:reindex …');
            // если нужно — можно пробросить свои опции в reindex-команду
            \Artisan::call('store:search:reindex', [], $this->output);
        }

        
        \Log::info('SearchApplySettings');
        
        $this->info('Done.');
        return self::SUCCESS;
    }
}
