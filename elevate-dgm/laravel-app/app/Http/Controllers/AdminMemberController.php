<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMemberController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];
    private const SORTS = [
        'name' => 'name',
        'members' => 'members_count',
        'new_members' => 'new_members_count',
        'leaders' => 'leaders_count',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);
        $counts = Satellite::dashboardCounts();
        $satellites = $this->filteredSatellitesQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.members', [
            'satellites' => $satellites,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'totalMembers' => $counts['activeMembers'],
            'totalSatellites' => $counts['totalSatellites'],
            'totalLeaders' => $counts['activeLeaders'],
            'newMembers' => $counts['newMembers'],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $filename = 'member-breakdown-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredSatellitesQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Satellite', 'Members', 'New Members', 'Leaders']);

            $query->chunk(500, function ($satellites) use ($handle): void {
                foreach ($satellites as $satellite) {
                    fputcsv($handle, [
                        $satellite->display_name,
                        $satellite->members_count,
                        $satellite->new_members_count,
                        $satellite->leaders_count,
                    ]);
                }
            });
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function filteredSatellitesQuery(array $filters)
    {
        return Satellite::query()
            ->with('assignedUsers')
            ->when($filters['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'));
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
