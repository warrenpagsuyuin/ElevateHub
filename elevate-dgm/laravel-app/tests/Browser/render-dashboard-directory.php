<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local')) throw new RuntimeException('Local preview only');
$dir = storage_path('framework/testing/dashboard-directory');
if (!is_dir($dir)) mkdir($dir,0700,true);
$baseline = json_decode(file_get_contents(storage_path('app/private/dashboard-directory/before.json')),true);
$unchanged = $baseline['hash'] === hash('sha256',json_encode(Illuminate\Support\Facades\DB::table('members')->orderBy('id')->get($baseline['columns'])));
if (!$unchanged) throw new RuntimeException('Original member fields changed');
Illuminate\Support\Facades\Auth::setUser(App\Models\User::where('role','admin')->firstOrFail());
Illuminate\Support\Facades\Vite::useHotFile($dir.'/unused.hot');
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');
Illuminate\Support\Facades\View::share('errors',new Illuminate\Support\ViewErrorBag);
$service = app(App\Services\DashboardDirectoryService::class);
foreach (array_merge(['dashboard'],array_keys($service::TYPES)) as $type) {
    foreach ($type === 'dashboard' ? [''] : ['', 'main', 'ncp', 'icp'] as $category) {
        $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/admin/dashboard'.($type === 'dashboard' ? '' : '/'.$type).($category ? '?category='.$category : ''));
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn()=>Illuminate\Support\Facades\Auth::user());
        $app->instance('request',$request);
        $view = $type === 'dashboard' ? app(App\Http\Controllers\AdminDashboardController::class)->index($service) : app(App\Http\Controllers\DashboardDirectoryController::class)->index($request,$service,$type);
        file_put_contents($dir.'/'.$type.($category ? '-'.$category : '').'.html',$view->render());
    }
}
$result = ['totals'=>array_column($service->summary(),'total','key'),'original_fields_unchanged'=>$unchanged,'linked'=>App\Models\Member::whereNotNull('leader_member_id')->count(),'unresolved'=>App\Models\Member::where('dgroup_role',App\Models\Member::ROLE_MEMBER)->whereNull('leader_member_id')->pluck('id')->all(),'distribution'=>$service->distribution(),'satellites'=>[]];
foreach (App\Models\Satellite::all() as $s) $result['satellites'][$s->name] = ['members'=>$service->query('dgroup-members',null,$s->id)->count(),'leaders'=>$service->query('dgroup-leaders',null,$s->id)->count(),'dgroups'=>$service->query('dgroups',null,$s->id)->count(),'campuses'=>$service->campuses(null,$s->id)->count(),'distribution'=>$service->distribution(null,$s->id)];
file_put_contents($dir.'/verification.json',json_encode($result,JSON_PRETTY_PRINT));
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
