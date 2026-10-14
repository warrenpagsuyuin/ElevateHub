<?php

use App\Models\User;
use App\Services\MovementAnalyticsCache;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Actual local HTTP middleware/controller timings, without writing source records.
$started = hrtime(true);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Request::create('/admin/analytics'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
\Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/testing/movement-ui/unused.hot'));
config(['session.driver' => 'array']);
Auth::setUser(User::where('role', 'admin')->firstOrFail());
app(MovementAnalyticsCache::class)->invalidate(DB::connection());
$results = ['bootstrap_ms' => round((hrtime(true) - $started) / 1e6, 2)];
$queries = [];
DB::listen(function ($query) use (&$queries) {
    $queries[] = $query->sql;
});
foreach (['page' => '/admin/analytics', 'cold_data' => '/admin/analytics/movement-data?checkpoint=2026-09', 'warm_data' => '/admin/analytics/movement-data?checkpoint=2026-09'] as $label => $url) {
    $queries = [];
    $started = hrtime(true);
    $response = $kernel->handle(Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
    $results[$label] = ['ms' => round((hrtime(true) - $started) / 1e6, 2), 'status' => $response->getStatusCode(), 'queries' => count($queries), 'bytes' => strlen($response->getContent())];
    foreach ($queries as $query) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query)) {
            throw new RuntimeException('Benchmark attempted a database mutation.');
        }
    }
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Benchmark request failed: '.$response->getContent());
    }
}
$dir = storage_path('framework/testing/movement-ui');
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
file_put_contents($dir.'/server-performance.json', json_encode($results, JSON_PRETTY_PRINT));
echo json_encode($results, JSON_PRETTY_PRINT).PHP_EOL;
