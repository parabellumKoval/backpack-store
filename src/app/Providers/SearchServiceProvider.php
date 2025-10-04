<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Cache, Bus};

use Meilisearch\Client as MeiliClient;

use Backpack\Store\app\Services\Search\MeiliSettingsBuilder;
use Backpack\Store\app\Job\ApplyMeiliSettingsJob;

class SearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Если поиск не включен – выходим сразу
        if (!\Settings::get('dress.search.enabled')
            || \Settings::get('dress.search.driver', 'meilisearch') !== 'meilisearch'
            || !class_exists(\Laravel\Scout\EngineManager::class)
        ) {
            return;
        }

        // Проброс в Scout – только те ключи, которые он ожидает
        config()->set('scout.driver', 'meilisearch');
        config()->set('scout.queue', false);
        config()->set('scout.meilisearch.host', \Settings::get('dress.search.meilisearch.host'));
        // config()->set('scout.meilisearch.key',  \Settings::get('dress.search.meilisearch.key'));

        // включай поведение флагом, чтобы на DEV было удобно
        if (!\Settings::get('dress.search.auto_apply_on_boot', false)) {
            return;
        }

        // собрали настройки и хэш
        $settings = MeiliSettingsBuilder::build();
        $hash     = sha1(json_encode($settings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $cacheKey = 'meili:settings:hash';
        if (Cache::get($cacheKey) === $hash) {
            return; // уже применены – ничего не делаем
        }

        // антидубль на многопроцессной среде
        $lock = Cache::lock('meili:settings:apply', 30);
        if (!$lock->get()) {
            return; // кто-то уже запускает применение
        }

        try {
            // запускаем фоновую джобу (без блокировки запроса!)
            dispatch(new ApplyMeiliSettingsJob($settings));
            Cache::put($cacheKey, $hash, 86400);
        } finally {
            $lock->release();
        }
    }
}
