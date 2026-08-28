<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];
    private const SORTS = [
        'name' => 'name',
        'reports' => 'seekers_count',
        'members' => 'members_count',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);

        $satellites = $this->filteredSatellitesQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.reports', [
            'satellites' => $satellites,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'totalReports' => 0,
            'completedReports' => 0,
            'pendingReports' => 0,
            'overdueReports' => 0,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $filename = 'reports-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredSatellitesQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Satellite', 'Status', 'Reports']);

            $query->chunk(500, function ($satellites) use ($handle): void {
                foreach ($satellites as $satellite) {
                    fputcsv($handle, [
                        $satellite->display_name,
                        'No reports',
                        0,
                    ]);
                }
            });
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
        ];
    }

    private function filteredSatellitesQuery(array $filters)
    {
        return Satellite::query()
            ->when($filters['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->when($filters['status'] === 'no_reports', fn ($query) => $query);
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
}
