<?php

namespace App\Services;

use App\Models\EngagementModelRun;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModelEvaluationDashboard
{
    public const CACHE_VERSION_KEY = 'model-evaluation:version';

    public function __construct(
        private SentimentResearchService $sentiment,
        private ClassificationMetrics $metrics,
        private SatisfactionCommentEvaluation $commentEvaluation
    ) {}

    public static function bumpVersion(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (int) Cache::get(self::CACHE_VERSION_KEY, 0) + 1);
    }

    public function data(Request $request): array
    {
        $dates = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'dataset' => ['nullable', 'string', 'in:comments,system_feedback']
        ]);
        $from = $dates['from'] ?? null;
        $to = $dates['to'] ?? null;
        $dataset = $dates['dataset'] ?? 'comments';

        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);

        // Changes to consent, human reviews, stored predictions, settings, or model runs
        // change the key immediately; the cache stores aggregates, never raw feedback.
        $signature = hash('sha256', json_encode([
            $version,
            $dataset,
            DB::table('feedback')->orderBy('id')->get()->toArray(),
            DB::table('feedback_analysis_units')->orderBy('id')->get()->toArray(),
            DB::table('satisfaction_comments')->orderBy('id')->get(['id', 'predicted_sentiment', 'final_label', 'sentiment_model_version'])->toArray(),
            DB::table('satisfaction_comment_reviews')->orderBy('id')->get(['comment_id', 'role', 'label'])->toArray(),
            DB::table('sentiment_research_settings')->orderBy('id')->get()->toArray(),
            DB::table('users')->orderBy('id')->get(['id', 'role', 'updated_at'])->toArray(),
            DB::table('engagement_model_runs')->orderBy('id')->get()->toArray(),
            SentimentAnalysisService::MODEL_VERSION,
        ], JSON_THROW_ON_ERROR));

        return Cache::store('file')->remember('model-evaluation:'.hash('sha256', $signature.'|'.$from.'|'.$to.'|'.$dataset), now()->addMinutes(10),
            fn () => $this->calculate($from, $to, $dataset));
    }

    private function calculate(?string $from, ?string $to, string $dataset = 'comments'): array
    {
        $commentEval = $this->commentEvaluation->data($from, $to);

        $sentimentMatrix = array_fill_keys(SentimentResearchMetrics::LABELS, array_fill_keys(SentimentResearchMetrics::LABELS, 0));
        $eligible = 0;
        $covered = 0;
        foreach ($this->sentiment->eligibility() as $item) {
            $unit = $item['unit'];
            $date = $unit->feedback?->created_at?->toDateString();
            if (($from && $date < $from) || ($to && $date > $to)) continue;
            if (!$item['included']) continue;
            $eligible++;
            if (!$this->sentiment->currentPrediction($unit)) continue;
            $sentimentMatrix[$unit->human_reference_sentiment][$unit->system_prediction]++;
            $covered++;
        }
        $legacySentiment = $this->metrics->calculate(SentimentResearchMetrics::LABELS, $sentimentMatrix);

        $run = EngagementModelRun::where('is_active', true)->latest('trained_at')->first();
        $engagementMatrix = array_fill_keys(ResearchCartService::LABELS, array_fill_keys(ResearchCartService::LABELS, 0));
        $engagementTotal = 0;
        if ($run && (!$from || $run->trained_at?->toDateString() >= $from) && (!$to || $run->trained_at?->toDateString() <= $to)) {
            $stored = $run->holdout_metrics ?? [];
            $engagementMatrix = $stored['confusion_matrix'] ?? $engagementMatrix;
            $engagementTotal = (int) ($stored['sample_count'] ?? 0);
        } else {
            $run = null;
        }

        // Selected sentiment evaluation component based on dataset option
        $activeSentiment = $dataset === 'system_feedback' ? $legacySentiment : $commentEval['sentiment'];
        $activeCoverage = $dataset === 'system_feedback'
            ? ['eligible' => $eligible, 'total' => Feedback::count(), 'predicted' => $covered]
            : ['eligible' => $commentEval['coverage']['labeled'], 'total' => $commentEval['coverage']['total'], 'predicted' => $commentEval['coverage']['labeled']];

        return [
            'from' => $from,
            'to' => $to,
            'dataset' => $dataset,
            'commentEvaluation' => $commentEval,
            'legacySentiment' => $legacySentiment,
            'legacyCoverage' => ['eligible' => $eligible, 'total' => Feedback::count(), 'predicted' => $covered],
            'sentiment' => $activeSentiment,
            'sentimentCoverage' => $activeCoverage,
            'sentimentVersion' => SentimentAnalysisService::MODEL_VERSION,
            'engagement' => $this->metrics->calculate(ResearchCartService::LABELS, $engagementMatrix),
            'engagementRun' => $run,
            'engagementTotal' => $engagementTotal,
        ];
    }
}
