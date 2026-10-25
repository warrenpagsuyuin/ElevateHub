<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Services\DashboardDirectoryService;
use App\Services\SatelliteDashboardPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request, DashboardDirectoryService $service, SatelliteDashboardPeriod $periodResolver): View|JsonResponse
    {
        $period = $periodResolver->resolveMovementWide($request);

        $movementSummaryCards = $service->summary($period);

        $recentActivity = FollowUp::query()
            ->with(['member', 'seeker'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (FollowUp $followUp): array {
                $target = $followUp->member?->display_name
                    ?? $followUp->seeker?->name
                    ?? 'Unassigned target';

                return [
                    'target' => $target,
                    'status' => $followUp->status,
                    'statusLabel' => 'Status',
                    'method' => $followUp->method,
                    'date' => $followUp->follow_up_date?->format('M d, Y') ?? $followUp->created_at?->format('M d, Y'),
                ];
            })
            ->all();

        $viewData = array_merge($period, [
            'movementSummaryCards' => $movementSummaryCards,
            'recentActivity' => $recentActivity,
            'periodFormAction' => route('admin.dashboard'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('admin.dashboard-content', $viewData)->render(),
                'controls' => view('components.satellite-period-controls', $viewData)->render(),
                'activities' => $recentActivity,
                'updatedAt' => $period['dashboardUpdatedAt'],
                'selectedMonth' => $period['selectedDashboardMonth'],
                'selectedYear' => $period['selectedDashboardYear'],
                'date' => $period['dashboardDate'],
                'year' => $period['dashboardCurrentYear'],
                'month' => $period['dashboardCurrentMonth'],
            ])->header('Cache-Control', 'no-store, private');
        }

        return view('admin.dashboard', $viewData);
    }
}
