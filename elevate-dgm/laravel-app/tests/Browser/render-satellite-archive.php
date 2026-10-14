<?php

use App\Http\Controllers\AdminSatelliteController;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Isolated fixtures: never read or mutate the application's database.
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'cache.default' => 'array', 'session.driver' => 'array']);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
$dir = storage_path('framework/testing/satellite-archive');
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$admin = User::factory()->create(['role' => 'admin']);
Auth::setUser($admin);
View::share('errors', new ViewErrorBag);
URL::forceRootUrl('http://127.0.0.1:8000');
$satellite = Satellite::create(['name' => 'Plp', 'slug' => 'plp', 'hub' => 'MAIN']);
Member::create(['name' => 'Member', 'satellite_id' => $satellite->id, 'status' => 'Active']);
for ($i = 1; $i < 10; $i++) {
    Satellite::create(['name' => 'Satellite '.$i, 'slug' => 'satellite-'.$i, 'hub' => 'MAIN']);
}
$render = function ($name, $query = '') use ($app, $dir, $admin) {
    $request = Request::create('http://127.0.0.1:8000/admin/satellites'.$query);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);
    file_put_contents($dir.'/'.$name.'.html', app(AdminSatelliteController::class)->index($request)->render());
};
$render('active');
$satellite->archive($admin);
$render('after');
$render('archived', '?view=archived');
echo "Rendered isolated satellite archive fixtures.\n";
