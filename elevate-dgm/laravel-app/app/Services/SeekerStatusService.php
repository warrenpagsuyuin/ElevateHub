<?php

namespace App\Services;

use App\Models\{Member, Satellite, Seeker, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeekerStatusService
{
    public function __construct(
        private SeekerArchiveService $archive,
        private SeekerLeaderRequirement $leaderRequirement,
    ) {}

    public function change(Seeker $seeker, string $status, User $actor, ?Satellite $scope = null): array
    {
        // Accept API lowercase values, but preserve the application's canonical values.
        $status = ucfirst(strtolower(trim($status)));
        if (!in_array($status, Seeker::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose Pending, Successful, or Unsuccessful.']);
        }

        return DB::transaction(function () use ($seeker, $status, $actor, $scope) {
            $row = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            if ($scope) {
                abort_unless($row->assigned_satellite_slug === $scope->slug, 404);
                abort_unless($actor->canChangeSeekerStatus($scope), 403);
            } else {
                $this->authorize($row, $actor, null);
            }

            $leader = null;
            if (in_array($status, [Seeker::STATUS_SUCCESSFUL, Seeker::STATUS_UNSUCCESSFUL], true)) {
                $leader = $this->leaderRequirement->requireForFinalStatus($row);
            }

            if ($row->converted_member_id !== null) {
                if ($status !== Seeker::STATUS_SUCCESSFUL) {
                    throw ValidationException::withMessages(['status' => 'This seeker has moved to Members. Manage the linked member there.']);
                }
                return $this->result($row, 'Already moved to Members', true);
            }
            if ($row->archived_at) {
                if ($row->status === $status) return $this->result($row, 'Seeker archived');
                throw ValidationException::withMessages(['status' => 'Restore this seeker from the Archived view first.']);
            }
            if ($status === Seeker::STATUS_PENDING && $row->status === $status) {
                return $this->result($row, 'Status unchanged');
            }

            $existing = false;
            if ($status === Seeker::STATUS_SUCCESSFUL) {
                $satellite = Satellite::where('slug', $row->assigned_satellite_slug)->lockForUpdate()->first();
                // Serialize conversions in a satellite, including different seekers sharing an email.
                if (!$satellite) {
                    throw ValidationException::withMessages(['status' => 'Assign a satellite before moving this seeker to Members.']);
                }
                $email = trim((string) $row->email);
                $member = $email === '' ? null : $satellite->members()->where(function ($query) use ($email) {
                    $query->whereRaw('LOWER(TRIM(email_address)) = ?', [strtolower($email)])
                        ->orWhereRaw('LOWER(TRIM(email)) = ?', [strtolower($email)]);
                })->lockForUpdate()->first();
                $existing = $member !== null;
                if (!$member) {
                    $member = Member::create([
                        'satellite_id' => $satellite->id,
                        'name' => $row->name, 'full_name' => $row->name,
                        'first_name' => $row->first_name, 'last_name' => $row->last_name,
                        'email' => $email ?: null, 'email_address' => $email ?: null,
                        'phone' => $row->contact_number, 'contact_number' => $row->contact_number,
                        'facebook_link' => $row->facebook_link, 'gender' => $row->gender,
                        'birthdate' => $row->birthdate, 'age' => $row->age,
                        'campus_school' => $row->school, 'district' => $satellite->name,
                        'area' => $row->area, 'dgroup_leader_name' => $leader->display_name,
                        'leader_member_id' => $leader->id,
                        'status' => Member::STATUS_NEW, 'dgroup_role' => Member::ROLE_MEMBER, 'role' => 'member',
                    ]);
                }
                $row->forceFill(['converted_member_id' => $member->id, 'converted_at' => now()])->save();
            }
            $row = $this->archive->changeStatus($row, $status, $actor, $scope, $leader);
            $this->refreshSeekerCount($row);
            return $this->result($row, $status === Seeker::STATUS_SUCCESSFUL
                ? ($existing ? 'Moved to Members — linked to an existing member with the same email.' : 'Moved to Members')
                : ($row->archived_at ? 'Seeker archived' : 'Status updated'), $existing);
        }, 3);
    }

    public function restore(Seeker $seeker, User $actor, Satellite $scope): array
    {
        return DB::transaction(function () use ($seeker, $actor, $scope) {
            $row = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            $this->authorize($row, $actor, $scope);
            $row = $this->archive->restore($row, $actor, Seeker::STATUS_PENDING, false, 'Restored to Pending from Archived.');
            $this->refreshSeekerCount($row);
            return $this->result($row, 'Seeker restored to Pending');
        }, 3);
    }

    private function authorize(Seeker $seeker, User $actor, ?Satellite $scope): void
    {
        if ($scope) abort_unless($seeker->assigned_satellite_slug === $scope->slug, 404);
        abort_unless($actor->normalizedRole() === 'admin' ||
            ($seeker->satellite && $actor->canManageSeekers($seeker->satellite)), 403);
    }

    private function result(Seeker $seeker, string $message, bool $existing = false): array
    {
        $satellite = $seeker->satellite;
        $active = $satellite?->seekers()->whereNull('archived_at')->whereNull('converted_member_id');
        return [
            'message' => $message, 'status' => $seeker->status,
            'archived' => $seeker->archived_at !== null, 'member_id' => $seeker->converted_member_id,
            'linked_existing' => $existing,
            'counts' => [
                'active' => $active ? (clone $active)->count() : 0,
                'archived' => $satellite?->seekers()->whereNotNull('archived_at')->count() ?? 0,
                'pending' => $active ? (clone $active)->where('status', Seeker::STATUS_PENDING)->count() : 0,
                'members' => $satellite?->members()->count() ?? 0,
            ],
        ];
    }

    private function refreshSeekerCount(Seeker $seeker): void
    {
        $seeker->satellite?->update(['seekers_count' => $seeker->satellite->seekers()
            ->whereNull('archived_at')->whereNull('converted_member_id')->count()]);
    }
}
