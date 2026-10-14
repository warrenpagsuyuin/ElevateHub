<?php

use App\Http\Controllers\AdminSeekerController;
use App\Models\{Seeker, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{Auth, DB, URL, View, Vite};
use Illuminate\Support\ViewErrorBag;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) throw new RuntimeException('Local read-only preview only.');
$dir = storage_path('framework/testing/seeker-archive');
if (!is_dir($dir)) mkdir($dir, 0700, true);
if (in_array('--baseline', $argv, true)) {
    $columns = array_diff(\Illuminate\Support\Facades\Schema::getColumnListing('seekers'), ['archived_by', 'archive_reason']);
    $snapshot = ['columns' => array_values($columns), 'seekers' => hash('sha256', json_encode(DB::table('seekers')->orderBy('id')->get($columns))), 'followups' => hash('sha256', json_encode(DB::table('follow_ups')->orderBy('id')->get()))];
    file_put_contents($dir.'/baseline.json', json_encode($snapshot));
    echo "Saved private record fingerprints.\n";
    exit;
}
$baseline = json_decode(file_get_contents($dir.'/baseline.json'), true);
if ($baseline['seekers'] !== hash('sha256', json_encode(DB::table('seekers')->orderBy('id')->get($baseline['columns']))) || $baseline['followups'] !== hash('sha256', json_encode(DB::table('follow_ups')->orderBy('id')->get()))) throw new RuntimeException('Existing records changed.');
Auth::setUser(User::where('role', 'admin')->firstOrFail());
Vite::useHotFile($dir.'/unused.hot');
URL::forceRootUrl('http://127.0.0.1:8000');
View::share('errors', new ViewErrorBag);
foreach (['active', 'archive'] as $state) {
    $request = Request::create('http://127.0.0.1:8000/admin/seekers?view='.$state);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => Auth::user());
    $app->instance('request', $request);
    file_put_contents($dir.'/'.$state.'.html', app(AdminSeekerController::class)->index($request)->render());
}
$aaron = Seeker::where('name', 'Aaron Alcantara')->first();
echo json_encode(['active' => Seeker::whereNull('archived_at')->count(), 'archived' => Seeker::whereNotNull('archived_at')->count(), 'aaron_in_archive' => (bool) $aaron?->archived_at, 'records_unchanged' => true]).PHP_EOL;

$checkpoint = \App\Models\Checkpoint::whereDate('as_of_date', '<=', now('Asia/Manila')->toDateString())->orderByDesc('as_of_date')->value('key');
if ($checkpoint) {
    $pipeline = app(\App\Services\MovementAnalyticsService::class)->dashboard($checkpoint)['activity']['pipeline'];
    $summary = array_intersect_key($pipeline, array_flip(['total', 'active', 'archived', 'placed', 'rate']));
    file_put_contents($dir.'/pipeline.json', json_encode($summary, JSON_PRETTY_PRINT));
    echo json_encode(['pipeline' => $summary]).PHP_EOL;
}
