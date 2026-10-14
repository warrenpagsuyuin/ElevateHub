<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use Illuminate\Validation\ValidationException;

class SeekerLeaderRequirement
{
    public function requireForFinalStatus(Seeker $seeker): Member
    {
        $satellite = Satellite::query()
            ->where('slug', $seeker->assigned_satellite_slug)
            ->lockForUpdate()
            ->first();
        $leaderName = trim((string) $seeker->dgroup_leader_name);
        $leader = null;

        if ($satellite && $leaderName !== '') {
            $leader = Member::query()->active()
                ->where('satellite_id', $satellite->id)
                ->where('dgroup_role', Member::ROLE_LEADER)
                ->where('status', Member::STATUS_ACTIVE)
                ->where(function ($query) use ($leaderName): void {
                    $query->where('full_name', $leaderName)
                        ->orWhere(function ($fallback) use ($leaderName): void {
                            $fallback->where(function ($name): void {
                                $name->whereNull('full_name')->orWhere('full_name', '');
                            })->where('name', $leaderName);
                        });
                })
                ->lockForUpdate()
                ->first();
        }

        if (!$leader) {
            throw ValidationException::withMessages([
                'status' => "Assign a DGroup Leader to {$seeker->name} before marking them as successful or unsuccessful.",
            ]);
        }

        return $leader;
    }
}
