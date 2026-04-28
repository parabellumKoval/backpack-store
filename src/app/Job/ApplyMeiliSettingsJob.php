<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Meilisearch\Client;

class ApplyMeiliSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->afterCommit();          // применять только после коммита транзакции запроса
        // $this->onQueue('search');   // опционально отдельная очередь
    }

    public function handle(): void
    {
        $client = new Client(
            \Settings::get('dress.search.meilisearch.host', 'http://localhost:7700'),
            \Settings::get('dress.search.meilisearch.key')
        );

        $indexes = self::resolveIndexUids();

        \Log::info('Setting ' . print_r($this->settings, true));

        $tasks = [];
        foreach (array_unique($indexes) as $uid) {
            $task = $client->index($uid)->updateSettings($this->settings);
            $tasks[] = [$uid, $task['taskUid']];
        }

        // дождёмся применения (не в веб-запросе, а в queue-воркере)
        foreach ($tasks as [$uid, $taskUid]) {
            $client->index($uid)->waitForTask($taskUid, 15000, 100);
        }
    }

    protected static function resolveIndexUids(): array
    {
        $base = 'products';
        $indexes = [$base];

        if (!class_exists(\Store::class)) {
            return $indexes;
        }

        $countries = array_keys((array) \Store::countries());
        $storefronts = array_keys((array) \Store::storefronts());

        if (empty($storefronts)) {
            $storefronts = [\Store::defaultStorefront()];
        }

        foreach ($countries as $countryCode) {
            $indexes[] = "{$base}_{$countryCode}";

            foreach ($storefronts as $storefront) {
                $indexes[] = "{$base}_{$countryCode}_{$storefront}";
            }
        }

        return array_values(array_unique($indexes));
    }
}
