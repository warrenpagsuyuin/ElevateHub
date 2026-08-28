<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const MOVEMENT_SUMMARY_CARDS = [
        [
            'label' => 'DGROUP LEADERS',
            'total' => 3006,
            'segments' => [
                ['label' => 'MAIN', 'value' => 515],
                ['label' => 'NCP', 'value' => 2178],
                ['label' => 'ICP', 'value' => 313],
            ],
        ],
        [
            'label' => 'DGROUPS',
            'total' => 3147,
            'segments' => [
                ['label' => 'MAIN', 'value' => 546],
                ['label' => 'NCP', 'value' => 2286],
                ['label' => 'ICP', 'value' => 315],
            ],
        ],
        [
            'label' => 'DGROUP MEMBERS',
            'total' => 13420,
            'segments' => [
                ['label' => 'MAIN', 'value' => 1911],
                ['label' => 'NCP', 'value' => 10211],
                ['label' => 'ICP', 'value' => 1298],
            ],
        ],
        [
            'label' => 'CAMPUSES',
            'total' => 335,
            'segments' => [
                ['label' => 'MAIN', 'value' => 95],
                ['label' => 'NCP', 'value' => 240],
                ['label' => 'ICP', 'value' => null],
            ],
        ],
    ];

    public function index(): View
    {
        return view('admin.dashboard', [
            'movementSummaryCards' => self::MOVEMENT_SUMMARY_CARDS,
        ]);
    }
}
