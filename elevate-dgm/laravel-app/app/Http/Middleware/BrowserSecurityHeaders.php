<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrowserSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The deployment proxy must report HTTPS correctly before enabling production.
        $isProduction = (app()->isProduction() || config('app.env') === 'production')
            && ! $request->expectsJson()
            && ! $request->is('admin/analytics/movement-data*');

        $response = $isProduction && ! $request->isSecure()
            ? redirect()->secure($request->getRequestUri(), 308)
            : $next($request);

        // Compatible with existing inline scripts and the local Vite development server.
        // A strict script-src policy needs a separate nonce migration for Blade scripts.
        $headers = [
            'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($request->is('/') || $request->is('login', 'login/*', 'logout')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
