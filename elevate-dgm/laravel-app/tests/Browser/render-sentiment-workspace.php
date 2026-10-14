<?php

use App\Http\Controllers\SentimentResearchController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Auth, DB, URL, View, Vite};
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Isolated empty database: never read or change the user's records.
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
DB::purge('sqlite');
Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);
$dir = storage_path('framework/testing/sentiment-workspace');
if (!is_dir($dir)) mkdir($dir, 0700, true);
$admin = User::factory()->create(['role' => 'admin']);
Auth::setUser($admin);
URL::forceRootUrl('http://127.0.0.1:8000');
Vite::useHotFile($dir.'/unused.hot');
View::share('errors', new ViewErrorBag);
foreach (['overview' => '/admin/analytics/sentiment', 'review' => '/admin/analytics/sentiment/research/review'] as $section => $uri) {
    $request = Request::create('http://127.0.0.1:8000'.$uri);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $admin);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $app->instance('request', $request);
    file_put_contents($dir.'/'.$section.'.html', app(SentimentResearchController::class)->index($request)->render());
}
echo "Rendered isolated sentiment workspace previews.\n";
