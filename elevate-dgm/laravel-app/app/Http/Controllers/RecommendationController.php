<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRecommendationRequest;
use App\Http\Requests\ReviewRecommendationRequest;
use App\Models\Member;
use App\Models\Recommendation;
use App\Models\RecommendationAction;
use App\Models\Satellite;
use App\Models\User;
use App\Models\EngagementResearchAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50];

    public function store(AssignRecommendationRequest $request, Satellite $satellite, Member $member): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canManageSatellite($satellite), 403);
        abort_unless((int) $member->satellite_id === (int) $satellite->id, 404);

        $result = $member->engagementResult;

        if ($result === null) {
            return back()->with('error', 'Generate an engagement classification before assigning a recommendation.');
        }

        $existingPending = Recommendation::query()
            ->where('engagement_result_id', $result->id)
            ->whereIn('status', [Recommendation::STATUS_PENDING, Recommendation::STATUS_MODIFIED])
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->first();

        if ($existingPending !== null) {
            return redirect()
                ->route('satellites.recommendations.show', [$satellite, $existingPending])
                ->with('error', 'This engagement result already has a pending recommendation review.');
        }

        $validated = $request->validated();
        $action = RecommendationAction::approved()->findOrFail($validated['recommended_action_id']);

        $recommendation = DB::transaction(function () use ($member, $result, $action, $validated, $user): Recommendation {
            $recommendation = Recommendation::create([
            'member_id' => $member->id,
            'engagement_result_id' => $result->id,
            'recommended_action_id' => $action->id,
            'assigned_by' => Auth::id(),
            'assigned_to' => $validated['assigned_to'],
            'status' => Recommendation::STATUS_PENDING,
            'action_title_snapshot' => $action->title,
            'action_description_snapshot' => $action->description,
            'assignment_notes' => $validated['assignment_notes'] ?: null,
            'expires_at' => $validated['expires_at'] ?? null,
            ]);
            $this->audit('recommendation.assigned', $user, null, $recommendation->toArray());
            return $recommendation;
        });

        return redirect()
            ->route('satellites.recommendations.show', [$satellite, $recommendation])
            ->with('status', 'Recommendation assigned for human review.');
    }

    public function index(Request $request, Satellite $satellite): View
    {
        $this->authorizeSatelliteAccess($satellite);

        $filters = $this->filters($request);
        $perPage = $this->perPage($request);
        $user = Auth::user();
        $baseQuery = $this->scopedRecommendations($satellite, $user);

        $recommendations = $this->filteredRecommendationsQuery(clone $baseQuery, $filters)
            ->with(['member', 'engagementResult', 'recommendedAction', 'assigner', 'assignee', 'reviewer'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('satellites.recommendations.index', [
            'satellite' => $satellite,
            'recommendations' => $recommendations,
            'filters' => $filters,
            'statuses' => Recommendation::STATUS_OPTIONS,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'summary' => $this->summary(clone $baseQuery),
        ]);
    }

    public function show(Satellite $satellite, Recommendation $recommendation): View
    {
        $this->authorizeRecommendationAccess($satellite, $recommendation);

        $recommendation->load([
            'member.satellite',
            'engagementResult.modelRun',
            'recommendedAction',
            'assigner',
            'assignee',
            'reviewer',
        ]);

        return view('satellites.recommendations.show', [
            'satellite' => $satellite,
            'recommendation' => $recommendation,
            'history' => Schema::hasTable('engagement_research_audits')
                ? EngagementResearchAudit::query()->where('event', 'like', 'recommendation.%')
                    ->where('after_state->id', $recommendation->id)->orderBy('id')->get()
                : collect(),
            'reviewerNames' => User::pluck('name', 'id'),
        ]);
    }

    public function review(ReviewRecommendationRequest $request, Satellite $satellite, Recommendation $recommendation): RedirectResponse
    {
        $this->authorizeRecommendationAccess($satellite, $recommendation);

        $validated = $request->validated();

        DB::transaction(function () use ($recommendation, $validated, $request): void {
            $recommendation = Recommendation::query()->lockForUpdate()->findOrFail($recommendation->id);
            if (! $recommendation->is_reviewable) {
                throw ValidationException::withMessages(['status' => 'This recommendation is already finalized or expired.']);
            }
            $before = $recommendation->toArray();
            $recommendation->update([
                'status' => $validated['status'],
                'review_notes' => $validated['review_notes'] ?: $recommendation->review_notes,
                'action_description_snapshot' => $validated['status'] === Recommendation::STATUS_MODIFIED
                    ? $validated['modified_action_description'] : $recommendation->action_description_snapshot,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
            $this->audit('recommendation.'.strtolower($validated['status']), $request->user(), $before, $recommendation->toArray());
        });

        return redirect()
            ->route('satellites.recommendations.show', [$satellite, $recommendation])
            ->with('status', 'Recommendation review recorded.');
    }

    private function audit(string $event, User $actor, ?array $before, array $after): void
    {
        if (Schema::hasTable('engagement_research_audits')) {
            $research = app(\App\Services\ReferenceDatasetService::class);
            $research->audit($event, $research->current(), $actor, null, $before, $after);
        }
    }

    private function authorizeSatelliteAccess(Satellite $satellite): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->canAccessSatellite($satellite), 403);
    }

    private function authorizeRecommendationAccess(Satellite $satellite, Recommendation $recommendation): void
    {
        $recommendation->loadMissing('member');
        abort_unless((int) $recommendation->member?->satellite_id === (int) $satellite->id, 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->canAccessSatellite($satellite), 403);

        abort_unless(
            $user->canManageSatellite($satellite)
            || (int) $recommendation->assigned_to === (int) $user->id,
            403
        );
    }

    private function scopedRecommendations(Satellite $satellite, ?User $user): Builder
    {
        $query = Recommendation::query()->forSatellite($satellite);

        if ($user instanceof User && ! $user->canManageSatellite($satellite)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private function filteredRecommendationsQuery(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('action_title_snapshot', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('member', function (Builder $query) use ($filters): void {
                            $query->where('full_name', 'like', '%'.$filters['search'].'%')
                                ->orWhere('name', 'like', '%'.$filters['search'].'%');
                        })
                        ->orWhereHas('assignee', function (Builder $query) use ($filters): void {
                            $query->where('name', 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $this->applyStatusFilter($query, $filters['status']);
            });
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === Recommendation::STATUS_EXPIRED) {
            $query->where(function (Builder $query): void {
                $query->where('status', Recommendation::STATUS_EXPIRED)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', Recommendation::STATUS_PENDING)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<', now());
                    });
            });

            return;
        }

        $query->where('status', $status);

        if ($status === Recommendation::STATUS_PENDING) {
            $query->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
        }
    }

    private function summary(Builder $query): array
    {
        return [
            Recommendation::STATUS_PENDING => (clone $query)
                ->where('status', Recommendation::STATUS_PENDING)
                ->where(function (Builder $query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                })
                ->count(),
            Recommendation::STATUS_APPROVED => (clone $query)->where('status', Recommendation::STATUS_APPROVED)->count(),
            Recommendation::STATUS_MODIFIED => (clone $query)->where('status', Recommendation::STATUS_MODIFIED)->count(),
            Recommendation::STATUS_REJECTED => (clone $query)->where('status', Recommendation::STATUS_REJECTED)->count(),
            Recommendation::STATUS_EXPIRED => (clone $query)
                ->where(function (Builder $query): void {
                    $query->where('status', Recommendation::STATUS_EXPIRED)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', Recommendation::STATUS_PENDING)
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<', now());
                        });
                })
                ->count(),
        ];
    }

    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($status, Recommendation::STATUS_OPTIONS, true) ? $status : '',
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
