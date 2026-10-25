<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = auth()->user();
        $role = str_replace([' ', '-'], '_', strtolower((string) ($user->role ?: 'member')));
        $satellite = $user->satelliteForDistrict();

        if ($satellite) {
            return redirect()->route('satellites.dashboard', [$satellite, 'month' => 'Live', 'year' => now(config('app.timezone'))->year]);
        }

        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'district', 'district_user', 'satellite', 'satellite_user' => redirect()->route('district.dashboard'),
            default => redirect()->route('home'),
        };
    }
}
