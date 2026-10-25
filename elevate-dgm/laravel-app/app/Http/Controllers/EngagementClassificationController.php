<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\RecommendationAction;
use App\Models\Satellite;
use App\Models\User;
use App\Services\EngagementClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EngagementClassificationController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];

    protected EngagementClassificationService $classificationService;

    public function __construct(EngagementClassificationService $classificationService)
    {
        $this->classificationService = $classificationService;
    }

    /**
     * Show engagement analytics for a satellite.
     */
    public function index(Request $request, Satellite $satellite): View
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canAccessSatellite($satellite), 403);

        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('search', ''));
        $classificationFilter = trim((string) $request->query('classification', ''));
        $roleFilter = trim((string) $request->query('role', ''));

        $membersQuery = $satellite->members()->with('engagementResult');

        if ($search !== '') {
            $membersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('campus_school', 'like', '%'.$search.'%');
            });
        }

        if ($roleFilter !== '') {
            $membersQuery->where('dgroup_role', $roleFilter);
        }

        if ($classificationFilter !== '') {
            $aliases = match ($classificationFilter) {
                EngagementClassificationService::CLASSIFICATION_ACTIVE => [EngagementClassificationService::CLASSIFICATION_ACTIVE, 'High'],
                EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE => [EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE, 'Medium'],
                EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP => [EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, 'Low'],
                EngagementClassificationService::CLASSIFICATION_INSUFFICIENT, 'Insufficient Information' => [EngagementClassificationService::CLASSIFICATION_INSUFFICIENT, 'Inactive'],
                default => [$classificationFilter],
            };
            if (in_array($classificationFilter, [EngagementClassificationService::CLASSIFICATION_INSUFFICIENT, 'Insufficient Information'], true)) {
                $membersQuery->where(function ($q) use ($aliases) {
                    $q->whereDoesntHave('engagementResult')
                        ->orWhereHas('engagementResult', fn ($rq) => $rq->whereIn('classification', $aliases));
                });
            } else {
                $membersQuery->whereHas('engagementResult', fn ($rq) => $rq->whereIn('classification', $aliases));
            }
        }

        $members = $membersQuery
            ->paginate($perPage)
            ->withQueryString();
        $classifiedCounts = $satellite->members()
            ->whereHas('engagementResult')
            ->with('engagementResult')
            ->get()
            ->pluck('engagementResult.classification')
            ->countBy();
        $memberCount = $satellite->members()->count();
        $classifiedTotal = (int) $classifiedCounts->sum();

        $classifications = [
            EngagementClassificationService::CLASSIFICATION_ACTIVE => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_ACTIVE] ?? 0) + ($classifiedCounts['High'] ?? 0)),
            EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE] ?? 0) + ($classifiedCounts['Medium'] ?? 0)),
            EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP] ?? 0) + ($classifiedCounts['Low'] ?? 0)),
            EngagementClassificationService::CLASSIFICATION_INSUFFICIENT => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_INSUFFICIENT] ?? 0) + ($classifiedCounts['Inactive'] ?? 0) + max(0, $memberCount - $classifiedTotal)),
        ];

        return view('satellites.engagement.index', [
            'satellite' => $satellite,
            'members' => $members,
            'classifications' => $classifications,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'canManageSatellite' => $user->canManageSatellite($satellite),
            'search' => $search,
            'classificationFilter' => $classificationFilter,
            'roleFilter' => $roleFilter,
        ]);
    }

    /**
     * Show classification detail for a specific member.
     */
    public function show(Satellite $satellite, Member $member): View
    {
        abort_unless($member->satellite_id === $satellite->id, 404);

        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAccessSatellite($satellite), 403);

        $result = $member->engagementResult;
        $result?->load(['modelRun', 'generator']);

        return view('satellites.engagement.show', [
            'satellite' => $satellite,
            'member' => $member,
            'result' => $result,
            'canManageSatellite' => $user->canManageSatellite($satellite),
            'recommendationActions' => $result && $user->canManageSatellite($satellite)
                ? RecommendationAction::approved()->orderBy('title')->get()->filter(fn (RecommendationAction $action): bool => $action->supportsResult($result))
                : collect(),
            'recommendationAssignees' => $user->canManageSatellite($satellite)
                ? User::orderBy('name')->get(['id', 'name', 'district'])->filter(fn (User $assignee): bool => $assignee->canAccessSatellite($satellite))
                : collect(),
        ]);
    }

    /**
     * Classify all members in a satellite.
     */
    public function classify(Satellite $satellite): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canManageSatellite($satellite), 403);

        $members = $satellite->members()->get();
        $count = 0;

        foreach ($members as $member) {
            $classification = $this->classificationService->classifyMember($member);
            $this->classificationService->storeClassification($classification, Auth::id());
            $count++;
        }

        return redirect()->route('satellites.engagement.index', $satellite)
            ->with('success', "Engagement classification generated for $count members.");
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }
}
