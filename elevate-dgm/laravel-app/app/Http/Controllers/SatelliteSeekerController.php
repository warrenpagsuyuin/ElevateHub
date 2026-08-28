<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SatelliteSeekerController extends Controller
{
    private const PAGE_SIZE = 10;

    public function index(Satellite $satellite): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canOpenSatellite($satellite, $user), 403);

        $satellite->load('assignedUser');
        $seekers = $satellite->seekers()
            ->whereNull('converted_member_id')
            ->whereNull('archived_at')
            ->orderBy('name')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();
        $leaderOptions = $satellite->members()
            ->where('status', Member::STATUS_ACTIVE)
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (Member $leader): array => [$leader->display_name => $leader->display_name])
            ->all();

        return view('satellites.seekers', [
            'satellite' => $satellite,
            'seekers' => $seekers,
            'statuses' => Seeker::STATUSES,
            'notificationCount' => $satellite->seekers()->whereNull('converted_member_id')->whereNull('archived_at')->where('status', Seeker::STATUS_PENDING)->count(),
            'totalSeekers' => $seekers->total(),
            'pendingFollowups' => $seekers->where('status', Seeker::STATUS_PENDING)->count(),
            'totalMembers' => (int) $satellite->members_count,
            'totalLeaders' => (int) $satellite->leaders_count,
            'leaderOptions' => $leaderOptions,
        ]);
    }

    public function assignLeader(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse
    {
        $this->authorizeSeeker($satellite, $seeker);
        $leaders = array_keys($this->leaderOptions($satellite));
        $validated = $request->validate([
            'dgroup_leader_name' => ['required', 'string', Rule::in($leaders)],
        ]);

        $seeker->update(['dgroup_leader_name' => $validated['dgroup_leader_name']]);

        return back()->with('status', 'DGroup Leader assigned successfully.');
    }

    public function convertToMember(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse
    {
        $this->authorizeSeeker($satellite, $seeker);
        abort_if($seeker->converted_member_id !== null, 422, 'This seeker has already been added to Members.');
        abort_if(blank($seeker->dgroup_leader_name), 422, 'Assign a DGroup Leader before converting this seeker to a member.');

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(Member::GENDERS)],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'email_address' => ['required', 'email', 'max:255', Rule::unique('members', 'email_address')->where(fn ($query) => $query->where('satellite_id', $satellite->id))],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'facebook_link' => ['nullable', 'url', 'max:255'],
            'district' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'campus_school' => ['required', 'string', 'max:180'],
            'active_disciples' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'year_joined' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'ministry_involvement' => ['nullable', 'string', 'max:180'],
            'weekly_dgroups_led' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'generation' => ['nullable', 'string', 'max:120'],
            'refresh_date' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'month' => ['nullable', 'string', 'max:30'],
        ]);

        $member = DB::transaction(function () use ($satellite, $seeker, $validated): Member {
            $firstName = trim($validated['first_name']);
            $lastName = trim($validated['last_name']);
            $fullName = trim("{$firstName} {$lastName}");
            $birthdate = $validated['birthdate'] ?? null;
            $member = Member::create([
                'satellite_id' => $satellite->id,
                'status' => Member::STATUS_NEW,
                'dgroup_role' => Member::ROLE_MEMBER,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'full_name' => $fullName,
                'name' => $fullName,
                'gender' => $validated['gender'] ?? $seeker->gender,
                'email_address' => $validated['email_address'],
                'email' => $validated['email_address'],
                'contact_number' => $validated['contact_number'] ?? $seeker->contact_number,
                'phone' => $validated['contact_number'] ?? $seeker->contact_number,
                'facebook_link' => $validated['facebook_link'] ?? $seeker->facebook_link,
                'birthdate' => $birthdate,
                'age' => $birthdate ? Carbon::parse($birthdate)->age : ($validated['age'] ?? $seeker->age),
                'district' => $validated['district'],
                'area' => $validated['area'] ?? $seeker->area,
                'campus_school' => $validated['campus_school'],
                'dgroup_leader_name' => $seeker->dgroup_leader_name,
                'active_disciples' => $validated['active_disciples'] ?? $seeker->active_disciples,
                'year_joined' => $validated['year_joined'] ?? $seeker->year_joined,
                'ministry_involvement' => $validated['ministry_involvement'] ?? $seeker->ministry_involvement,
                'weekly_dgroups_led' => $validated['weekly_dgroups_led'] ?? $seeker->weekly_dgroups_led,
                'generation' => $validated['generation'] ?? $seeker->generation,
                'refresh_date' => $validated['refresh_date'] ?? $seeker->refresh_date,
                'year' => $validated['year'] ?? $seeker->year,
                'month' => $validated['month'] ?? $seeker->month,
                'role' => 'member',
            ]);
            $seeker->update([
                'status' => Seeker::STATUS_SUCCESSFUL,
                'converted_member_id' => $member->id,
            ]);

            return $member;
        });

        $this->refreshSatelliteMemberCounts($satellite);

        return redirect()->route('satellites.seekers', $satellite)
            ->with('status', "{$member->display_name} has been added to {$satellite->display_name} Members as a New member.");
    }

    public function updateStatus(Request $request, Satellite $satellite, Seeker $seeker): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canOpenSatellite($satellite, $user), 403);
        $this->authorizeSeeker($satellite, $seeker);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Seeker::STATUSES)],
        ]);

        if ($validated['status'] === Seeker::STATUS_SUCCESSFUL && blank($seeker->converted_member_id)) {
            return back()->with('error', blank($seeker->dgroup_leader_name)
                ? 'Assign a DGroup Leader before converting this seeker to a member.'
                : 'Complete the member information before converting this seeker.');
        }

        $seeker->update([
            'status' => Seeker::normalizeStatus($validated['status']),
            'archived_at' => $validated['status'] === Seeker::STATUS_UNSUCCESSFUL ? now() : null,
        ]);

        return redirect()
            ->route('satellites.seekers', $satellite)
            ->with('status', $validated['status'] === Seeker::STATUS_UNSUCCESSFUL
                ? "{$seeker->name} was moved to the archive."
                : "{$seeker->name} status updated.");
    }

    public function bulkUpdateStatus(Request $request, Satellite $satellite): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canOpenSatellite($satellite, $user), 403);

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
        $query->update([
            'status' => $status,
            'archived_at' => $status === Seeker::STATUS_UNSUCCESSFUL ? now() : null,
        ]);

        return redirect()
            ->route('satellites.seekers', $satellite)
            ->with('status', $status === Seeker::STATUS_UNSUCCESSFUL
                ? $count.' seeker'.($count === 1 ? '' : 's').' moved to the archive.'
                : $count.' seeker'.($count === 1 ? '' : 's').' moved to '.$status.'.');
    }

    private function canOpenSatellite(Satellite $satellite, User $user): bool
    {
        $role = str_replace([' ', '-'], '_', strtolower((string) ($user->role ?: 'member')));

        return $role !== 'admin' && $satellite->assigned_user_id === $user->id;
    }

    private function authorizeSeeker(Satellite $satellite, Seeker $seeker): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canOpenSatellite($satellite, $user), 403);
        abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
    }

    private function leaderOptions(Satellite $satellite): array
    {
        return $satellite->members()
            ->where('status', Member::STATUS_ACTIVE)
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (Member $leader): array => [$leader->display_name => $leader->display_name])
            ->all();
    }

    private function refreshSatelliteMemberCounts(Satellite $satellite): void
    {
        $satellite->update([
            'members_count' => $satellite->members()->count(),
            'new_members_count' => $satellite->members()->where('status', Member::STATUS_NEW)->count(),
            'leaders_count' => $satellite->members()->where('dgroup_role', Member::ROLE_LEADER)->count(),
        ]);
    }
}
