<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        $role = str_replace([' ', '-'], '_', strtolower((string) (auth()->user()->role ?: 'member')));

        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'district', 'district_user', 'satellite', 'satellite_user' => redirect()->route('district.dashboard'),
            default => redirect()->route('home'),
        };
    }
}
