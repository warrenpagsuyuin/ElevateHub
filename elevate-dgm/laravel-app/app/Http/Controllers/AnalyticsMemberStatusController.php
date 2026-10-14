<?php
namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\LeaderMemberStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsMemberStatusController extends Controller
{
    public function store(Request $request, Member $member, LeaderMemberStatus $service)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(config('movement_analytics.active_definition.allowed_statuses'))],
            'effective_at'=>['required','date'], 'reason'=>['required','string','max:2000'],
            'expected_version'=>['required','integer','min:0'],
        ]);
        $event = $service->record($request->user(), $member, $data);
        return response()->json(['message'=>'Leader status decision recorded.','id'=>$event->id,'version'=>$event->version],201);
    }
}
