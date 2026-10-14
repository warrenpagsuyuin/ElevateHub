<?php

namespace Tests\Feature;

use App\Services\MovementAnalyticsCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovementAnalyticsCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('cache_probe', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function test_reuses_aggregate_and_separates_filters_configuration_and_day(): void
    {
        $cache = app(MovementAnalyticsCache::class);
        $calls = 0;
        $calculate = function () use (&$calls) {
            return ['calculation' => ++$calls];
        };
        $this->assertSame($cache->remember(['MAIN'], $calculate), $cache->remember(['MAIN'], $calculate));
        $this->assertSame(1, $calls);
        $cache->remember(['ICP'], $calculate);
        config(['movement_analytics.status_metric' => 'dgroups']);
        $cache->remember(['MAIN'], $calculate);
        $this->travel(1)->days();
        $cache->remember(['MAIN'], $calculate);
        $this->assertSame(4, $calls);
        $this->travelBack();
    }

    public function test_bulk_sql_write_invalidates_cached_aggregate(): void
    {
        $cache = app(MovementAnalyticsCache::class);
        $count = fn () => ['count' => DB::table('cache_probe')->count()];
        $this->assertSame(['count' => 0], $cache->remember([], $count));
        DB::table('cache_probe')->insert(['name' => 'Cache test member']);
        $this->assertSame(['count' => 1], $cache->remember([], $count));
        DB::table('cache_probe')->delete();
        $this->assertSame(['count' => 0], $cache->remember([], $count));
    }

    public function test_transaction_reads_bypass_cache_and_rollback_cannot_publish_counts(): void
    {
        $cache = app(MovementAnalyticsCache::class);
        $count = fn () => ['count' => DB::table('cache_probe')->count()];
        $cache->remember([], $count);
        DB::beginTransaction();
        DB::table('cache_probe')->insert(['name' => 'Uncommitted member']);
        $this->assertSame(['count' => 1], $cache->remember([], $count));
        DB::rollBack();
        $this->assertSame(['count' => 0], $cache->remember([], $count));
        DB::transaction(fn () => DB::table('cache_probe')->insert(['name' => 'Committed member']));
        $this->assertSame(['count' => 1], $cache->remember([], $count));
    }

    public function test_expiration_limits_external_write_staleness_and_cache_can_be_disabled(): void
    {
        $cache = app(MovementAnalyticsCache::class);
        $calls = 0;
        $calculate = function () use (&$calls) {
            return ['calculation' => ++$calls];
        };
        $cache->remember([], $calculate);
        $this->travel(61)->seconds();
        $cache->remember([], $calculate);
        $this->assertSame(2, $calls);
        config(['movement_analytics.cache.seconds' => 0]);
        $cache->remember([], $calculate);
        $cache->remember([], $calculate);
        $this->assertSame(4, $calls);
        $this->travelBack();
    }
}
