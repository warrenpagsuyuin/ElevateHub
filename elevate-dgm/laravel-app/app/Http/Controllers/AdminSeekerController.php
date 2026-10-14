<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use App\Models\Member;
use App\Models\Seeker;
use App\Services\GoogleSheetSeekerSync;
use App\Services\SeekerArchiveService;
use App\Services\SeekerStatusService;
use App\Services\SeekerLeaderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AdminSeekerController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;

    private const PAGE_SIZES = [10, 25, 50, 100];

    private const SORTS = [
        'name' => 'name',
        'email' => 'email',
        'school' => 'school',
        'district' => 'assigned_district',
        'status' => 'status',
        'archived_at' => 'archived_at',
    ];

    public function index(Request $request): View
    {
        // Any seeker whose status is no longer Pending belongs in the archive.
        app(SeekerArchiveService::class)->archiveFinalStatuses($request->user());

        $filters = $this->filters($request);
        $filters['view'] = $filters['view'] === 'archive' ? 'archive' : 'active';
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);
        $sortColumn = self::SORTS[$sort];
        $tabCounts = Seeker::query()
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL AND converted_member_id IS NULL THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) as archived_count')
            ->first();

        $seekers = $this->filteredSeekersQuery($filters)
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.seekers', [
            'seekers' => $seekers,
            'leaderOptionsBySatellite' => $this->leaderOptionsBySatellite(),
            'districtOptions' => $this->districtOptions($filters['view']),
            'filterDistrictOptions' => Seeker::query()
                ->where(fn ($q) => $q->whereNull('assigned_satellite_slug')->orWhereNotIn('assigned_satellite_slug', Satellite::archived()->select('slug')))
                ->where(fn ($q) => $q->whereNull('assigned_district')->orWhereNotIn('assigned_district', Satellite::archived()->select('name')))
                ->when($filters['view'] === 'archive', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at')->whereNull('converted_member_id'))
                ->select('assigned_district')->distinct()->orderBy('assigned_district')->pluck('assigned_district')
                ->map(fn ($district) => $district ?: 'Unassigned')->all(),
            'archiveCount' => (int) ($tabCounts->archived_count ?? 0),
            'activeCount' => (int) ($tabCounts->active_count ?? 0),
            'notificationCount' => Seeker::whereNull('archived_at')->where('status', Seeker::STATUS_UNSUCCESSFUL)->count(),
            'statuses' => Seeker::STATUSES,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
        ]);
    }

    public function syncGoogleSheet(GoogleSheetSeekerSync $sync): RedirectResponse
    {
        try {
            $synced = $sync->sync();
            $this->refreshSatelliteSeekerCounts();
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.seekers')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.seekers')
            ->with('status', "Synced {$synced} seeker".($synced === 1 ? '' : 's').' from Google Sheets.');
    }

    public function assignDistrict(Request $request, Seeker $seeker): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_district' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($seeker, $validated): void {
            $seeker = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            abort_if($seeker->archived_at, 422, 'Restore this seeker before assignment.');
            $assignedDistrict = trim((string) ($validated['assigned_district'] ?? ''));

            if ($assignedDistrict === '' || $assignedDistrict === 'Unassigned') {
                $seeker->update([
                    'assigned_district' => null,
                    'assigned_satellite_slug' => null,
                ]);
            } else {
                $satellite = $this->satelliteForDistrict($assignedDistrict);

                $seeker->update([
                    'assigned_district' => $satellite?->display_name ?? $assignedDistrict,
                    'assigned_satellite_slug' => $satellite?->slug ?? Str::slug($assignedDistrict),
                ]);
            }

        }, 3);
        $this->refreshSatelliteSeekerCounts();
        $redirectParameters = $request->filled('page')
            ? ['page' => max(1, $request->integer('page', 1))]
            : [];

        return redirect()
            ->route('admin.seekers', $redirectParameters)
            ->with('status', "{$seeker->name} assignment updated.");
    }

    public function assignLeader(Request $request, Seeker $seeker, SeekerLeaderAssignmentService $assignment): RedirectResponse|JsonResponse
    {
        $satellite = $seeker->satellite;
        abort_unless($satellite instanceof Satellite, 404);

        $validated = $request->validate(['leader_id' => ['required', 'integer']], [
            'leader_id.required' => 'Select a DGroup Leader.',
            'leader_id.integer' => 'Select a valid DGroup Leader.',
        ]);
        $leader = $assignment->assign($seeker, $satellite, (int) $validated['leader_id']);
        $message = "{$leader->display_name} assigned as DGroup Leader for {$seeker->name}.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'leader' => ['id' => $leader->id, 'name' => $leader->display_name],
            ]);
        }

        return redirect()->route('admin.seekers')->with('status', $message);
    }

    public function updateStatus(Request $request, Seeker $seeker): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string']]);
        $result = app(SeekerStatusService::class)->change($seeker, $data['status'], $request->user());
        return $request->expectsJson() ? response()->json($result)
            : redirect()->route('admin.seekers')->with('status', $result['message']);
    }
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_action' => ['required', 'in:assign_district,status'],
            'selection_scope' => ['required', 'in:ids,matching'],
            'ids' => ['array'],
            'ids.*' => ['integer', 'exists:seekers,id'],
            'assigned_district' => ['nullable', 'string', 'max:120'],
            'status' => ['required_if:bulk_action,status', 'nullable', 'string', 'in:'.implode(',', Seeker::STATUSES)],
            'return_to' => ['nullable', 'string'],
        ]);

        $query = $validated['selection_scope'] === 'matching'
            ? $this->filteredSeekersQuery($this->filtersFromArray((array) $request->input('filters', [])))
            : Seeker::query()->whereKey($validated['ids'] ?? []);

        $seekers = $query->get();

        if ($seekers->isEmpty()) {
            return redirect()
                ->to($request->input('return_to', route('admin.seekers')))
                ->with('error', 'Select at least one seeker before running a bulk action.');
        }

        if ($validated['bulk_action'] === 'assign_district') {
            $assignedDistrict = trim((string) ($validated['assigned_district'] ?? ''));
            $satellite = $assignedDistrict === '' || $assignedDistrict === 'Unassigned'
                ? null
                : $this->satelliteForDistrict($assignedDistrict);

            DB::transaction(function () use ($seekers, $assignedDistrict, $satellite): void {
                foreach ($seekers->sortBy('id') as $seeker) {
                    $seeker = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
                    abort_if($seeker->archived_at, 422, 'Restore this seeker before assignment.');
                    $seeker->update([
                        'assigned_district' => $assignedDistrict === '' || $assignedDistrict === 'Unassigned'
                            ? null
                            : ($satellite?->display_name ?? $assignedDistrict),
                        'assigned_satellite_slug' => $assignedDistrict === '' || $assignedDistrict === 'Unassigned'
                            ? null
                            : ($satellite?->slug ?? Str::slug($assignedDistrict)),
                    ]);
                }
            }, 3);

            $this->refreshSatelliteSeekerCounts();
            $message = $seekers->count().' seeker'.($seekers->count() === 1 ? '' : 's').' reassigned.';
        } else {
            $status = Seeker::normalizeStatus($validated['status'] ?? null);
            DB::transaction(function () use ($seekers, $status, $request): void {
                foreach ($seekers as $seeker) {
                    app(SeekerStatusService::class)->change($seeker, $status, $request->user());
                }
            }, 3);
            $message = $seekers->count().' seeker'.($seekers->count() === 1 ? '' : 's').' moved to '.$status.'.';
        }

        return redirect()
            ->to($request->input('return_to', route('admin.seekers')))
            ->with('status', $message);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $sortColumn = self::SORTS[$sort];
        // Historical exports require an explicit view=all; the default is active records.
        $filename = 'seekers-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredSeekersQuery($filters)
            ->orderBy($sortColumn, $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'School', 'Assigned District', 'Status', 'Archived on', 'Archived by', 'Reason', 'Archive note']);

            $query->chunk(500, function ($seekers) use ($handle): void {
                foreach ($seekers as $seeker) {
                    $row = [
                        $seeker->name,
                        $seeker->email,
                        $seeker->school,
                        $seeker->assigned_district_label,
                        $seeker->status,
                        $seeker->archived_at?->toDateTimeString(),
                        $seeker->archivedBy?->name,
                        $seeker->archive_reason,
                        $seeker->latestArchiveLog?->note,
                    ];
                    fputcsv($handle, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $row));
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        $request->validate(['archived_from' => ['nullable', 'date_format:Y-m-d'], 'archived_to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('archived_from') ? 'after_or_equal:archived_from' : null])]);

        return $this->filtersFromArray($request->query());
    }

    private function filtersFromArray(array $source): array
    {
        return [
            'view' => in_array($source['view'] ?? '', ['active', 'archive', 'all'], true) ? $source['view'] : 'active',
            'archived_from' => (string) ($source['archived_from'] ?? ''),
            'archived_to' => (string) ($source['archived_to'] ?? ''),
            'search' => trim((string) ($source['search'] ?? '')),
            'status' => (string) ($source['status'] ?? ''),
            'district' => (string) ($source['district'] ?? ''),
            'school' => trim((string) ($source['school'] ?? '')),
        ];
    }

    private function filteredSeekersQuery(array $filters)
    {
        return Seeker::query()->with(['archivedBy', 'latestArchiveLog', 'satellite'])
            ->when($filters['view'] === 'active', fn ($q) => $q->whereNull('archived_at')->whereNull('converted_member_id'))
            ->when($filters['view'] === 'archive', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($filters['archived_from'] !== '', fn ($q) => $q->where('archived_at', '>=', $filters['archived_from'].' 00:00:00'))
            ->when($filters['archived_to'] !== '', fn ($q) => $q->where('archived_at', '<=', $filters['archived_to'].' 23:59:59'))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%')
                        ->orWhere('school', 'like', '%'.$filters['search'].'%')
                        ->orWhere('assigned_district', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', Seeker::normalizeStatus($filters['status'])))
            ->when($filters['district'] !== '', function ($query) use ($filters): void {
                if ($filters['district'] === 'Unassigned') {
                    $query->whereNull('assigned_district');

                    return;
                }

                $query->where('assigned_district', $filters['district']);
            })
            ->when($filters['school'] !== '', fn ($query) => $query->where('school', 'like', '%'.$filters['school'].'%'));
    }

    private function leaderOptionsBySatellite(): array
    {
        return Member::active()->whereIn('satellite_id', Satellite::active()->select('id'))
            ->with('satellite:id,slug')
            ->where('status', Member::STATUS_ACTIVE)
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Member $leader): bool => $leader->satellite instanceof Satellite)
            ->groupBy(fn (Member $leader): string => $leader->satellite->slug)
            ->map(fn ($leaders): array => $leaders->mapWithKeys(fn (Member $leader): array => [$leader->id => $leader->display_name])->all())
            ->all();
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');
        $direction = (string) $request->query('direction', 'asc');

        return [
            array_key_exists($sort, self::SORTS) ? $sort : 'name',
            $direction === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }

    private function districtOptions(string $view): array
    {
        $satelliteDistricts = Satellite::orderBy('name')
            ->get()
            ->map(fn (Satellite $satellite): string => $satellite->display_name)
            ->all();

        return collect($satelliteDistricts)
            ->merge(
                Seeker::query()
                    ->when($view === 'archive', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at')->whereNull('converted_member_id'))
                    ->where(fn ($q) => $q->whereNull('assigned_satellite_slug')->orWhereNotIn('assigned_satellite_slug', Satellite::archived()->select('slug')))
                    ->whereNotIn('assigned_district', Satellite::archived()->select('name'))
                    ->whereNotNull('assigned_district')
                    ->pluck('assigned_district')
                    ->filter()
            )
            ->push('Unassigned')
            ->unique()
            ->values()
            ->all();
    }

    private function satelliteForDistrict(string $district): ?Satellite
    {
        $slug = Str::slug($district);

        return Satellite::query()
            ->where('slug', $slug)
            ->orWhere('name', $district)
            ->first();
    }

    private function refreshSatelliteSeekerCounts(): void
    {
        Satellite::all()->each(function (Satellite $satellite): void {
            $satellite->update([
                'seekers_count' => Seeker::where('assigned_satellite_slug', $satellite->slug)->count(),
            ]);
        });
    }
}
