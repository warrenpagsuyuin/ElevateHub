<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

class LeaderAssignmentService
{
    public function exactLeaderId(?string $name, ?int $satellite): ?int
    {
        if (! $name || ! $satellite) {
            return null;
        }
        // Exact comparison in PHP avoids case-insensitive database collations.
        $matches = Member::active()->where('satellite_id', $satellite)->where('dgroup_role', Member::ROLE_LEADER)
            ->get(['id', 'full_name', 'name'])->filter(fn ($leader) => $leader->display_name === $name);

        return $matches->count() === 1 ? $matches->first()->id : null;
    }

    public function prepare(Member $member): void
    {
        if ($member->exists && $member->isDirty(['satellite_id', 'dgroup_role']) && $member->assignedMembers()->exists()) {
            throw ValidationException::withMessages(['leader_member_id' => 'Reassign members assigned to this leader before changing their district or role.']);
        }
        if (! $member->exists && ! $member->leader_member_id && $member->dgroup_role === Member::ROLE_MEMBER) {
            $member->leader_member_id = $this->exactLeaderId($member->dgroup_leader_name, $member->satellite_id);
        }
        if (! $member->leader_member_id) {
            return;
        }
        $leader = Member::active()->whereKey($member->leader_member_id)->lockForUpdate()->first();
        if (! $leader || $leader->dgroup_role !== Member::ROLE_LEADER || (int) $leader->satellite_id !== (int) $member->satellite_id || $leader->id === $member->id) {
            throw ValidationException::withMessages(['leader_member_id' => 'Choose a DGroup Leader in the same district. A member cannot lead themselves.']);
        }
        $member->dgroup_leader_name = $leader->display_name;
    }

    public function synchronizeName(Member $leader): void
    {
        if ($leader->dgroup_role === Member::ROLE_LEADER && $leader->wasChanged(['full_name', 'name'])) {
            $leader->assignedMembers()->update(['dgroup_leader_name' => $leader->display_name]);
        }
    }
}
