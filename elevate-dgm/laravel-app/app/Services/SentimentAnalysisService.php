<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SentimentAnalysisService
{
    public const LABEL_POSITIVE = 'Positive';
    public const LABEL_NEUTRAL = 'Neutral';
    public const LABEL_NEGATIVE = 'Negative';

    public const MODEL_NAME = 'Explainable Multilingual Sentiment Lexicon';
    public const MODEL_VERSION = '2026.08.29-feedback-v1';

    private const POSITIVE_CUES = [
        'encouraged' => 1.4,
        'encourage' => 1.2,
        'interested' => 1.3,
        'responsive' => 1.2,
        'excited' => 1.6,
        'thankful' => 1.1,
        'blessed' => 1.2,
        'willing' => 1.0,
        'open' => 0.8,
        'attended' => 0.9,
        'joined' => 0.8,
        'confirmed' => 0.8,
        'positive' => 1.0,
        'great' => 1.2,
        'good' => 0.8,
        'helpful' => 1.3,
        'useful' => 1.1,
        'clear' => 1.0,
        'easy' => 0.9,
        'accurate' => 1.0,
        'organized' => 0.8,
        'reliable' => 1.0,
        'fast' => 0.8,
        'smooth' => 0.9,
        'recommend' => 1.0,
        'satisfied' => 1.2,
        'okay' => 0.5,
        'ok' => 0.5,
        'salamat' => 1.0,
        'masaya' => 1.1,
        'maganda' => 1.0,
        'malinaw' => 1.0,
        'madali' => 0.9,
        'nakatulong' => 1.2,
        'maayos' => 1.0,
        'tama' => 0.8,
        'interesado' => 1.2,
        'sige' => 0.8,
        'payag' => 0.9,
        'game' => 0.7,
    ];

    private const NEGATIVE_CUES = [
        'not interested' => 2.0,
        'no reply' => 1.5,
        'no response' => 1.5,
        'not helpful' => 1.5,
        'not useful' => 1.4,
        'not clear' => 1.4,
        'unresponsive' => 1.6,
        'declined' => 1.4,
        'cancelled' => 1.1,
        'concern' => 0.8,
        'problem' => 1.0,
        'confusing' => 1.4,
        'difficult' => 1.2,
        'hard' => 0.9,
        'slow' => 1.0,
        'inaccurate' => 1.2,
        'unclear' => 1.2,
        'missing' => 0.9,
        'error' => 1.0,
        'bug' => 1.0,
        'frustrating' => 1.3,
        'frustrated' => 1.3,
        'needs improvement' => 1.1,
        'busy' => 0.8,
        'hesitant' => 1.0,
        'unsure' => 0.8,
        'sad' => 1.0,
        'upset' => 1.2,
        'angry' => 1.4,
        'negative' => 1.2,
        'struggle' => 1.0,
        'struggling' => 1.1,
        'hindi interested' => 2.0,
        'hindi malinaw' => 1.4,
        'di malinaw' => 1.4,
        'hindi madali' => 1.1,
        'di madali' => 1.1,
        'di interested' => 2.0,
        'ayaw' => 1.4,
        'walang reply' => 1.5,
        'hindi sumagot' => 1.4,
        'di sumagot' => 1.4,
        'busy pa' => 0.8,
        'nahihiya' => 0.8,
        'problema' => 1.0,
        'alanganin' => 0.9,
    ];

    private const TAGLISH_CUES = [
        'salamat',
        'masaya',
        'interesado',
        'sige',
        'payag',
        'hindi interested',
        'di interested',
        'ayaw',
        'walang reply',
        'hindi sumagot',
        'di sumagot',
        'busy pa',
        'nahihiya',
        'problema',
        'alanganin',
        'maganda',
        'malinaw',
        'madali',
        'nakatulong',
        'maayos',
        'tama',
        'hindi malinaw',
        'di malinaw',
        'hindi madali',
        'di madali',
    ];

    /**
     * @return array{
     *     label: string,
     *     score: float,
     *     confidence: float,
     *     model_name: string,
     *     model_version: string,
     *     metrics: array<string, mixed>
     * }
     */
    public function analyze(?string $text): array
    {
        $original = trim((string) $text);
        $normalized = $this->normalize($original);

        if ($normalized === '') {
            return $this->result(
                self::LABEL_NEUTRAL,
                0.0,
                0.50,
                0.0,
                0.0,
                [],
                [],
                ['No text was available, so sentiment defaults to Neutral.'],
                'None'
            );
        }

        [$positiveScore, $positiveMatches] = $this->scoreCues($normalized, self::POSITIVE_CUES, true);
        [$negativeScore, $negativeMatches] = $this->scoreCues($normalized, self::NEGATIVE_CUES, false);

        $netScore = round($positiveScore - $negativeScore, 2);
        $totalEvidence = $positiveScore + $negativeScore;
        $label = self::LABEL_NEUTRAL;

        if ($totalEvidence > 0 && abs($netScore) >= 0.75) {
            $label = $netScore > 0 ? self::LABEL_POSITIVE : self::LABEL_NEGATIVE;
        }

        $confidence = $this->confidence($label, $netScore, $totalEvidence);
        $decisionPath = $this->decisionPath($label, $positiveScore, $negativeScore, $positiveMatches, $negativeMatches);
        $languageSignal = $this->languageSignal($normalized);

        return $this->result(
            $label,
            $netScore,
            $confidence,
            round($positiveScore, 2),
            round($negativeScore, 2),
            $positiveMatches,
            $negativeMatches,
            $decisionPath,
            $languageSignal
        );
    }

    public function analyzeFollowUp(FollowUp $followUp): FollowUp
    {
        if (trim((string) $followUp->notes) === '') {
            $followUp->forceFill([
                'sentiment_label' => null,
                'sentiment_score' => null,
                'sentiment_confidence' => null,
                'sentiment_model' => null,
                'sentiment_model_version' => null,
                'sentiment_metrics' => null,
                'sentiment_analyzed_at' => null,
            ])->save();

            return $followUp->refresh();
        }

        $analysis = $this->analyze($followUp->notes);

        $followUp->forceFill([
            'sentiment_label' => $analysis['label'],
            'sentiment_score' => $analysis['score'],
            'sentiment_confidence' => $analysis['confidence'],
            'sentiment_model' => $analysis['model_name'],
            'sentiment_model_version' => $analysis['model_version'],
            'sentiment_metrics' => $analysis['metrics'],
            'sentiment_analyzed_at' => now(),
        ])->save();

        return $followUp->refresh();
    }

    public function analyzeFeedback(Feedback $feedback): Feedback
    {
        $analysis = $this->analyze($this->feedbackText($feedback));

        $feedback->forceFill([
            'sentiment' => $analysis['label'],
            'sentiment_score' => $analysis['score'],
            'sentiment_confidence' => $analysis['confidence'],
            'sentiment_model' => $analysis['model_name'],
            'sentiment_model_version' => $analysis['model_version'],
            'sentiment_metrics' => $analysis['metrics'],
            'sentiment_analyzed_at' => now(),
        ])->save();

        return $feedback->refresh();
    }

    /**
     * @return array{count: int, labels: array<string, int>}
     */
    public function analyzeQuery(Builder $query): array
    {
        $labels = [
            self::LABEL_POSITIVE => 0,
            self::LABEL_NEUTRAL => 0,
            self::LABEL_NEGATIVE => 0,
        ];
        $count = 0;

        $query->whereNotNull('notes')->whereRaw("TRIM(notes) <> ''")
            ->orderBy('id')->chunkById(100, function (Collection $followUps) use (&$labels, &$count): void {
            foreach ($followUps as $followUp) {
                $updated = $this->analyzeFollowUp($followUp);
                $labels[$updated->sentiment_label] = ($labels[$updated->sentiment_label] ?? 0) + 1;
                $count++;
            }
        });

        return ['count' => $count, 'labels' => $labels];
    }

    /**
     * @return array{count: int, labels: array<string, int>}
     */
    public function analyzeFeedbackQuery(Builder $query): array
    {
        $labels = [
            self::LABEL_POSITIVE => 0,
            self::LABEL_NEUTRAL => 0,
            self::LABEL_NEGATIVE => 0,
        ];
        $count = 0;

        $query->orderBy('id')->chunkById(100, function (Collection $feedbackItems) use (&$labels, &$count): void {
            foreach ($feedbackItems as $feedback) {
                $updated = $this->analyzeFeedback($feedback);
                $labels[$updated->sentiment] = ($labels[$updated->sentiment] ?? 0) + 1;
                $count++;
            }
        });

        return ['count' => $count, 'labels' => $labels];
    }

    /**
     * @return array{
     *     total: int,
     *     analyzed: int,
     *     unanalyzed: int,
     *     counts: array<string, int>
     * }
     */
    public function summarize(Collection $followUps): array
    {
        $counts = [
            self::LABEL_POSITIVE => 0,
            self::LABEL_NEUTRAL => 0,
            self::LABEL_NEGATIVE => 0,
        ];

        $analyzed = 0;

        foreach ($followUps as $followUp) {
            $label = $followUp->sentiment_label;

            if ($label === null) {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
            $analyzed++;
        }

        return [
            'total' => $followUps->count(),
            'analyzed' => $analyzed,
            'unanalyzed' => max(0, $followUps->count() - $analyzed),
            'counts' => $counts,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     analyzed: int,
     *     unanalyzed: int,
     *     labeled: int,
     *     counts: array<string, int>,
     *     trend: array<int, array<string, mixed>>
     * }
     */
    public function summarizeFeedback(Collection $feedbackItems): array
    {
        $counts = [
            self::LABEL_POSITIVE => 0,
            self::LABEL_NEUTRAL => 0,
            self::LABEL_NEGATIVE => 0,
        ];
        $trend = [];
        $analyzed = 0;
        $labeled = 0;

        foreach ($feedbackItems as $feedback) {
            $label = $feedback->sentiment;

            if ($feedback->human_sentiment_label !== null) {
                $labeled++;
            }

            if ($label === null) {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
            $analyzed++;

            $date = $feedback->created_at;
            $periodKey = $date ? $date->format('Y-m') : 'undated';
            $periodLabel = $date ? $date->format('M Y') : 'Undated';

            $trend[$periodKey] ??= [
                'period' => $periodLabel,
                self::LABEL_POSITIVE => 0,
                self::LABEL_NEUTRAL => 0,
                self::LABEL_NEGATIVE => 0,
                'total' => 0,
            ];
            $trend[$periodKey][$label]++;
            $trend[$periodKey]['total']++;
        }

        ksort($trend);

        return [
            'total' => $feedbackItems->count(),
            'analyzed' => $analyzed,
            'unanalyzed' => max(0, $feedbackItems->count() - $analyzed),
            'labeled' => $labeled,
            'counts' => $counts,
            'trend' => array_slice(array_values($trend), -6),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     correct: int,
     *     accuracy: float|null,
     *     macro_precision: float|null,
     *     macro_recall: float|null,
     *     macro_f1: float|null,
     *     per_label: array<string, array<string, float|int|null>>,
     *     confusion_matrix: array<string, array<string, int>>
     * }
     */
    public function evaluationMetrics(Collection $feedbackItems): array
    {
        $labels = [self::LABEL_POSITIVE, self::LABEL_NEUTRAL, self::LABEL_NEGATIVE];
        $matrix = [];
        $perLabel = [];

        foreach ($labels as $actual) {
            $matrix[$actual] = array_fill_keys($labels, 0);
        }

        $total = 0;
        $correct = 0;

        foreach ($feedbackItems as $feedback) {
            $actual = $feedback->human_sentiment_label;
            $predicted = $feedback->sentiment;

            if (! in_array($actual, $labels, true) || ! in_array($predicted, $labels, true)) {
                continue;
            }

            $matrix[$actual][$predicted]++;
            $total++;

            if ($actual === $predicted) {
                $correct++;
            }
        }

        foreach ($labels as $label) {
            $truePositive = $matrix[$label][$label];
            $falsePositive = collect($labels)
                ->reject(fn (string $actual): bool => $actual === $label)
                ->sum(fn (string $actual): int => $matrix[$actual][$label]);
            $falseNegative = collect($labels)
                ->reject(fn (string $predicted): bool => $predicted === $label)
                ->sum(fn (string $predicted): int => $matrix[$label][$predicted]);

            $precision = ($truePositive + $falsePositive) > 0
                ? round($truePositive / ($truePositive + $falsePositive), 4)
                : null;
            $recall = ($truePositive + $falseNegative) > 0
                ? round($truePositive / ($truePositive + $falseNegative), 4)
                : null;
            $f1 = ($precision !== null && $recall !== null && ($precision + $recall) > 0)
                ? round((2 * $precision * $recall) / ($precision + $recall), 4)
                : null;

            $perLabel[$label] = [
                'true_positive' => $truePositive,
                'false_positive' => $falsePositive,
                'false_negative' => $falseNegative,
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
            ];
        }

        return [
            'total' => $total,
            'correct' => $correct,
            'accuracy' => $total > 0 ? round($correct / $total, 4) : null,
            'macro_precision' => $this->averageMetric($perLabel, 'precision'),
            'macro_recall' => $this->averageMetric($perLabel, 'recall'),
            'macro_f1' => $this->averageMetric($perLabel, 'f1'),
            'per_label' => $perLabel,
            'confusion_matrix' => $matrix,
        ];
    }

    public function feedbackText(Feedback $feedback): string
    {
        $payload = $feedback->feedback_payload ?? [];
        $payloadAnswers = collect($payload['answers'] ?? [])
            ->filter(fn ($answer): bool => filled($answer))
            ->implode("\n");

        if ($payloadAnswers !== '') {
            return trim($payloadAnswers."\n".(string) ($payload['additional_comments'] ?? ''));
        }

        $answers = collect(range(1, 5))
            ->map(fn (int $number): ?string => $feedback->{'question_'.$number})
            ->filter(fn (?string $answer): bool => filled($answer))
            ->implode("\n");

        if ($answers !== '') {
            return trim($answers."\n".(string) $feedback->message);
        }

        return (string) $feedback->message;
    }

    /**
     * @param array<string, float> $cues
     * @return array{0: float, 1: array<int, array{cue: string, weight: float}>}
     */
    private function scoreCues(string $text, array $cues, bool $skipNegatedPositive): array
    {
        $score = 0.0;
        $matches = [];

        foreach ($cues as $cue => $weight) {
            if (!$this->containsCue($text, $cue)) {
                continue;
            }

            if ($skipNegatedPositive && !$this->hasSpaces($cue) && $this->isNegatedCue($text, $cue)) {
                continue;
            }

            $matches[] = ['cue' => $cue, 'weight' => $weight];
            $score += $weight;
        }

        return [$score, $matches];
    }

    private function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^a-z0-9\s-]/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function containsCue(string $text, string $cue): bool
    {
        $pattern = '/(?<![a-z0-9])'.preg_quote($cue, '/').'(?![a-z0-9])/u';

        return preg_match($pattern, $text) === 1;
    }

    private function isNegatedCue(string $text, string $cue): bool
    {
        $pattern = '/(?<![a-z0-9])(?:not|no|never|hindi|di|wala|ayaw)\s+(?:\w+\s+){0,2}'.preg_quote($cue, '/').'(?![a-z0-9])/u';

        return preg_match($pattern, $text) === 1;
    }

    private function hasSpaces(string $value): bool
    {
        return str_contains($value, ' ');
    }

    private function confidence(string $label, float $netScore, float $totalEvidence): float
    {
        if ($totalEvidence === 0.0) {
            return $label === self::LABEL_NEUTRAL ? 0.50 : 0.55;
        }

        if ($label === self::LABEL_NEUTRAL) {
            return round(min(0.72, 0.55 + min($totalEvidence, 3.0) * 0.04), 2);
        }

        $dominance = abs($netScore) / max($totalEvidence, 1.0);

        return round(min(0.96, 0.58 + ($dominance * 0.28) + min($totalEvidence, 4.0) * 0.035), 2);
    }

    /**
     * @param array<int, array{cue: string, weight: float}> $positiveMatches
     * @param array<int, array{cue: string, weight: float}> $negativeMatches
     * @return array<int, string>
     */
    private function decisionPath(
        string $label,
        float $positiveScore,
        float $negativeScore,
        array $positiveMatches,
        array $negativeMatches
    ): array {
        $path = [];

        $path[] = $positiveMatches === []
            ? 'No positive sentiment cues were detected.'
            : 'Positive cues detected: '.$this->cueList($positiveMatches).'.';

        $path[] = $negativeMatches === []
            ? 'No negative sentiment cues were detected.'
            : 'Negative cues detected: '.$this->cueList($negativeMatches).'.';

        if ($label === self::LABEL_POSITIVE) {
            $path[] = 'Positive score '.round($positiveScore, 2).' outweighed negative score '.round($negativeScore, 2).'.';
        } elseif ($label === self::LABEL_NEGATIVE) {
            $path[] = 'Negative score '.round($negativeScore, 2).' outweighed positive score '.round($positiveScore, 2).'.';
        } else {
            $path[] = 'Scores were balanced or no strong cues were found, so the result is Neutral.';
        }

        return $path;
    }

    /**
     * @param array<int, array{cue: string, weight: float}> $matches
     */
    private function cueList(array $matches): string
    {
        return collect($matches)
            ->map(fn (array $match): string => $match['cue'].' (+'.number_format($match['weight'], 1).')')
            ->implode(', ');
    }

    private function languageSignal(string $text): string
    {
        foreach (self::TAGLISH_CUES as $cue) {
            if ($this->containsCue($text, $cue)) {
                return 'Filipino/Taglish';
            }
        }

        return 'English/Unknown';
    }

    /**
     * @param array<int, array{cue: string, weight: float}> $positiveMatches
     * @param array<int, array{cue: string, weight: float}> $negativeMatches
     * @param array<int, string> $decisionPath
     * @return array{
     *     label: string,
     *     score: float,
     *     confidence: float,
     *     model_name: string,
     *     model_version: string,
     *     metrics: array<string, mixed>
     * }
     */
    private function result(
        string $label,
        float $score,
        float $confidence,
        float $positiveScore,
        float $negativeScore,
        array $positiveMatches,
        array $negativeMatches,
        array $decisionPath,
        string $languageSignal
    ): array {
        return [
            'label' => $label,
            'score' => round($score, 2),
            'confidence' => round($confidence, 2),
            'model_name' => self::MODEL_NAME,
            'model_version' => self::MODEL_VERSION,
            'metrics' => [
                'positive_score' => $positiveScore,
                'negative_score' => $negativeScore,
                'matched_positive_cues' => $positiveMatches,
                'matched_negative_cues' => $negativeMatches,
                'decision_path' => $decisionPath,
                'language_signal' => $languageSignal,
                'notice' => 'Sentiment is used only for review analytics and validation metrics; it is not an engagement classification predictor.',
            ],
        ];
    }

    /**
     * @param array<string, array<string, float|int|null>> $perLabel
     */
    private function averageMetric(array $perLabel, string $metric): ?float
    {
        $values = collect($perLabel)
            ->pluck($metric)
            ->filter(fn ($value): bool => $value !== null)
            ->values();

        return $values->isNotEmpty() ? round($values->avg(), 4) : null;
    }
}
