<?php

use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OtpLoginController::class, 'show'])->name('login');
Route::get('/login', [OtpLoginController::class, 'show']);

Route::middleware('guest')->group(function () {
    Route::post('/login/email', [OtpLoginController::class, 'checkEmail'])
        ->middleware('throttle:5,1')
        ->name('login.email');

    Route::post('/login/password', [OtpLoginController::class, 'loginWithPassword'])
        ->middleware('throttle:5,1')
        ->name('login.password');

    Route::post('/login/otp', [OtpLoginController::class, 'verifyOtp'])
        ->middleware('throttle:8,1')
        ->name('login.otp');

    Route::post('/login/otp/resend', [OtpLoginController::class, 'resendOtp'])
        ->middleware('throttle:3,1')
        ->name('login.otp.resend');

    Route::post('/login/reset', [OtpLoginController::class, 'reset'])->name('login.reset');
});

Route::post('/logout', [OtpLoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', 'role:admin'])->group(function () {
    $renderAdminDashboard = function (string $file) {
        ob_start();
        include base_path('../../' . $file);

        return response(ob_get_clean());
    };

    Route::get('/admin/dashboard', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('AdminDashboard.php');
    })->name('admin.dashboard');

    Route::get('/admin/AdminDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('AdminDashboard.php');
    })->name('admin.dashboard.file');

    Route::get('/admin/SatellitesDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('SatellitesDashboard.php');
    })->name('admin.satellites.file');

    Route::get('/admin/MembersDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('MembersDashboard.php');
    })->name('admin.members.file');

    Route::get('/admin/SeekersDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('SeekersDashboard.php');
    })->name('admin.seekers.file');

    Route::get('/admin/UsersDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('UsersDashboard.php');
    })->name('admin.users.file');

    Route::get('/admin/ReportsDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('ReportsDashboard.php');
    })->name('admin.reports.file');

    Route::get('/admin/AnalyticsDashboard.php', function () use ($renderAdminDashboard) {
        return $renderAdminDashboard('AnalyticsDashboard.php');
    })->name('admin.analytics.file');
});

Route::view('/district/dashboard', 'dashboard.district')
    ->middleware(['auth', 'role:district,district_user,satellite,satellite_user'])
    ->name('district.dashboard');

Route::view('/home', 'home')
    ->middleware(['auth', 'role:normal,normal_user,member,user'])
    ->name('home');
