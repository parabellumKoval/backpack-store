<?php

namespace Backpack\Store\app\Services\Currency\Providers;

use Backpack\Store\app\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangerateHostExchangeRateProvider implements ExchangeRateProvider
{
    protected string $cacheKey;
    protected ?string $cacheStore;
    protected int $ttl;
    protected array $symbols;
    protected bool $lazyFetch;
    protected string $base;

    public function __construct()
    {
        $cfg = config('bs.currency');
        $this->cacheKey   = $cfg['cache_key'] ?? 'store:currency:rates:v1';
        $this->cacheStore = $cfg['cache_store'] ?? null;
        $this->ttl        = (int)($cfg['cache_ttl_seconds'] ?? 93600);
        $this->symbols    = $cfg['symbols'] ?? []; // например ['USD','EUR','UAH','CZK']
        $this->lazyFetch  = (bool)($cfg['lazy_fetch_when_missing'] ?? false);
        $this->base       = strtoupper($cfg['base'] ?? 'EUR'); // удобно оставить EUR
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to   = strtoupper($toCurrency);
        if ($from === $to) return 1.0;

        $rates = $this->getRates(); // ['base'=>'EUR','rates'=>['USD'=>..., 'UAH'=>..., ...]]
        $base  = $rates['base'] ?? $this->base;
        $map   = $rates['rates'] ?? [];
        $map[$base] = 1.0;

        if (!isset($map[$from]) || !isset($map[$to])) {
            throw new \RuntimeException("Missing rate(s) for $from or $to");
        }
        return (float) ($map[$to] / $map[$from]);
    }

    protected function getRates(): array
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
        $rates = $cache->get($this->cacheKey);
        if ($rates) return $rates;

        if (!$this->lazyFetch) {
            throw new \RuntimeException('Currency rates cache is empty. Run the refresh command.');
        }

        $payload = $this->fetchFromSource();
        $cache->put($this->cacheKey, $payload, $this->ttl);
        return $payload;
    }

    /**
     * Загрузка JSON одним запросом.
     * Примеры:
     *  - все валюты: https://api.exchangerate.host/latest?base=EUR
     *  - выборочно:  https://api.exchangerate.host/latest?base=EUR&symbols=USD,UAH,CZK,EUR
     */
    public function fetchFromSource(): array
    {
        $url = 'https://api.exchangerate.host/latest';
        $query = ['base' => $this->base];

        if (!empty($this->symbols)) {
            $query['symbols'] = implode(',', array_unique(array_map('strtoupper', $this->symbols)));
        }

        $resp = Http::timeout(10)->get($url, $query);
        if (!$resp->ok()) {
            throw new \RuntimeException('exchangerate.host fetch failed, status '.$resp->status());
        }

        $data = $resp->json();
        dd($data);
        if (!isset($data['rates']) || !is_array($data['rates'])) {
            throw new \RuntimeException('exchangerate.host response parse error');
        }

        // Нормализуем: сами добавим base=1.0 ниже в getExchangeRate
        return [
            'base'       => strtoupper($data['base'] ?? $this->base),
            'rates'      => array_change_key_case($data['rates'], CASE_UPPER),
            'fetched_at' => now()->toIso8601String(),
            'source'     => 'api.exchangerate.host',
        ];
    }

    public function saveRates(array $payload): void
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
        $cache->put($this->cacheKey, $payload, $this->ttl);
    }
}
