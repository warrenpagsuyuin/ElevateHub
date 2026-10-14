<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeekerLeaderAssignmentService
{
    public function assign(Seeker $seeker, Satellite $satellite, int $leaderId): Member
    {
        try {
            return DB::transaction(function () use ($seeker, $satellite, $leaderId): Member {
                $seeker = Seeker::query()->whereKey($seeker->id)->lockForUpdate()->firstOrFail();
                abort_unless($seeker->assigned_satellite_slug === $satellite->slug, 404);
                abort_if($seeker->archived_at, 422, 'Restore this seeker before assigning a DGroup Leader.');

                $leader = $satellite->members()->active()
                    ->whereKey($leaderId)
                    ->where('status', Member::STATUS_ACTIVE)
                    ->where('dgroup_role', Member::ROLE_LEADER)
                    ->first();

                if (! $leader) {
                    throw ValidationException::withMessages([
                        'leader_id' => 'Choose an active DGroup Leader from this satellite.',
                    ]);
                }

                if ($seeker->dgroup_leader_name === $leader->display_name) {
                    return $leader;
                }

                $seeker->update(['dgroup_leader_name' => $leader->display_name]);

                $seekerSatellite = $seeker->satellite;
                abort_unless($seekerSatellite instanceof Satellite && $seekerSatellite->id === $satellite->id, 404);

                $pendingFollowUp = FollowUp::query()
                    ->where('seeker_id', $seeker->id)
                    ->where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)
                    ->where('status', FollowUp::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if ($pendingFollowUp) {
                    $pendingFollowUp->update(['leader_member_id' => $leader->id]);
                } else {
                    FollowUp::create([
                        'seeker_id' => $seeker->id,
                        'satellite_id' => $seekerSatellite->id,
                        'leader_member_id' => $leader->id,
                        'origin' => FollowUp::ORIGIN_LEADER_ASSIGNMENT,
                        'follow_up_date' => now()->toDateString(),
                        'method' => null,
                        'notes' => null,
                        'status' => FollowUp::STATUS_PENDING,
                    ]);
                }

                return $leader;
            }, 3);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'leader_id' => 'The leader assignment and follow-up could not be saved. Please try again.',
            ]);
        }
    }
}
