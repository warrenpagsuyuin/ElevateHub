<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SatelliteFollowUpController extends Controller
{
    private const PAGE_SIZE = 10;

    private const PAGE_SIZES = [10, 25, 50, 100];

    private const SORT_OPTIONS = [
        'date' => 'follow_up_date',
        'created' => 'created_at',
        'name' => 'followable_name',
    ];

    public function __construct(private SentimentAnalysisService $sentimentService) {}

    public function index(Request $request, Satellite $satellite): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->normalizedRole() === 'admin' || $user->canAccessSatellite($satellite)), 403);

        $satellite->load('assignedUsers');
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);

        $query = FollowUp::query()
            ->where('satellite_id', $satellite->id)
            ->with(['seeker', 'member', 'leader']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('seeker', fn ($sq) => $sq->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('member', fn ($mq) => $mq->where('name', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('leader', fn ($lq) => $lq->where('name', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%'));
            });
        }

        if ($filters['target_type'] === 'seeker') {
            $query->whereNotNull('seeker_id');
        } elseif ($filters['target_type'] === 'member') {
            $query->whereNotNull('member_id');
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['method'] !== '') {
            $query->where('method', $filters['method']);
        }

        if ($filters['sentiment'] === 'Unanalyzed') {
            $query->whereNull('sentiment_label');
        } elseif ($filters['sentiment'] !== '') {
            $query->where('sentiment_label', $filters['sentiment']);
        }

        $followUps = $query
            ->orderBy(self::SORT_OPTIONS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $sentimentSummary = $this->sentimentService->summarize(
            FollowUp::where('satellite_id', $satellite->id)->get(['sentiment_label'])
        );

        $canManageFollowUps = $user->canManageSatelliteFollowUps($satellite);

        return view('satellites.follow-ups.index', [
            'satellite' => $satellite,
            'followUps' => $followUps,
            'statuses' => FollowUp::STATUSES,
            'methods' => FollowUp::METHODS,
            'sentiments' => FollowUp::SENTIMENTS,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'sentimentSummary' => $sentimentSummary,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'canManageSatellite' => $canManageFollowUps,
        ]);
    }

    public function create(Satellite $satellite, ?Seeker $seeker = null, ?Member $member = null): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSatelliteFollowUps($satellite), 403);

        if ($seeker) {
            abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
            abort_if($seeker->archived_at, 422, 'Restore this seeker before adding a follow-up.');
        }

        if ($member) {
            abort_unless($member->satellite_id === $satellite->id, 404);
        }

        return view('satellites.follow-ups.create', [
            'satellite' => $satellite,
            'seeker' => $seeker,
            'member' => $member,
            'methods' => FollowUp::METHODS,
        ]);
    }

    public function store(Request $request, Satellite $satellite): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSatelliteFollowUps($satellite), 403);

        $validated = $request->validate([
            'seeker_id' => ['nullable', 'integer', 'exists:seekers,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'target' => ['nullable', 'string', 'regex:/^(seeker|member)-[0-9]+$/'],
            'follow_up_date' => ['nullable', 'date'],
            'method' => ['required', Rule::in(FollowUp::METHODS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(FollowUp::STATUSES)],
        ]);

        $followUp = DB::transaction(function () use ($validated, $satellite, $user) {
            [$seekerId, $memberId] = $this->resolveTarget($validated, $satellite);

            $followUp = FollowUp::create([
                'satellite_id' => $satellite->id,
                'seeker_id' => $seekerId,
                'member_id' => $memberId,
                'created_by_id' => $user->id,
                'follow_up_date' => $validated['follow_up_date'] ?? null,
                'method' => $validated['method'],
                'notes' => $validated['notes'] ?? null,
                'status' => FollowUp::normalizeStatus($validated['status']),
            ]);

            return $followUp;
        }, 3);

        $this->sentimentService->analyzeFollowUp($followUp);

        return redirect()
            ->route('satellites.follow-ups.index', $satellite)
            ->with('status', 'Follow-up created successfully.');
    }

    public function update(Request $request, Satellite $satellite, FollowUp $followUp): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSatelliteFollowUps($satellite), 403);
        abort_unless($followUp->satellite_id === $satellite->id, 404);

        $validated = $request->validate([
            'follow_up_date' => ['nullable', 'date'],
            'method' => ['required', Rule::in(FollowUp::METHODS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(FollowUp::STATUSES)],
        ]);

        $followUp->update([
            'follow_up_date' => $validated['follow_up_date'] ?? $followUp->follow_up_date,
            'method' => $validated['method'],
            'notes' => $validated['notes'] ?? $followUp->notes,
            'status' => FollowUp::normalizeStatus($validated['status']),
        ]);

        $this->sentimentService->analyzeFollowUp($followUp);

        return back()->with('status', 'Follow-up updated successfully.');
    }

    public function analyzeSentiment(Satellite $satellite): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSatelliteFollowUps($satellite), 403);

        $result = $this->sentimentService->analyzeQuery(
            FollowUp::where('satellite_id', $satellite->id)
        );

        return back()->with(
            'status',
            'Sentiment analysis updated for '.$result['count'].' follow-up '.($result['count'] === 1 ? 'entry.' : 'entries.')
        );
    }

    public function destroy(Satellite $satellite, FollowUp $followUp): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageSatelliteFollowUps($satellite), 403);
        abort_unless($followUp->satellite_id === $satellite->id, 404);

        $followUp->delete();

        return redirect()
            ->route('satellites.follow-ups.index', $satellite)
            ->with('status', 'Follow-up deleted successfully.');
    }

    private function filters(Request $request): array
    {
        $sentiment = (string) $request->query('sentiment', '');
        $validSentiments = array_merge(FollowUp::SENTIMENTS, ['Unanalyzed']);

        return [
            'search' => trim((string) $request->query('search', '')),
            'target_type' => (string) $request->query('target_type', ''),
            'status' => (string) $request->query('status', ''),
            'method' => (string) $request->query('method', ''),
            'sentiment' => in_array($sentiment, $validSentiments, true) ? $sentiment : '',
        ];
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'date');
        $direction = (string) $request->query('direction', 'desc');

        return [
            array_key_exists($sort, self::SORT_OPTIONS) ? $sort : 'date',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true) ? $perPage : self::PAGE_SIZE;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveTarget(array $validated, Satellite $satellite): array
    {
        $seekerId = $validated['seeker_id'] ?? null;
        $memberId = $validated['member_id'] ?? null;

        if (! empty($validated['target'])) {
            [$type, $id] = explode('-', (string) $validated['target'], 2);

            if ($type === 'seeker') {
                $seekerId = (int) $id;
                $memberId = null;
            } else {
                $memberId = (int) $id;
                $seekerId = null;
            }
        }

        if ($seekerId !== null && $memberId !== null) {
            throw ValidationException::withMessages([
                'target' => 'Select only one seeker or member for this follow-up.',
            ]);
        }

        if ($seekerId !== null) {
            $seeker = Seeker::whereKey($seekerId)->lockForUpdate()->firstOrFail();
            abort_if($seeker->archived_at, 422, 'Restore this seeker before adding a follow-up.');
            abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
        }

        if ($memberId !== null) {
            $member = Member::findOrFail($memberId);
            abort_unless((int) $member->satellite_id === (int) $satellite->id, 404);
        }

        return [$seekerId, $memberId];
    }
}
