<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Models\Checkpoint;
use App\Models\User;
use App\Services\MovementAnalyticsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

// Read-only render harness: writes browser artifacts to private test storage only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Test the built assets without changing the developer's running Vite hot file.
\Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/testing/movement-ui/unused.hot'));
\Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');
Auth::setUser(User::where('role', 'admin')->firstOrFail());
$dir = storage_path('framework/testing/movement-ui');
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
file_put_contents($dir.'/after.html', app(AdminAnalyticsController::class)->index()->render());
$checkpoint = Checkpoint::whereDate('as_of_date', '<=', now('Asia/Manila')->toDateString())->orderByDesc('as_of_date')->value('key');
file_put_contents($dir.'/after.json', json_encode(app(MovementAnalyticsService::class)->dashboard($checkpoint)));
file_put_contents($dir.'/close-review.json', json_encode(app(\App\Services\CheckpointCloseService::class)->review(Auth::user(), Checkpoint::where('key', $checkpoint)->firstOrFail())));
if (in_array('--sample', $argv, true)) {
    if (!app()->environment('local')) throw new RuntimeException('Sample renderer is local-only.');
    config(['movement_analytics.sample_enabled'=>true]);
    file_put_contents($dir.'/sample.json', json_encode(app(MovementAnalyticsService::class)->dashboard($checkpoint, sample:true)));
}
echo 'Rendered real local aggregate data without changing database records.'.PHP_EOL;
