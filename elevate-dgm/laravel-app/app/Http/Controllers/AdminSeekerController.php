<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use App\Models\Seeker;
use App\Services\GoogleSheetSeekerSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);
        $sortColumn = self::SORTS[$sort];

        $seekers = $this->filteredSeekersQuery($filters)
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.seekers', [
            'seekers' => $seekers,
            'districtOptions' => $this->districtOptions(),
            'notificationCount' => Seeker::where('status', Seeker::STATUS_UNSUCCESSFUL)->count(),
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

        $this->refreshSatelliteSeekerCounts();
        $redirectParameters = $request->filled('page')
            ? ['page' => max(1, $request->integer('page', 1))]
            : [];

        return redirect()
            ->route('admin.seekers', $redirectParameters)
            ->with('status', "{$seeker->name} assignment updated.");
    }

    public function updateStatus(Request $request, Seeker $seeker): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Seeker::STATUSES)],
        ]);

        $seeker->update([
            'status' => Seeker::normalizeStatus($validated['status']),
        ]);

        return redirect()
            ->to($request->input('return_to', route('admin.seekers')))
            ->with('status', "{$seeker->name} status updated.");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_action' => ['required', 'in:assign_district,status'],
            'selection_scope' => ['required', 'in:ids,matching'],
            'ids' => ['array'],
            'ids.*' => ['integer', 'exists:seekers,id'],
            'assigned_district' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Seeker::STATUSES)],
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

            $seekers->each(function (Seeker $seeker) use ($assignedDistrict, $satellite): void {
                $seeker->update([
                    'assigned_district' => $assignedDistrict === '' || $assignedDistrict === 'Unassigned'
                        ? null
                        : ($satellite?->display_name ?? $assignedDistrict),
                    'assigned_satellite_slug' => $assignedDistrict === '' || $assignedDistrict === 'Unassigned'
                        ? null
                        : ($satellite?->slug ?? Str::slug($assignedDistrict)),
                ]);
            });

            $this->refreshSatelliteSeekerCounts();
            $message = $seekers->count().' seeker'.($seekers->count() === 1 ? '' : 's').' reassigned.';
        } else {
            $status = Seeker::normalizeStatus($validated['status'] ?? null);
            Seeker::whereKey($seekers->modelKeys())->update(['status' => $status]);
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
        $filename = 'seekers-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredSeekersQuery($filters)
            ->orderBy($sortColumn, $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'School', 'Assigned District', 'Status']);

            $query->chunk(500, function ($seekers) use ($handle): void {
                foreach ($seekers as $seeker) {
                    fputcsv($handle, [
                        $seeker->name,
                        $seeker->email,
                        $seeker->school,
                        $seeker->assigned_district_label,
                        $seeker->status,
                    ]);
                }
            });
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return $this->filtersFromArray($request->query());
    }

    private function filtersFromArray(array $source): array
    {
        return [
            'search' => trim((string) ($source['search'] ?? '')),
            'status' => (string) ($source['status'] ?? ''),
            'district' => (string) ($source['district'] ?? ''),
            'school' => trim((string) ($source['school'] ?? '')),
        ];
    }

    private function filteredSeekersQuery(array $filters)
    {
        return Seeker::query()
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

    private function districtOptions(): array
    {
        $satelliteDistricts = Satellite::orderBy('name')
            ->get()
            ->map(fn (Satellite $satellite): string => $satellite->display_name)
            ->all();

        return collect($satelliteDistricts)
            ->merge(
                Seeker::query()
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
