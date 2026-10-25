<?php

// Database-free fixture: render the real dashboard with in-memory models only.
use App\Models\{Satellite, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, URL, View, Vite};
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'session.driver' => 'array']);
\Illuminate\Support\Facades\DB::listen(fn () => throw new RuntimeException('Summary fixtures must not query a database.'));
$output = storage_path('framework/testing/summary-panels');
if (!is_dir($output)) mkdir($output, 0700, true);
$satellite = new Satellite(['name' => 'Pasig', 'slug' => 'pasig']);
$satellite->id = 1;
$request = Request::create('http://127.0.0.1:8000/satellites/pasig/dashboard');
$request->setLaravelSession(app('session.store'));
$route = app('router')->getRoutes()->match($request);
$route->setParameter('satellite', $satellite);
$request->setRouteResolver(fn () => $route);
$app->instance('request', $request);
Auth::setUser(new User(['name' => 'Fixture Admin', 'role' => 'admin']));
URL::forceRootUrl('http://127.0.0.1:8000');
Vite::useHotFile($output.'/unused.hot');
View::share('errors', new ViewErrorBag);
$now = now('Asia/Manila');
$cards = [];
foreach (['DGROUP MEMBERS', 'DGROUP LEADERS', 'SEEKERS', 'NEW MEMBERS'] as $label) {
    $cards[] = ['label' => $label, 'total' => 0, 'href' => route('satellites.members', $satellite)];
}
$data = [
    'satellite' => $satellite, 'canManageSatellite' => true,
    'isLiveDashboard' => true, 'dashboardHasData' => true,
    'dashboardCurrentYear' => $now->year, 'dashboardCurrentMonth' => $now->month,
    'dashboardDate' => $now->format('F j, Y'), 'dashboardUpdatedAt' => $now->format('g:i A'),
    'selectedDashboardMonth' => 'Live', 'selectedDashboardYear' => $now->year,
    'dashboardPeriods' => ['Live', ...\App\Services\SatelliteDashboardPeriod::MONTHS], 'dashboardYears' => [2026],
    'satelliteSummaryCards' => $cards,
    'engagementSummary' => [
        ['label' => 'Active', 'value' => 1250000],
        ['label' => 'Moderately Active', 'value' => 0],
        ['label' => 'Possible Need for Follow-Up', 'value' => 12],
        ['label' => 'Insufficient Information / Not Yet Classified', 'value' => 0],
    ],
    'seekerSummary' => [['label' => 'Pending', 'value' => 0], ['label' => 'Under Review', 'value' => 12], ['label' => 'Converted', 'value' => 0]],
    'recentActivity' => [['target' => 'Synthetic activity target', 'date' => $now->format('M d, Y'), 'method' => 'Phone', 'status' => 'Unsuccessful', 'statusLabel' => 'Status']],
];
file_put_contents($output.'/dashboard.html', view('satellites.dashboard', $data)->render());
echo "Rendered database-free dashboard fixture.\n";
