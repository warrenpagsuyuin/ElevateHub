<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $userRole = $this->normalizeRole((string) ($request->user()->role ?: 'member'));

        // `role:except,admin` blocks the listed roles and allows everyone else.
        if (($roles[0] ?? null) === 'except') {
            $deniedRoles = array_map(
                fn (string $role): string => $this->normalizeRole($role),
                array_slice($roles, 1)
            );

            abort_if(in_array($userRole, $deniedRoles, true), 403);

            return $next($request);
        }

        $allowedRoles = array_map(
            fn (string $role): string => $this->normalizeRole($role),
            $roles
        );

        if ($allowedRoles !== [] && ! in_array($userRole, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }

    private function normalizeRole(string $role): string
    {
        return str_replace([' ', '-'], '_', strtolower($role));
    }
}
