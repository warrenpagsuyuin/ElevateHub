<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\SentimentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct(private SentimentAnalysisService $sentimentService)
    {
    }

    public function index(): View
    {
        return view('admin.analytics');
    }

    public function data(): JsonResponse
    {
        return response()->json($this->analyticsData());
    }

    public function sentiment(Request $request): View
    {
        return app(SentimentResearchController::class)->index($request);
    }

    public function analyzeFeedbackSentiment(Request $request): RedirectResponse
    {
        return app(SentimentResearchController::class)->evaluate($request);
    }

    public function updateFeedbackReferenceLabel(Request $request, Feedback $feedback): RedirectResponse
    {
        app(\App\Services\SentimentResearchService::class)->authorize($request->user());
        return redirect()->route('admin.sentiment-research.review')->withErrors(['review'=>'Use Human Feedback Review to record consent, language and an independent reference. Legacy labels are preserved.']);
    }
    /**
     * @return array<string, array<string, array{labels: array<int, string>, 2024: array<int, int>, 2025: array<int, int>}>>
     */
    private function analyticsData(): array
    {
        return [
            'MAIN' => [
                'DLEADERS' => [
                    'labels' => ['The Hub', 'Pasig', 'Main Ortigas', 'U-Belt', 'Eastwood'],
                    '2024' => [77, 37, 52, 46, 31],
                    '2025' => [67, 22, 58, 61, 43],
                ],
                'DGROUPS' => [
                    'labels' => ['The Hub', 'Pasig', 'Main Ortigas', 'U-Belt', 'Eastwood'],
                    '2024' => [82, 39, 56, 48, 33],
                    '2025' => [75, 27, 60, 64, 45],
                ],
                'DMEMBERS' => [
                    'labels' => ['The Hub', 'Pasig', 'Main Ortigas', 'U-Belt', 'Eastwood'],
                    '2024' => [415, 288, 332, 301, 194],
                    '2025' => [502, 350, 410, 385, 260],
                ],
            ],
            'NCP' => [
                'DLEADERS' => [
                    'labels' => ['Quezon City', 'Caloocan', 'Malabon', 'Valenzuela', 'Marikina', 'Rizal'],
                    '2024' => [316, 284, 198, 172, 226, 301],
                    '2025' => [402, 351, 244, 218, 287, 376],
                ],
                'DGROUPS' => [
                    'labels' => ['Quezon City', 'Caloocan', 'Malabon', 'Valenzuela', 'Marikina', 'Rizal'],
                    '2024' => [328, 296, 207, 181, 239, 318],
                    '2025' => [419, 367, 259, 226, 301, 389],
                ],
                'DMEMBERS' => [
                    'labels' => ['Quezon City', 'Caloocan', 'Malabon', 'Valenzuela', 'Marikina', 'Rizal'],
                    '2024' => [1480, 1215, 918, 774, 1038, 1396],
                    '2025' => [1912, 1544, 1176, 936, 1299, 1743],
                ],
            ],
            'ICP' => [
                'DLEADERS' => [
                    'labels' => ['Cavite', 'Laguna', 'Bulacan', 'Pampanga'],
                    '2024' => [72, 68, 61, 54],
                    '2025' => [89, 82, 75, 67],
                ],
                'DGROUPS' => [
                    'labels' => ['Cavite', 'Laguna', 'Bulacan', 'Pampanga'],
                    '2024' => [74, 70, 64, 57],
                    '2025' => [92, 85, 78, 69],
                ],
                'DMEMBERS' => [
                    'labels' => ['Cavite', 'Laguna', 'Bulacan', 'Pampanga'],
                    '2024' => [318, 294, 266, 221],
                    '2025' => [406, 371, 332, 281],
                ],
            ],
        ];
    }

    /**
     * @return array{sentiment: string, human_sentiment_label: string}
     */
    private function sentimentFilters(Request $request): array
    {
        $sentiment = (string) $request->query('sentiment', '');
        $humanSentiment = (string) $request->query('human_sentiment_label', '');

        return [
            'sentiment' => in_array($sentiment, Feedback::SENTIMENTS, true) ? $sentiment : '',
            'human_sentiment_label' => in_array($humanSentiment, Feedback::SENTIMENTS, true) ? $humanSentiment : '',
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }
}
