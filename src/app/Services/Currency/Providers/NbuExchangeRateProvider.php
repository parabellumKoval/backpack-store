<?php

namespace Backpack\Store\app\Services\Currency\Providers;

use Backpack\Store\app\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NbuExchangeRateProvider implements ExchangeRateProvider
{
    protected string $cacheKey;
    protected ?string $cacheStore;
    protected ?int $ttl;  // Теперь может быть null для forever
    protected array $symbols;
    protected bool $lazyFetch;
    protected string $base;

    public function __construct()
    {
        $cfg = \Settings::get('dress.currency');
        $this->cacheKey   = $cfg['cache_key'] ?? 'store:currency:rates:v1';
        $this->cacheStore = $cfg['cache_store'] ?? null;
        
        // Если cache_ttl_seconds = 0 или null, используем forever
        $configTtl = $cfg['cache_ttl_seconds'] ?? 93600;
        $this->ttl = $configTtl == 0 ? null : (int)$configTtl;
        
        $this->symbols    = array_values(array_unique(array_map('strtoupper', $cfg['symbols'] ?? [])));
        // рабочая база для кросс-курсов — EUR (так удобнее в e-commerce)
        $this->base       = strtoupper($cfg['base'] ?? 'EUR');
        $this->lazyFetch  = (bool)($cfg['lazy_fetch_when_missing'] ?? false);
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to   = strtoupper($toCurrency);
        if ($from === $to) return 1.0;

        $rates = $this->getRates(); // ['base'=>'EUR','rates'=>['USD'=>..., 'UAH'=>..., 'CZK'=>...]]
        $base  = $rates['base'] ?? $this->base;
        $map   = $rates['rates'] ?? [];
        $map[$base] = 1.0;

        if (!isset($map[$from]) || !isset($map[$to])) {
            throw new \RuntimeException("Missing rate(s) for $from or $to");
        }

        // rate(from->to) = rate(base->to) / rate(base->from)
        return (float) ($map[$to] / $map[$from]);
    }

    protected function getRates(): array
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
        if ($payload = $cache->get($this->cacheKey)) {
            return $payload;
        }

        if (!$this->lazyFetch) {
            throw new \RuntimeException('Currency rates cache is empty. Run: php artisan store:currency:refresh');
        }

        $payload = $this->fetchFromSource();
        
        // Если ttl = null, используем forever, иначе используем ttl
        if ($this->ttl === null) {
            $cache->forever($this->cacheKey, $payload);
        } else {
            $cache->put($this->cacheKey, $payload, $this->ttl);
        }
        
        return $payload;
    }

    /**
     * Забираем JSON от НБУ (UAH base) и строим матрицу с базой EUR.
     * НБУ отдаёт: rate = UAH per 1 <CC>. Нужен код EUR обязательно.
     */
    public function fetchFromSource(): array
    {
        $resp = Http::timeout(10)->get('https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange', [
            'json' => '', // ?json
        ]);
        if (!$resp->ok()) {
            throw new \RuntimeException('NBU fetch failed, status '.$resp->status());
        }

        $list = $resp->json(); // массив объектов: { r030, txt, rate, cc, exchangedate }
        if (!is_array($list)) {
            throw new \RuntimeException('NBU response parse error');
        }

        // Ищем UAH per EUR — это опорный коэффициент для базы EUR.
        $uEur = null;
        $byCode = [];
        foreach ($list as $row) {
            if (!isset($row['cc'], $row['rate'])) continue;
            $code = strtoupper($row['cc']);
            $rate = (float) $row['rate'];      // UAH per 1 <code>
            $byCode[$code] = $rate;
        }
        if (!isset($byCode['EUR'])) {
            throw new \RuntimeException('NBU payload missing EUR rate');
        }
        $uEur = (float) $byCode['EUR']; // UAH per 1 EUR

        // Строим карту с базой EUR: for ANY code X -> X per 1 EUR
        // X_per_EUR = (UAH_per_EUR) / (UAH_per_X)
        $map = [];

        // Если нужен UAH: это UAH per EUR (прямо uEur)
        $map['UAH'] = $uEur;

        foreach ($byCode as $code => $uX) {
            if ($code === 'EUR') continue;           // base
            if ($uX <= 0) continue;
            $map[$code] = $uEur / $uX;               // X per EUR
        }

        // Ограничим по symbols, если задано
        if (!empty($this->symbols)) {
            $map = array_intersect_key($map, array_flip($this->symbols));
        }

        return [
            'base'       => 'EUR',
            'rates'      => $map,
            'fetched_at' => now()->toIso8601String(),
            'source'     => 'bank.gov.ua (NBU)',
        ];
    }

    public function saveRates(array $payload): void
    {
        $cache = $this->cacheStore ? Cache::store($this->cacheStore) : Cache::store();
        
        // Если ttl = null, используем forever, иначе используем ttl
        if ($this->ttl === null) {
            $cache->forever($this->cacheKey, $payload);
        } else {
            $cache->put($this->cacheKey, $payload, $this->ttl);
        }
    }
}
