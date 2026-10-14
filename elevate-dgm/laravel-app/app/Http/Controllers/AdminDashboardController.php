<?php

namespace App\Http\Controllers;

use App\Services\DashboardDirectoryService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(DashboardDirectoryService $service): View
    {
        return view('admin.dashboard', ['movementSummaryCards' => $service->summary()]);
    }
}
