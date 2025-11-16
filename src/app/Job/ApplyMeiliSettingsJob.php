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

        $base = 'products';
        $indexes = [$base];

        if (\Settings::get('dress.search.suffix_per_country', false) && class_exists(\Store::class)) {
            foreach (\Store::countries() as $code => $_) {
                $indexes[] = "{$base}_{$code}";
            }
        }

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
}
