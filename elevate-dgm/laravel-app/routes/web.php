<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSatelliteController;
use App\Http\Controllers\AdminMemberController;
use App\Http\Controllers\AdminRecommendationActionController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminSeekerController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EngagementClassificationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SatelliteSeekerController;
use App\Http\Controllers\SatelliteFollowUpController;
use Illuminate\Support\Facades\Route;

// Retain old bookmarks; the report itself is available only through the CLI.
Route::permanentRedirect('/admin/research/thesis-evidence', '/admin/analytics/sentiment');

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

Route::middleware('auth')->group(function () {
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/create', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::patch('/feedback/{feedback}/research-consent', [\App\Http\Controllers\SentimentResearchController::class, 'consent'])->name('feedback.research-consent');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/satellites', [AdminSatelliteController::class, 'index'])->name('admin.satellites');
    Route::get('/admin/satellites/export', [AdminSatelliteController::class, 'export'])->name('admin.satellites.export');
    Route::post('/admin/satellites', [AdminSatelliteController::class, 'store'])->name('admin.satellites.store');
    Route::get('/admin/members', [AdminMemberController::class, 'index'])->name('admin.members');
    Route::get('/admin/members/export', [AdminMemberController::class, 'export'])->name('admin.members.export');
    Route::get('/admin/seekers', [AdminSeekerController::class, 'index'])->name('admin.seekers');
    Route::get('/admin/seekers/export', [AdminSeekerController::class, 'export'])->name('admin.seekers.export');
    Route::post('/admin/seekers/sync-google-sheet', [AdminSeekerController::class, 'syncGoogleSheet'])->name('admin.seekers.sync-google-sheet');
    Route::patch('/admin/seekers/bulk', [AdminSeekerController::class, 'bulkUpdate'])->name('admin.seekers.bulk');
    Route::patch('/admin/seekers/{seeker}/assign-district', [AdminSeekerController::class, 'assignDistrict'])->name('admin.seekers.assign-district');
    Route::patch('/admin/seekers/{seeker}/status', [AdminSeekerController::class, 'updateStatus'])->name('admin.seekers.status');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/admin/users/export', [AdminUserController::class, 'export'])->name('admin.users.export');
    Route::patch('/admin/users/bulk', [AdminUserController::class, 'bulkUpdate'])->name('admin.users.bulk');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::get('/admin/reports', [AdminReportController::class, 'index'])->name('admin.reports');
    Route::get('/admin/reports/export', [AdminReportController::class, 'export'])->name('admin.reports.export');
    Route::get('/admin/recommendation-actions', [AdminRecommendationActionController::class, 'index'])->name('admin.recommendation-actions.index');
    Route::post('/admin/recommendation-actions', [AdminRecommendationActionController::class, 'store'])->name('admin.recommendation-actions.store');
    Route::patch('/admin/recommendation-actions/{recommendationAction}', [AdminRecommendationActionController::class, 'update'])->name('admin.recommendation-actions.update');
    Route::get('/admin/recommendation-actions/export', [AdminRecommendationActionController::class, 'export'])->name('admin.recommendation-actions.export');
    Route::get('/admin/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics');
    Route::prefix('/admin/analytics/engagement')->name('admin.engagement.')->controller(\App\Http\Controllers\EngagementResearchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/review', 'index')->name('review');
        Route::get('/dataset', 'index')->name('dataset');
        Route::get('/export', 'export')->name('export');
        Route::post('/train', 'train')->name('train');
        Route::post('/finalize', 'finalize')->name('finalize');
        Route::get('/records/{reference}', 'show')->name('show');
        Route::patch('/records/{reference}/verify', 'verify')->name('verify');
    });
    Route::get('/admin/analytics/sentiment', [AdminAnalyticsController::class, 'sentiment'])->name('admin.analytics.sentiment');
    Route::prefix('/admin/analytics/sentiment/research')->name('admin.sentiment-research.')->controller(\App\Http\Controllers\SentimentResearchController::class)->group(function () {
        Route::get('/review','index')->name('review');
        Route::get('/dataset','index')->name('dataset');
        Route::get('/export','export')->name('export');
        Route::post('/sync','synchronize')->name('sync');
        Route::post('/period','period')->name('period');
        Route::post('/evaluate','evaluate')->name('evaluate');
        Route::post('/apply-approved-review-set','applyApprovedReviewSet')->name('apply-approved-review-set');
        Route::get('/units/{unit}','show')->name('show');
        Route::patch('/units/{unit}','review')->name('save');
        Route::post('/units/{unit}/split','split')->name('split');
    });
    Route::post('/admin/analytics/sentiment/analyze', [AdminAnalyticsController::class, 'analyzeFeedbackSentiment'])->name('admin.analytics.sentiment.analyze');
    Route::patch('/admin/analytics/sentiment/{feedback}/reference-label', [AdminAnalyticsController::class, 'updateFeedbackReferenceLabel'])->name('admin.analytics.sentiment.reference-label');
    Route::get('/api/analytics-data', [AdminAnalyticsController::class, 'data'])->name('admin.analytics.data');

    Route::redirect('/admin/AdminDashboard.php', '/admin/dashboard')->name('admin.dashboard.file');
    Route::redirect('/admin/SatellitesDashboard.php', '/admin/satellites')->name('admin.satellites.file');
    Route::redirect('/admin/MembersDashboard.php', '/admin/members')->name('admin.members.file');
    Route::redirect('/admin/SeekersDashboard.php', '/admin/seekers')->name('admin.seekers.file');
    Route::redirect('/admin/UsersDashboard.php', '/admin/users')->name('admin.users.file');
    Route::redirect('/admin/ReportsDashboard.php', '/admin/reports')->name('admin.reports.file');
    Route::redirect('/admin/AnalyticsDashboard.php', '/admin/analytics')->name('admin.analytics.file');
});

