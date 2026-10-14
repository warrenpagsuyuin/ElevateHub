<?php

// Render synthetic records without reading or changing application records.
use App\Models\{FollowUp, Satellite, Seeker, User};
use App\Services\SentimentAnalysisService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Auth, URL, View, Vite};
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$dir = storage_path('framework/testing/follow-ups');
if (!is_dir($dir)) mkdir($dir, 0700, true);
$satellite = new Satellite(['name' => 'Pasig', 'slug' => 'pasig']);
$satellite->id = 1;
$request = Request::create('http://127.0.0.1:8000/satellites/pasig/follow-ups');
$request->setLaravelSession(app('session.store'));
$route = app('router')->getRoutes()->match($request);
$route->setParameter('satellite', $satellite);
$request->setRouteResolver(fn () => $route);
$app->instance('request', $request);
Auth::setUser(new User(['name' => 'Preview Admin', 'role' => 'admin']));
URL::forceRootUrl('http://127.0.0.1:8000');
Vite::useHotFile($dir.'/unused.hot');
View::share('errors', new ViewErrorBag);
$items = collect([new FollowUp([
    'follow_up_date' => '2026-09-15', 'method' => FollowUp::METHOD_PHONE,
    'status' => FollowUp::STATUS_COMPLETED, 'sentiment_label' => 'Positive',
    'sentiment_confidence' => 0.95,
    'sentiment_metrics' => ['decision_path' => ['Interested in attending. '.str_repeat('Long explanation ', 12)]],
    'notes' => str_repeat('A long note that must stay readable. ', 8).str_repeat('unbroken', 35),
])]);
$items[0]->id = 1;
$items[0]->setRelation('seeker', new Seeker(['name' => 'Long Target Name for Responsive Testing']));
$items[0]->setRelation('member', null);
foreach ([false, true] as $canManageSatellite) {
    file_put_contents($dir.'/'.($canManageSatellite ? 'manager' : 'readonly').'.html', view('satellites.follow-ups.index', [
        'satellite' => $satellite,
        'followUps' => new LengthAwarePaginator($items, 1, 10, 1, ['path' => $request->url()]),
        'statuses' => FollowUp::STATUSES, 'methods' => FollowUp::METHODS, 'sentiments' => FollowUp::SENTIMENTS,
        'filters' => ['status' => '', 'method' => '', 'sentiment' => ''],
        'sentimentSummary' => app(SentimentAnalysisService::class)->summarize($items),
        'pageSizes' => [10, 25, 50, 100], 'perPage' => 10, 'canManageSatellite' => $canManageSatellite,
    ])->render());
}
echo "Rendered synthetic follow-up previews.\n";
