<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use App\Services\SeekerStatusService;
use App\Services\SeekerLeaderAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SatelliteSeekerController extends Controller
{
    private const PAGE_SIZE = 10;

    private const PAGE_SIZES = [10, 25, 50, 100];

    public function index(Request $request, Satellite $satellite): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User && ($user->normalizedRole() === 'admin' || $user->canAccessSatellite($satellite)), 403);

        $archive = $request->query('view') === 'archive';
        abort_if($archive && !$user->canManageSeekers($satellite), 403);
        $sorts = ['name' => 'name', 'school' => 'school', 'district' => 'assigned_district', 'status' => 'status', 'archived_at' => 'archived_at'];
        $sort = array_key_exists($request->query('sort', 'name'), $sorts) ? $request->query('sort', 'name') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('search', ''));
        $assignmentFilter = trim((string) $request->query('assignment', ''));
        $leaderFilter = trim((string) $request->query('leader', ''));

        $satellite->load('assignedUsers');
        $seekersQuery = $satellite->seekers()
            ->with(['archivedBy', 'latestArchiveLog'])
            ->when($archive, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('converted_member_id')->whereNull('archived_at'));

        if ($search !== '') {
            $seekersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('school', 'like', '%'.$search.'%')
                    ->orWhere('contact_number', 'like', '%'.$search.'%')
                    ->orWhere('dgroup_leader_name', 'like', '%'.$search.'%');
            });
        }

        if (! $archive) {
            if ($assignmentFilter === 'unassigned') {
                $seekersQuery->where(function ($q) {
                    $q->whereNull('dgroup_leader_name')->orWhere('dgroup_leader_name', '');
                });
            } elseif ($assignmentFilter === 'assigned') {
                $seekersQuery->whereNotNull('dgroup_leader_name')->where('dgroup_leader_name', '<>', '');
            }

            if ($leaderFilter !== '') {
                $seekersQuery->where('dgroup_leader_name', $leaderFilter);
            }
        }

        $seekers = $seekersQuery
            ->orderBy($sorts[$sort], $direction)->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $leaderOptions = $this->leaderOptions($satellite);
        $tabCounts = $satellite->seekers()
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL AND converted_member_id IS NULL THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL AND converted_member_id IS NULL AND (dgroup_leader_name IS NULL OR dgroup_leader_name = "") THEN 1 ELSE 0 END) as unassigned_count')
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL AND converted_member_id IS NULL AND dgroup_leader_name IS NOT NULL AND dgroup_leader_name <> "" THEN 1 ELSE 0 END) as assigned_count')
            ->selectRaw('SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) as archived_count')
            ->first();

        return view('satellites.seekers', [
            'sort' => $sort,
            'direction' => $direction,
            'archive' => $archive,
            'archiveCount' => (int) ($tabCounts->archived_count ?? 0),
            'activeCount' => (int) ($tabCounts->active_count ?? 0),
            'unassignedCount' => (int) ($tabCounts->unassigned_count ?? 0),
            'assignedCount' => (int) ($tabCounts->assigned_count ?? 0),
            'satellite' => $satellite,
            'seekers' => $seekers,
            'statuses' => Seeker::STATUSES,
            'notificationCount' => $satellite->seekers()->whereNull('converted_member_id')->whereNull('archived_at')->where('status', Seeker::STATUS_PENDING)->count(),
            'totalSeekers' => $seekers->total(),
            'pendingFollowups' => $seekers->where('status', Seeker::STATUS_PENDING)->count(),
            'totalMembers' => (int) $satellite->members_count,
            'totalLeaders' => (int) $satellite->leaders_count,
            'leaderOptions' => $leaderOptions,
            'canChangeSeekerStatus' => $user->canChangeSeekerStatus($satellite),
            'canManageSeekers' => $user->canManageSeekers($satellite),
            'canManageFollowUps' => $user->canManageSatelliteFollowUps($satellite),
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'search' => $search,
            'assignmentFilter' => $assignmentFilter,
            'leaderFilter' => $leaderFilter,
        ]);
    }

    public function assignLeader(Request $request, Satellite $satellite, Seeker $seeker, SeekerLeaderAssignmentService $assignment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canAssignSeekerLeader($satellite), 403);

        $validated = $request->validate(['leader_id' => ['required', 'integer']], [
            'leader_id.required' => 'Select a DGroup Leader.',
            'leader_id.integer' => 'Select a valid DGroup Leader.',
        ]);
        $leader = $assignment->assign($seeker, $satellite, (int) $validated['leader_id']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'DGroup Leader assigned successfully.',
                'leader' => ['id' => $leader->id, 'name' => $leader->display_name],
            ]);
        }

        return back()->with('status', 'DGroup Leader assigned successfully.');
    }

    public function convertToMember(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse|JsonResponse
    {
        // Retain old callers, using the same saved seeker data and idempotent transaction.
        $request->merge(['status' => Seeker::STATUS_SUCCESSFUL]);
        return $this->updateStatus($request, $satellite, $seeker);
    }

    public function updateStatus(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse|JsonResponse
    {
        abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
        abort_unless($request->user() instanceof User && $request->user()->canChangeSeekerStatus($satellite), 403);
        $data = $request->validate(['status' => ['required', 'string']]);
        $result = app(SeekerStatusService::class)->change($seeker, $data['status'], $request->user(), $satellite);
        return $request->expectsJson() ? response()->json($result)
            : redirect()->route('satellites.seekers', $satellite)->with('status', $result['message']);
    }

    public function restore(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse|JsonResponse
    {
        $this->authorizeSeeker($satellite, $seeker);
        $result = app(SeekerStatusService::class)->restore($seeker, $request->user(), $satellite);
        return $request->expectsJson() ? response()->json($result)
            : redirect()->route('satellites.seekers', [$satellite, 'view' => 'archive'])->with('status', $result['message']);
    }
    public function bulkUpdateStatus(Request $request, Satellite $satellite): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canManageSeekers($satellite), 403);

        $validated = $request->validate([
            'selection_scope' => ['required', 'in:ids,matching'],
            'ids' => ['array'],
            'ids.*' => ['integer', 'exists:seekers,id'],
            'status' => ['required', 'string', 'in:'.implode(',', Seeker::STATUSES)],
        ]);

        $query = $satellite->seekers();

        if ($validated['selection_scope'] !== 'matching') {
            $query->whereKey($validated['ids'] ?? []);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return redirect()
                ->route('satellites.seekers', $satellite)
                ->with('status', 'Select at least one seeker before changing status.');
        }

        $status = Seeker::normalizeStatus($validated['status']);
        DB::transaction(function () use ($query, $status, $user): void {
            foreach ($query->orderBy('id')->get() as $seeker) {
                app(SeekerStatusService::class)->change($seeker, $status, $user);
            }
        }, 3);

        return redirect()
            ->route('satellites.seekers', $satellite)
            ->with('status', in_array($status, config('seekers.final_statuses'), true)
                ? $count.' seeker'.($count === 1 ? '' : 's').' moved to the archive.'
                : $count.' seeker'.($count === 1 ? '' : 's').' moved to '.$status.'.');
    }

    private function authorizeSeeker(Satellite $satellite, Seeker $seeker): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSeekers($satellite), 403);
        abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
    }

    private function leaderOptions(Satellite $satellite): array
    {
        return $satellite->members()->active()
            ->where('status', Member::STATUS_ACTIVE)
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (Member $leader): array => [$leader->id => $leader->display_name])
            ->all();
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true) ? $perPage : self::PAGE_SIZE;
    }

}
