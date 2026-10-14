<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Private dashboard cache; checkpoint reviews always use uncached evidence. */
class MovementAnalyticsCache
{
    private function store()
    {
        // Never use a database cache: its own writes would trigger invalidation.
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }

    private function namespace(Connection $connection): string
    {
        return 'movement:v4:'.hash('sha256', json_encode([
            base_path(), app()->environment(), $connection->getName(), $connection->getDatabaseName(),
        ]));
    }

    public function invalidate(Connection $connection): void
    {
        // A fresh generation also handles bulk SQL imports that bypass model events.
        $this->store()->forever($this->namespace($connection).':generation', bin2hex(random_bytes(16)));
    }

    public function remember(array $filters, callable $calculate): array
    {
        $connection = DB::connection();
        $ttl = (int) config('movement_analytics.cache.seconds', 60);
        // Never publish transaction-local evidence or use a cache for a close decision.
        if ($ttl <= 0 || $connection->transactionLevel() > 0) {
            return $calculate();
        }
        $prefix = $this->namespace($connection);
        $generation = $this->store()->get($prefix.':generation', 'initial');
        $key = $prefix.':'.hash('sha256', json_encode([
            $generation, $filters, config('movement_analytics'), now('Asia/Manila')->toDateString(),
        ]));

        return $this->store()->remember($key, $ttl, $calculate);
    }
}
