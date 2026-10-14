<?php
namespace App\Services;

use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaderMemberStatus
{
    public function record(User $actor, Member $member, array $data): MemberStatusHistory
    {
        abort_unless($actor->normalizedRole() === config('movement_analytics.active_definition.editor_role')
            && $member->satellite && $actor->canAccessSatellite($member->satellite), 403);
        return DB::transaction(function () use ($actor, $member, $data) {
            Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $latest = MemberStatusHistory::where('member_id',$member->id)->orderByDesc('version')->first();
            if (($latest?->version ?? 0) !== (int) $data['expected_version']) throw ValidationException::withMessages(['expected_version'=>'Status changed. Reload before confirming.']);
            $effective = CarbonImmutable::parse($data['effective_at']);
            if ($effective->isFuture() || ($latest && $effective->lt($latest->effective_at))) throw ValidationException::withMessages(['effective_at'=>'Use an effective time no earlier than the last decision and no later than now.']);
            if (!in_array($data['status'],config('movement_analytics.active_definition.allowed_statuses'),true)) throw ValidationException::withMessages(['status'=>'Unknown member status.']);
            if (!trim($data['reason'])) throw ValidationException::withMessages(['reason'=>'A reason is required.']);
            return MemberStatusHistory::create([
                'member_id'=>$member->id,'version'=>($latest?->version ?? 0)+1,'old_status'=>$latest?->new_status,
                'new_status'=>$data['status'],'actor_id'=>$actor->id,'effective_at'=>$effective,
                'recorded_at'=>now(),'reason'=>$data['reason'],
            ]);
        });
    }
}
