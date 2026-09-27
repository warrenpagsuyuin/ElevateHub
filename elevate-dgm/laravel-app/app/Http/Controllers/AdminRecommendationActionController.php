<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\RecommendationActionRequest;
use App\Models\RecommendationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminRecommendationActionController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50];
    private const SORTS = [
        'title' => 'title',
        'status' => 'is_active',
        'updated' => 'updated_at',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);

        $actions = $this->filteredActionsQuery($filters)
            ->with('approver')
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.recommendation-actions', [
            'actions' => $actions,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'totalActions' => RecommendationAction::count(),
            'activeActions' => RecommendationAction::where('is_active', true)->count(),
            'draftActions' => RecommendationAction::where('is_active', false)->count(),
        ]);
    }

    public function store(RecommendationActionRequest $request): RedirectResponse
    {
        $action = RecommendationAction::create($this->payload($request->validated()));

        return redirect()
            ->route('admin.recommendation-actions.index')
            ->with('status', "{$action->title} was added to the recommendation catalogue.");
    }

    public function update(RecommendationActionRequest $request, RecommendationAction $recommendationAction): RedirectResponse
    {
        $recommendationAction->update($this->payload($request->validated(), $recommendationAction));

        return redirect()
            ->route('admin.recommendation-actions.index', $request->query())
            ->with('status', "{$recommendationAction->title} was updated.");
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $filename = 'recommendation-actions-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredActionsQuery($filters)
            ->with('approver')
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Title', 'Status', 'Approved By', 'Approved At', 'Description']);

            $query->chunk(500, function ($actions) use ($handle): void {
                foreach ($actions as $action) {
                    fputcsv($handle, [
                        $action->title,
                        $action->status_label,
                        $action->approver?->name ?? 'Not approved',
                        $action->approved_at?->toDateTimeString() ?? '',
                        $action->description,
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

    private function filteredActionsQuery(array $filters)
    {
        return RecommendationAction::query()
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'draft', fn ($query) => $query->where('is_active', false));
    }

    private function payload(array $validated, ?RecommendationAction $existing = null): array
    {
        $isActive = (bool) ($validated['is_active'] ?? false);

        return [
            'title' => trim((string) $validated['title']),
            'description' => trim((string) $validated['description']),
            'condition_metadata' => $this->metadata($validated['condition_metadata'] ?? null),
            'is_active' => $isActive,
            'approved_by' => $isActive ? ($existing?->approved_by ?: Auth::id()) : null,
            'approved_at' => $isActive ? ($existing?->approved_at ?: now()) : null,
        ];
    }

    private function metadata(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'updated');
        $direction = (string) $request->query('direction', 'desc');

        return [
            array_key_exists($sort, self::SORTS) ? $sort : 'updated',
            $direction === 'asc' ? 'asc' : 'desc',
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
