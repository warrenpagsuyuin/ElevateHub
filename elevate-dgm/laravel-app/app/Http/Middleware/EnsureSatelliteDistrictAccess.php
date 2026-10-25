<?php

namespace App\Http\Middleware;

use App\Models\Satellite;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSatelliteDistrictAccess
{
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $freshUser = $user->fresh();

        if ($freshUser instanceof User) {
            $user = $freshUser;
            Auth::setUser($user);
            $request->setUserResolver(fn (): User => $user);
        }

        $satellite = $request->route('satellite');

        if (! $satellite instanceof Satellite) {
            $satellite = Satellite::where('slug', (string) $satellite)->firstOrFail();
            $request->route()?->setParameter('satellite', $satellite);
        }

        abort_if($satellite->archived_at !== null, 404);

        $allowed = match ($ability) {
            'seekers-view' => $user->normalizedRole() === 'admin' || $user->canAccessSatellite($satellite),
            'seeker-status' => $user->canManageSeekers($satellite),
            'seeker-status-update' => $user->canChangeSeekerStatus($satellite),
            'manage' => $user->canManageSatellite($satellite),
            'follow-ups-manage' => $user->canManageSatelliteFollowUps($satellite),
            'member-update' => $user->canUpdateSatelliteMembers($satellite),
            'seeker-leader-assignment' => $user->canAssignSeekerLeader($satellite),
            'members-view' => $user->canViewSatelliteMembers($satellite),
            default => $user->canAccessSatellite($satellite),
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
