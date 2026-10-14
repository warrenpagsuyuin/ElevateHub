<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaderReconciliationService
{
    public function apply(array $pairs): int
    {
        return DB::transaction(function () use ($pairs) {
            $ids = collect($pairs)->flatMap(fn ($p) => [$p['member_id'], $p['leader_id']])->unique()->sort()->values();
            $records = Member::whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $changed = 0;
            foreach ($pairs as $pair) {
                $member = $records->get($pair['member_id']);
                $leader = $records->get($pair['leader_id']);
                if (! $member || ! $leader || $member->dgroup_role !== Member::ROLE_MEMBER || $leader->dgroup_role !== Member::ROLE_LEADER
                    || (int) $member->satellite_id !== (int) $pair['satellite_id'] || $member->satellite_id !== $leader->satellite_id
                    || $member->display_name !== $pair['member_name'] || $leader->display_name !== $pair['leader_name']
                    || $member->dgroup_leader_name !== $pair['leader_name']
                    || ($member->leader_member_id !== null && (int) $member->leader_member_id !== $leader->id)
                    || app(LeaderAssignmentService::class)->exactLeaderId($member->dgroup_leader_name, $member->satellite_id) !== $leader->id) {
                    throw new RuntimeException('Approved reconciliation has changed. No links applied; generate and review a new preview.');
                }
                if ($member->leader_member_id === null) {
                    // Only the approved FK changes; preserve all original text and timestamps.
                    DB::table('members')->where('id', $member->id)->update(['leader_member_id' => $leader->id]);
                    $changed++;
                }
            }

            return $changed;
        }, 3);
    }
}
