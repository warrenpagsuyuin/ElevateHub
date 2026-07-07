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

Route::view('/admin/dashboard', 'dashboard.admin')
    ->middleware(['auth', 'role:admin'])
    ->name('admin.dashboard');

Route::view('/district/dashboard', 'dashboard.district')
    ->middleware(['auth', 'role:district,district_user,satellite,satellite_user'])
    ->name('district.dashboard');

Route::view('/home', 'home')
    ->middleware(['auth', 'role:normal,normal_user,member,user'])
    ->name('home');
