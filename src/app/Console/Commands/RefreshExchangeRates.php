<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Backpack\Store\app\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshExchangeRates extends Command
{
    protected $signature = 'store:currency:refresh {--no-db : Do not persist to DB}';
    protected $description = 'Fetch and cache exchange rates for the store';

    public function handle(ExchangeRateProvider $provider): int
    {
        // Попросим у провайдера «сырец» (если умеет), иначе — построим вызовом getExchangeRate
        if (method_exists($provider, 'fetchFromSource') && method_exists($provider, 'saveRates')) {
            $this->info('Fetching from provider source...');
            $payload = $provider->fetchFromSource();

            // Сохраняем в кэш атомарно
            $provider->saveRates($payload);

            $this->info('Rates cached: base='.$payload['base'].'; source='.$payload['source'].'; at='.$payload['fetched_at']);

            if (!$this->option('no-db') && Schema::hasTable('ak_currency_rates')) {
                DB::table('ak_currency_rates')->insert([
                    'source'     => $payload['source'] ?? 'unknown',
                    'base'       => $payload['base'] ?? 'EUR',
                    'rates'      => json_encode($payload['rates'] ?? []),
                    'fetched_at' => $payload['fetched_at'] ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info('History row inserted.');
            }
            return self::SUCCESS;
        }

        // Универсальный (медленный) путь — построить матрицу из пары-символов (если заданы)
        $symbols = config('bs.currency.symbols', []);
        
        if (empty($symbols)) {
            $this->error('Provider does not expose fetch method and no symbols defined.');
            return self::FAILURE;
        }

        $base = $symbols[0]; // возьмём первую как базу
        $map  = [];
        foreach ($symbols as $sym) {
            if ($sym === $base) {
                $map[$sym] = 1.0;
                continue;
            }
            $map[$sym] = $provider->getExchangeRate($base, $sym);
        }

        $payload = [
            'base' => $base,
            'rates' => $map,
            'fetched_at' => now()->toIso8601String(),
            'source' => class_basename($provider),
        ];

        Cache::put(config('bs.currency.cache_key'), $payload, (int)config('bs.currency.cache_ttl_seconds'));
        $this->info('Rates cached via fallback.');
        return self::SUCCESS;
    }
}
