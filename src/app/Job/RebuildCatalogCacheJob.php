<?php

namespace Backpack\Store\app\Job;

use Backpack\Store\app\Services\Catalog\CatalogCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RebuildCatalogCacheJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0; // не ограничиваем время фоновой пересборки

    protected ?array $countries;
    protected int $chunk;

    public function __construct(?array $countries = null, int $chunk = 1000)
    {
        $this->countries = $countries ?: null;
        $this->chunk = $chunk;

        $this->afterCommit();
    }

    public function handle(CatalogCacheService $builder): void
    {
        $queueJob = $this->job instanceof QueueJob ? $this->job : null;
        $heartbeat = $queueJob
            ? function () use ($queueJob) {
                $this->refreshDatabaseReservation($queueJob);
            }
            : null;

        $builder->rebuildAll($this->countries, $this->chunk, $heartbeat);
    }

    public function uniqueId(): string
    {
        $countryKey = $this->countries ? implode(',', $this->countries) : 'all';

        return sprintf('catalog-rebuild:%s:%d', $countryKey, $this->chunk);
    }

    public function uniqueFor(): int
    {
        // 5 minutes, чтобы не плодить фоновые ребилды при частом вызове
        return 300;
    }

    protected function refreshDatabaseReservation(?QueueJob $queueJob): void
    {
        if (!$queueJob) {
            return;
        }

        $connectionName = $queueJob->getConnectionName() ?: config('queue.default');
        $connectionConfig = config("queue.connections.{$connectionName}");

        if (!$connectionConfig) {
            return;
        }

        if (($connectionConfig['driver'] ?? null) !== 'database') {
            return;
        }

        $connection = $connectionConfig['connection'] ?? null;
        $table = $connectionConfig['table'] ?? 'jobs';

        DB::connection($connection)
            ->table($table)
            ->where('id', $queueJob->getJobId())
            ->update(['reserved_at' => now()->getTimestamp()]);
    }
}
