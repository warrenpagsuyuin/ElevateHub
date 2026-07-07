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

        $allowedRoles = array_map(
            fn (string $role): string => $this->normalizeRole($role),
            $roles
        );

        $userRole = $this->normalizeRole((string) ($request->user()->role ?: 'member'));

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