Route::get('/satellites/{satellite}/dashboard', [AdminSatelliteController::class, 'dashboard'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.dashboard');

Route::get('/satellites/{satellite}/members', [AdminSatelliteController::class, 'members'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.members');
Route::post('/satellites/{satellite}/members', [AdminSatelliteController::class, 'storeMember'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.store');
Route::patch('/satellites/{satellite}/members/{member}', [AdminSatelliteController::class, 'updateMember'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.update');
Route::delete('/satellites/{satellite}/members/{member}', [AdminSatelliteController::class, 'destroyMember'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.destroy');
Route::post('/satellites/{satellite}/members/import-preview', [AdminSatelliteController::class, 'previewMemberImport'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.import-preview');
Route::post('/satellites/{satellite}/members/import', [AdminSatelliteController::class, 'importMembers'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.import');
Route::get('/satellites/{satellite}/members/import-template', [AdminSatelliteController::class, 'memberImportTemplate'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.members.import-template');

Route::get('/satellites/{satellite}/seekers', [SatelliteSeekerController::class, 'index'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.seekers');

Route::patch('/satellites/{satellite}/seekers/{seeker}/status', [SatelliteSeekerController::class, 'updateStatus'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.seekers.status');
Route::patch('/satellites/{satellite}/seekers/{seeker}/leader', [SatelliteSeekerController::class, 'assignLeader'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.seekers.leader');
Route::post('/satellites/{satellite}/seekers/{seeker}/convert', [SatelliteSeekerController::class, 'convertToMember'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.seekers.convert');

Route::patch('/satellites/{satellite}/seekers/bulk-status', [SatelliteSeekerController::class, 'bulkUpdateStatus'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.seekers.bulk-status');

Route::get('/satellites/{satellite}/follow-ups', [SatelliteFollowUpController::class, 'index'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.follow-ups.index');
Route::get('/satellites/{satellite}/follow-ups/create', [SatelliteFollowUpController::class, 'create'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.follow-ups.create');
Route::post('/satellites/{satellite}/follow-ups', [SatelliteFollowUpController::class, 'store'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.follow-ups.store');
Route::post('/satellites/{satellite}/follow-ups/analyze-sentiment', [SatelliteFollowUpController::class, 'analyzeSentiment'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.follow-ups.analyze-sentiment');
Route::patch('/satellites/{satellite}/follow-ups/{followUp}', [SatelliteFollowUpController::class, 'update'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.follow-ups.update');
Route::delete('/satellites/{satellite}/follow-ups/{followUp}', [SatelliteFollowUpController::class, 'destroy'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.follow-ups.destroy');

Route::get('/satellites/{satellite}/engagement', [EngagementClassificationController::class, 'index'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.engagement.index');
Route::post('/satellites/{satellite}/engagement/classify', [EngagementClassificationController::class, 'classify'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.engagement.classify');
Route::post('/satellites/{satellite}/engagement/{member}/recommendations', [RecommendationController::class, 'store'])
    ->middleware(['auth', 'satellite.access:manage'])
    ->name('satellites.engagement.recommendations.store');
Route::get('/satellites/{satellite}/engagement/{member}', [EngagementClassificationController::class, 'show'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.engagement.show');

Route::get('/satellites/{satellite}/recommendations', [RecommendationController::class, 'index'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.recommendations.index');
Route::get('/satellites/{satellite}/recommendations/{recommendation}', [RecommendationController::class, 'show'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.recommendations.show');
Route::patch('/satellites/{satellite}/recommendations/{recommendation}/review', [RecommendationController::class, 'review'])
    ->middleware(['auth', 'satellite.access'])
    ->name('satellites.recommendations.review');

Route::view('/district/dashboard', 'dashboard.district')
    ->middleware(['auth', 'role:district,district_user,satellite,satellite_user'])
    ->name('district.dashboard');

Route::view('/home', 'home')
    ->middleware(['auth', 'role:normal,normal_user,member,user'])
    ->name('home');
