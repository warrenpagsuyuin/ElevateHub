<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use App\Services\DashboardDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class DashboardDirectoryController extends Controller
{
    public function index(Request $request, DashboardDirectoryService $service, string $directory)
    {
        abort_unless(isset(DashboardDirectoryService::TYPES[$directory]), 404);
        $request->validate(['category' => ['nullable', Rule::in(['main', 'ncp', 'icp'])], 'satellite' => ['nullable', 'integer', Rule::exists('satellites', 'id')->whereNull('archived_at')],
            'search' => ['nullable', 'string', 'max:200'], 'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'], 'sort' => ['nullable', 'string'], 'direction' => ['nullable', 'in:asc,desc']]);
        $category = $request->filled('category') ? strtoupper($request->query('category')) : null;
        $satellite = $request->filled('satellite') ? $request->integer('satellite') : null;
        $search = trim((string) $request->query('search', ''));
        $sorts = $directory === 'campuses' ? ['name' => 'name'] : ['name' => 'display_name', 'district' => 'directory_satellite', 'leader' => 'directory_leader', 'campus' => 'campus_school', 'category' => 'directory_hub', 'registered' => 'created_at'];
        if ($directory !== 'campuses' && $directory !== 'dgroup-members') {
            $sorts['assigned'] = 'assigned_count';
        }
        $sort = array_key_exists($request->query('sort', 'name'), $sorts) ? $request->query('sort', 'name') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $perPage = $request->integer('per_page', 25);
        if ($directory === 'campuses') {
            $all = $service->campuses($category, $satellite);
            $scopeCount = $all->count();
            $filtered = $all->filter(fn ($row) => $search === '' || mb_stripos($row['name'].' '.implode(' ', $row['satellites']), $search) !== false);
            $filtered = ($direction === 'desc' ? $filtered->sortByDesc('key') : $filtered->sortBy('key'))->values();
            $records = new LengthAwarePaginator($filtered->forPage($request->integer('page', 1), $perPage)->values(), $filtered->count(), $perPage, $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
        } else {
            $q = $service->query($directory, $category, $satellite);
            $scopeCount = (clone $q)->count();
            $service->withAssignmentCount($q)->with(['satellite', 'leader']);
            $q->selectRaw("COALESCE(NULLIF(members.full_name, ''), members.name) as display_name")
                ->selectSub(Satellite::select('name')->whereColumn('satellites.id', 'members.satellite_id')->limit(1), 'directory_satellite')
                ->selectSub(Satellite::select('hub')->whereColumn('satellites.id', 'members.satellite_id')->limit(1), 'directory_hub')
                ->selectRaw("COALESCE(members.dgroup_leader_name, '') as directory_leader");
            if ($search !== '') {
                $q->where(function ($q) use ($search) {
                    foreach (['full_name', 'name', 'campus_school', 'dgroup_leader_name', 'email_address', 'email', 'contact_number', 'phone'] as $field) {
                        $q->orWhere('members.'.$field, 'like', '%'.$search.'%');
                    }
                    $q->orWhereHas('satellite', fn ($s) => $s->where('name', 'like', '%'.$search.'%'));
                });
            }
            $records = $q->orderBy($sorts[$sort], $direction)->orderBy('members.id')->paginate($perPage)->withQueryString();
        }

        return view('admin.dashboard-directory.index', [
            'directory' => $directory, 'title' => DashboardDirectoryService::TYPES[$directory], 'records' => $records,
            'category' => $category, 'selectedSatellite' => $satellite, 'search' => $search, 'sort' => $sort, 'direction' => $direction, 'sorts' => $sorts,
            'scopeCount' => $scopeCount, 'perPage' => $perPage, 'pageSizes' => [10, 25, 50, 100],
            'satellites' => Satellite::when($category, fn ($q) => $q->where('hub', $category))->orderBy('name')->get(['id', 'name']),
            'distribution' => $directory === 'dgroups' ? $service->distribution($category, $satellite) : null,
        ]);
    }
}
