<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Асинхронное логирование поисковых запросов в ak_search_queries.
 */
class LogSearchQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Оригинальный запрос */
    public string $q;

    /** Нормализованные варианты (строка или массив строк) */
    public array $normalized;

    /** Код страны (например, UA, CZ, ...). Может быть null. */
    public ?string $countryCode;

    /** Локаль (например, uk, cs, ...). Может быть null. */
    public ?string $locale;

    /** ID пользователя (если авторизован). Может быть null. */
    public ?int $userId;

    /** IP-адрес клиента. Может быть null. */
    public ?string $ip;

    /** Сколько результатов вернул поиск. */
    public int $resultsCount;

    /** Время выполнения, мс. */
    public int $tookMs;

    /** Какой драйвер использовался (meilisearch / db / etc.) */
    public ?string $driver;

    /**
     * @param string            $q
     * @param string|array|null $normalized  Строка или массив вариантов; может быть null
     * @param string|null       $countryCode
     * @param string|null       $locale
     * @param int|null          $userId
     * @param string|null       $ip
     * @param int               $resultsCount
     * @param int               $tookMs
     * @param string|null       $driver
     */
    public function __construct(
        string $q,
        string|array|null $normalized,
        ?string $countryCode,
        ?string $locale,
        ?int $userId,
        ?string $ip,
        int $resultsCount,
        int $tookMs,
        ?string $driver = 'meilisearch'
    ) {
        $this->q            = $q;
        $this->normalized   = is_array($normalized) ? $normalized : (array) ($normalized ?? []);
        $this->countryCode  = $countryCode;
        $this->locale       = $locale;
        $this->userId       = $userId;
        $this->ip           = $ip;
        $this->resultsCount = $resultsCount;
        $this->tookMs       = $tookMs;
        $this->driver       = $driver;

        // Выполнять логирование только после фиксации транзакции запроса
        $this->afterCommit();

        // Опционально можно направить в отдельную очередь:
        // $this->onQueue('search');
    }

    public function handle(): void
    {
        // Если логирование отключено в настройках — тихо выходим
        if (!\Settings::get('dress.search.history.enabled', true)) {
            return;
        }

        // Нормализуем и ограничим длины строк под колонку VARCHAR(255)
        $q             = $this->cut($this->q, 255);
        $normalizedStr = $this->normalizedToString($this->normalized, 255);
        $country       = $this->cut($this->countryCode, 8);
        $locale        = $this->cut($this->locale, 8);
        $ip            = $this->cut($this->ip, 45);
        $driver        = $this->cut($this->driver, 32);
        
        try {
            DB::table('ak_search_queries')->insert([
                'q'             => $q,
                'normalized_q'  => $normalizedStr,
                'country_code'  => $country,
                'locale'        => $locale,
                'user_id'       => $this->userId,
                'ip'            => $ip,
                'results_count' => max(0, (int) $this->resultsCount),
                'took_ms'       => max(0, (int) $this->tookMs),
                'driver'        => $driver,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Не рвём очередь из-за метрик
            logger()->warning('Search log insert failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Удобный статический диспатч из сервисов */
    public static function dispatchNowOrQueue(
        string $q,
        string|array|null $normalized,
        ?string $countryCode,
        ?string $locale,
        ?int $userId,
        ?string $ip,
        int $resultsCount,
        int $tookMs,
        ?string $driver = 'meilisearch'
    ): void {
        // Просто диспатчим — job сама подождёт коммит (afterCommit)
        dispatch(new self(
            $q,
            $normalized,
            $countryCode,
            $locale,
            $userId,
            $ip,
            $resultsCount,
            $tookMs,
            $driver
        ));
    }

    // --- helpers ---

    protected function cut(?string $s, int $max): ?string
    {
        if ($s === null) return null;
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }

    protected function normalizedToString(array $list, int $max): ?string
    {
        if (empty($list)) return null;

        // Уберём пустые/дубли, склеим через " | "
        $clean = array_values(array_unique(array_filter(array_map(
            fn($v) => is_string($v) ? trim($v) : (is_scalar($v) ? (string)$v : ''),
            $list
        ), fn($s) => $s !== '')));

        if (empty($clean)) return null;

        $joined = implode(' | ', $clean);
        return $this->cut($joined, $max);
    }
}
