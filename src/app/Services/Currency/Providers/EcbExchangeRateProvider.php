<?php

namespace Backpack\Store\app\Services\Currency\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use Backpack\Store\app\Contracts\ExchangeRateProvider;

class EcbExchangeRateProvider implements ExchangeRateProvider
{
    protected string $cacheKey;
    protected ?string $cacheStore;
    protected int $ttl;
    protected array $symbols;
    protected bool $lazyFetch;

    public function __construct()
    {
        $cfg = config('bs.currency');
        $this->cacheKey   = $cfg['cache_key'] ?? 'store:currency:rates:v1';
        $this->cacheStore = $cfg['cache_store'] ?? null;
        $this->ttl        = (int)($cfg['cache_ttl_seconds'] ?? 93600);
        $this->symbols    = $cfg['symbols'] ?? [];
        $this->lazyFetch  = (bool)($cfg['lazy_fetch_when_missing'] ?? false);
    }

    /**
     * Вернёт курс from->to как float
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to   = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->getRates(); // ['base' => 'EUR', 'rates' => ['USD'=>1.08, 'UAH'=>43.1, ...]]

        $base = $rates['base'] ?? 'EUR';
        $map  = $rates['rates'] ?? [];

        // Нормализуем так, чтобы в map гарантированно была базовая валюта = 1
        $map[$base] = 1.0;

        if (!isset($map[$from]) || !isset($map[$to])) {
            throw new \RuntimeException("Missing rate(s) for $from or $to");
        }

        // Кросс-курс через базовую валюту (EUR)
        // rate(from->to) = rate(base->to) / rate(base->from)
        return (float) ($map[$to] / $map[$from]);
    }

    /**
     * Забрать актуальную матрицу курсов из кэша (или подзагрузить).
     */
    protected function getRates(): array
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();

        $rates = $cache->get($this->cacheKey);
        if ($rates) {
            return $rates;
        }

        if (!$this->lazyFetch) {
            throw new \RuntimeException('Currency rates cache is empty. Run the refresh command.');
        }

        // Фоллбэк: подзагрузить сейчас (разово)
        $rates = $this->fetchFromSource();
        $cache->put($this->cacheKey, $rates, $this->ttl);

        return $rates;
    }

    /**
     * Прямой запрос к источнику (ECB).
     */
    public function fetchFromSource(): array
    {
        // XML: https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
        $resp = Http::timeout(10)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
        if (!$resp->ok()) {
            throw new \RuntimeException('ECB fetch failed, status '.$resp->status());
        }

        $xml = simplexml_load_string($resp->body());
        if (!$xml) {
            throw new \RuntimeException('ECB response parse error');
        }

        // Вытаскиваем курсы к EUR
        $namespaces = $xml->getNamespaces(true);
        $cube = $xml->xpath("//gesmes:Envelope/*[local-name()='Cube']/*[local-name()='Cube']/*[local-name()='Cube']");

        $rates = [];
        foreach ($cube as $entry) {
            $currency = (string) $entry['currency'];
            $rate     = (float) $entry['rate'];
            if (!$this->symbols || in_array($currency, $this->symbols, true)) {
                $rates[$currency] = $rate;
            }
        }

        return [
            'base'  => 'EUR',
            'rates' => $rates,
            'fetched_at' => now()->toIso8601String(),
            'source' => 'ecb.europa.eu',
        ];
    }

    /**
     * Сервисный метод: сохранить курсы в кэш.
     */
    public function saveRates(array $payload): void
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
        $cache->put($this->cacheKey, $payload, $this->ttl);
    }
}
