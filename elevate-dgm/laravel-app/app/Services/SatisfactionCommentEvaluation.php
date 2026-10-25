<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sentiment metrics for questionnaire comments, derived only from stored rows:
 * consented comments whose final human label is Positive/Neutral/Negative and whose
 * current-version prediction exists. "Cannot judge" is excluded from metrics.
 */
class SatisfactionCommentEvaluation
{
    public const CACHE_MINUTES = 10;

    public function __construct(private SatisfactionCommentService $comments, private ClassificationMetrics $metrics) {}

    public function data(?string $from, ?string $to): array
    {
        $key = 'satisfaction-comment-evaluation:'.$this->comments->version().':'.SentimentAnalysisService::MODEL_VERSION.':'.$from.':'.$to;
        return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn () => $this->calculate($from, $to) + ['calculated_at' => now()->toIso8601String()]);
    }

    private function scoped(?string $from, ?string $to)
    {
        return DB::table('satisfaction_comments as c')->join('satisfaction_responses as r', 'r.id', '=', 'c.response_id')
            ->when($from, fn ($q) => $q->where('r.service_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('r.service_date', '<=', $to));
    }

    /** Evaluation set query: consented, final human label in the three classes, current prediction. */
    public function evaluationRows(?string $from, ?string $to)
    {
        return $this->scoped($from, $to)->where('c.research_consent', true)
            ->whereIn('c.final_label', SatisfactionCommentService::LABELS)
            ->whereIn('c.predicted_sentiment', SatisfactionCommentService::LABELS)
            ->where('c.sentiment_model_version', SentimentAnalysisService::MODEL_VERSION);
    }

    private function calculate(?string $from, ?string $to): array
    {
        $labels = SatisfactionCommentService::LABELS;
        $matrix = array_fill_keys($labels, array_fill_keys($labels, 0));
        $cells = $this->evaluationRows($from, $to)->selectRaw('c.final_label as actual, c.predicted_sentiment as predicted, COUNT(*) as total')
            ->groupBy('c.final_label', 'c.predicted_sentiment')->get();
        foreach ($cells as $cell) $matrix[$cell->actual][$cell->predicted] = (int) $cell->total;

        $coverage = $this->scoped($from, $to)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN c.research_consent = 1 THEN 1 ELSE 0 END) as consented,
            SUM(CASE WHEN c.research_consent = 1 AND c.final_label IN ('Positive','Neutral','Negative') THEN 1 ELSE 0 END) as labeled,
            SUM(CASE WHEN c.research_consent = 1 AND c.final_label = ? THEN 1 ELSE 0 END) as cannot_judge,
            SUM(CASE WHEN c.research_consent = 1 AND c.final_label IS NULL THEN 1 ELSE 0 END) as pending_review,
            SUM(CASE WHEN c.predicted_sentiment IS NULL OR c.sentiment_model_version IS NULL OR c.sentiment_model_version != ? THEN 1 ELSE 0 END) as awaiting_prediction
        ", [SatisfactionCommentService::CANNOT_JUDGE, SentimentAnalysisService::MODEL_VERSION])->first();

        return [
            'from' => $from, 'to' => $to,
            'sentiment' => $this->metrics->calculate($labels, $matrix),
            'coverage' => array_map('intval', (array) $coverage),
            'agreement' => $this->comments->agreement(),
            'version' => SentimentAnalysisService::MODEL_VERSION,
        ];
    }

    /** CSV lines: one row per evaluated comment, then the confusion matrix. No comment text, satellite or date. */
    public function csvLines(?string $from, ?string $to): \Generator
    {
        yield ['comment_ref', 'predicted_sentiment', 'reference_label', 'reference_source', 'model_version', 'correct'];
        foreach ($this->evaluationRows($from, $to)->orderBy('c.id')->select(['c.id', 'c.predicted_sentiment', 'c.final_label', 'c.final_label_source', 'c.sentiment_model_version'])->cursor() as $row) {
            yield [self::ref($row->id), $row->predicted_sentiment, $row->final_label, $row->final_label_source, $row->sentiment_model_version, $row->predicted_sentiment === $row->final_label ? 'yes' : 'no'];
        }
        $data = $this->calculate($from, $to)['sentiment'];
        yield [];
        yield ['confusion_matrix', 'rows = reference label (actual), columns = predicted'];
        yield ['actual \\ predicted', ...$data['labels'], 'total'];
        foreach ($data['labels'] as $actual) yield [$actual, ...array_values($data['confusion_matrix'][$actual]), array_sum($data['confusion_matrix'][$actual])];
        yield [];
        yield ['n', $data['n']];
        yield ['accuracy', $data['accuracy'] === null ? 'N/A' : round($data['accuracy'], 4)];
        yield ['majority_baseline', $data['majority_baseline'] === null ? 'N/A' : round($data['majority_baseline'], 4)];
        yield ['macro_f1', $data['macro'] === null ? 'N/A' : round($data['macro']['f1'], 4)];
        yield ['weighted_f1', $data['weighted'] === null ? 'N/A' : round($data['weighted']['f1'], 4)];
    }

    /** Short, non-reversible reference that is never shown on the blind review page. */
    public static function ref(string $id): string
    {
        return 'C-'.strtoupper(substr(hash('sha256', 'comment-ref|'.$id), 0, 8));
    }
}
