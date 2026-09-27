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

        $allowed = $ability === 'manage'
            ? $user->canManageSatellite($satellite)
            : $user->canAccessSatellite($satellite);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
