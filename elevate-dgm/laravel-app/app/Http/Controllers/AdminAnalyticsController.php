<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Feedback;
use App\Models\OrgUnit;
use App\Services\MovementAnalyticsService;
use App\Services\SentimentResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function index(): View
    {
        $checkpoints = Checkpoint::orderBy('as_of_date')->get();
        $defaultCheckpoint = $checkpoints->last(fn ($cp) => $cp->as_of_date->toDateString() <= now('Asia/Manila')->toDateString())?->key ?? $checkpoints->first()?->key;

        return view('admin.analytics', ['activeSatelliteCounts' => \App\Models\Satellite::dashboardCounts(), 'checkpoints' => $checkpoints, 'groups' => OrgUnit::where('type', 'group')->orderBy('sort')->get(), 'defaultCheckpoint' => $defaultCheckpoint]);
    }

    public function data(Request $request, MovementAnalyticsService $analytics)
    {
        // Compatibility URL now uses the same source as every movement widget.
        return app(MovementAnalyticsController::class)($request, $analytics);
    }

    public function sentiment(Request $request): View|RedirectResponse|JsonResponse
    {
        return app(SentimentResearchController::class)->index($request);
    }

    public function analyzeFeedbackSentiment(Request $request): RedirectResponse
    {
        return app(SentimentResearchController::class)->evaluate($request);
    }

    public function updateFeedbackReferenceLabel(Request $request, Feedback $feedback): RedirectResponse
    {
        app(SentimentResearchService::class)->authorize($request->user());

        return redirect()->route('admin.sentiment-research.review')->withErrors(['review' => 'Use Human Feedback Review to record consent, language and an independent reference. Legacy labels are preserved.']);
    }
}
