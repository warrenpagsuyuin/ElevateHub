<?php

namespace App\Services;

use App\Models\EngagementReferenceLabel;
use App\Models\EngagementResult;
use App\Models\EngagementModelRun;
use App\Models\Member;
use App\Models\Seeker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EngagementClassificationService
{
    public const CLASSIFICATION_ACTIVE = 'Active';
    public const CLASSIFICATION_MODERATELY_ACTIVE = 'Moderately Active';
    public const CLASSIFICATION_NEEDS_FOLLOW_UP = 'Possible Need for Follow-Up';
    public const CLASSIFICATION_INSUFFICIENT = 'Insufficient information for a reliable prediction.';

    public const CLASSIFICATION_HIGH = self::CLASSIFICATION_ACTIVE;
    public const CLASSIFICATION_MEDIUM = self::CLASSIFICATION_MODERATELY_ACTIVE;
    public const CLASSIFICATION_LOW = self::CLASSIFICATION_NEEDS_FOLLOW_UP;
    public const CLASSIFICATION_INACTIVE = self::CLASSIFICATION_INSUFFICIENT;

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_ACTIVE,
        self::CLASSIFICATION_MODERATELY_ACTIVE,
        self::CLASSIFICATION_NEEDS_FOLLOW_UP,
        self::CLASSIFICATION_INSUFFICIENT,
    ];

    public const MODEL_CART = 'CART Engagement Classifier';
    public const MODEL_BASELINE = 'Engagement Review Decision Tree Baseline';
    public const CART_MODEL_VERSION = '2026.09.01-cart-v1';
    public const BASELINE_MODEL_VERSION = '2026.09.01-baseline-v1';

    private const MAX_DEPTH = 3;
    private const MIN_SAMPLES_SPLIT = 4;
    private const MIN_SAMPLES_LEAF = 2;
    private const MIN_AVAILABLE_INDICATORS = 2;
    private const MIN_REFERENCE_LABELS_PER_CART_CLASS = 4;

    private const FEATURE_LABELS = [
        'follow_up_count_3m' => 'Recent Follow-Ups',
        'months_since_refresh' => 'Months Since Refresh',
        'active_disciples' => 'Active Disciples',
        'weekly_dgroups_led' => 'Weekly D-Groups Led',
        'is_dgroup_leader' => 'D-Group Leader Role',
        'years_since_joined' => 'Years Since Joined',
        'has_ministry_involvement' => 'Ministry Involvement Recorded',
    ];

    public function classifyMember(Member $member): array
    {
        $features = $this->extractMemberFeatures($member);
        $researchDataset = app(ReferenceDatasetService::class)->current();
        if ($researchDataset) {
            $researchPrediction = app(EngagementEvaluationService::class)->forMember($member, $researchDataset);
            return $researchPrediction ?? $this->baselinePrediction($member, $features, collect());
        }
        $samples = $this->referenceSamples((int) $member->satellite_id);

        $cartRun = $this->cartTrainingRun($samples, (int) $member->satellite_id);

        if ($cartRun !== null) {
            $prediction = $this->predictFromCart($cartRun['tree'], $features);

            return $this->resultPayload(
                member: $member,
                classification: $prediction['classification'],
                confidence: $prediction['confidence'],
                decisionPath: $prediction['decision_path'],
                features: $features,
                modelName: self::MODEL_CART,
                modelVersion: self::CART_MODEL_VERSION,
                modelFamily: 'cart',
                dataSufficiency: 'trained_cart',
                metadata: [
                    'model_run_id' => $cartRun['model_run']?->id,
                    'training_samples' => $cartRun['split_summary']['train']['sample_count'],
                    'training_config' => $this->trainingConfig(),
                    'split_summary' => $cartRun['split_summary'],
                    'label_distribution' => $cartRun['label_distribution'],
                    'validation_metrics' => $cartRun['validation_metrics'],
                    'holdout_metrics' => $cartRun['holdout_metrics'],
                    'tree' => $cartRun['tree'],
                ],
                modelRunId: $cartRun['model_run']?->id,
            );
        }

        return $this->baselinePrediction($member, $features, $samples);
    }

    public function classifySeeker(Seeker $seeker): array
    {
        $features = $this->extractSeekerFeatures($seeker);
        $isModeratelyActive = $features['follow_up_count_3m'] >= 2;

        return $this->resultPayload(
            member: null,
            classification: $isModeratelyActive
                ? self::CLASSIFICATION_MODERATELY_ACTIVE
                : self::CLASSIFICATION_NEEDS_FOLLOW_UP,
            confidence: $isModeratelyActive ? 0.72 : 0.62,
            decisionPath: [
                $this->pathStep(
                    feature: 'follow_up_count_3m',
                    value: $features['follow_up_count_3m'],
                    operator: '>=',
                    threshold: 2,
                    branch: $isModeratelyActive ? 'yes' : 'no',
                    outcome: $isModeratelyActive
                        ? self::CLASSIFICATION_MODERATELY_ACTIVE
                        : self::CLASSIFICATION_NEEDS_FOLLOW_UP,
                ),
            ],
            features: $features,
            modelName: self::MODEL_BASELINE,
            modelVersion: self::BASELINE_MODEL_VERSION,
            modelFamily: 'review_baseline',
            dataSufficiency: 'seeker_follow_up_review',
            metadata: ['model_type' => 'seeker', 'model_id' => $seeker->id],
        );
    }

    public function storeClassification(array $classificationResult, ?int $generatedById = null): EngagementResult
    {
        $memberId = $classificationResult['model_type'] === 'member'
            ? $classificationResult['model_id']
            : null;

        $values = [
            'classification' => $classificationResult['classification'],
            'confidence' => $classificationResult['confidence'],
            'metrics' => [
                'decision_path' => $classificationResult['decision_path'],
                'used_features' => $classificationResult['used_features'],
                'feature_values' => $classificationResult['feature_values'],
                'model_type' => $classificationResult['model_type'],
                'model_id' => $classificationResult['model_id'],
                'model_family' => $classificationResult['model_family'],
                'model_name' => $classificationResult['model_name'],
                'model_version' => $classificationResult['model_version'],
                'data_sufficiency' => $classificationResult['data_sufficiency'],
                'classified_at' => now()->toIso8601String(),
                'excluded_predictors' => [
                    'name',
                    'first_name',
                    'last_name',
                    'full_name',
                    'email',
                    'email_address',
                    'phone',
                    'contact_number',
                    'facebook_link',
                    'gender',
                    'birthdate',
                    'system_feedback',
                    'sentiment',
                    'member_status',
                ],
                'human_review_required' => true,
                'engagement_model_run_id' => $classificationResult['engagement_model_run_id'] ?? null,
            ],
            'model_name' => $classificationResult['model_name'],
            'model_version' => $classificationResult['model_version'],
            'decision_path' => $classificationResult['decision_path'],
            'used_features' => $classificationResult['used_features'],
            'explanation' => $classificationResult['explanation'],
            'data_sufficiency' => $classificationResult['data_sufficiency'],
            'generated_by_id' => $generatedById,
            'generated_at' => $classificationResult['generated_at'],
            'human_review_required' => true,
            'model_metadata' => $classificationResult['model_metadata'],
        ];

        if (Schema::hasColumn('engagement_results', 'engagement_model_run_id')) {
            $values['engagement_model_run_id'] = $classificationResult['engagement_model_run_id'] ?? null;
        }

        return EngagementResult::updateOrCreate(
            ['member_id' => $memberId],
            $values
        );
    }

    private function extractMemberFeatures(Member $member): array
    {
        $followUpCount = $member->followUps()
            ->whereDate('created_at', '>=', now()->subMonths(3))
            ->count();

        $monthsSinceRefresh = $member->refresh_date
            ? (int) $member->refresh_date->diffInMonths(now())
            : null;

        $yearJoined = is_numeric($member->year_joined) ? (int) $member->year_joined : null;
        $yearsSinceJoined = $yearJoined && $yearJoined > 1900 ? max(0, now()->year - $yearJoined) : null;
        $dgroupRole = trim((string) $member->dgroup_role);

        return [
            'model_type' => 'member',
            'model_id' => $member->id,
            'follow_up_count_3m' => $followUpCount,
            'months_since_refresh' => $monthsSinceRefresh,
            'active_disciples' => $member->active_disciples === null ? null : (int) $member->active_disciples,
            'weekly_dgroups_led' => $member->weekly_dgroups_led === null ? null : (int) $member->weekly_dgroups_led,
            'is_dgroup_leader' => $dgroupRole === '' ? null : (strcasecmp($dgroupRole, 'Dgroup Leader') === 0 ? 1 : 0),
            'years_since_joined' => $yearsSinceJoined,
            'has_ministry_involvement' => $member->ministry_involvement === null
                ? null
                : (int) (trim((string) $member->ministry_involvement) !== ''),
        ];
    }

    private function extractSeekerFeatures(Seeker $seeker): array
    {
        $followUpCount = $seeker->followUps()
            ->whereDate('created_at', '>=', now()->subMonths(3))
            ->count();

        $monthsSinceRefresh = $seeker->refresh_date
            ? (int) $seeker->refresh_date->diffInMonths(now())
            : null;

        return [
            'model_type' => 'seeker',
            'model_id' => $seeker->id,
            'follow_up_count_3m' => $followUpCount,
            'months_since_refresh' => $monthsSinceRefresh,
            'active_disciples' => $seeker->active_disciples === null ? null : (int) $seeker->active_disciples,
            'weekly_dgroups_led' => $seeker->weekly_dgroups_led === null ? null : (int) $seeker->weekly_dgroups_led,
            'is_dgroup_leader' => $seeker->dgroup_leader_name ? 1 : 0,
            'years_since_joined' => null,
            'has_ministry_involvement' => $seeker->ministry_involvement === null
                ? null
                : (int) (trim((string) $seeker->ministry_involvement) !== ''),
        ];
    }

    private function baselinePrediction(Member $member, array $features, Collection $samples): array
    {
        if ($this->availableIndicatorCount($features) < self::MIN_AVAILABLE_INDICATORS) {
            return $this->resultPayload(
                member: $member,
                classification: self::CLASSIFICATION_INSUFFICIENT,
                confidence: 0.0,
                decisionPath: [
                    [
                        'feature' => 'data_sufficiency',
                        'label' => 'Data Sufficiency',
                        'value' => $this->availableIndicatorCount($features),
                        'operator' => '>=',
                        'threshold' => self::MIN_AVAILABLE_INDICATORS,
                        'branch' => 'no',
                        'outcome' => self::CLASSIFICATION_INSUFFICIENT,
                    ],
                ],
                features: $features,
                modelName: self::MODEL_BASELINE,
                modelVersion: self::BASELINE_MODEL_VERSION,
                modelFamily: 'review_baseline',
                dataSufficiency: 'insufficient_member_indicators',
                metadata: [
                    'reference_labels_available' => $samples->count(),
                    'training_config' => $this->trainingConfig(),
                ],
            );
        }

        $leadershipScore = max(
            (int) ($features['is_dgroup_leader'] ?? 0),
            (int) (($features['active_disciples'] ?? 0) > 0),
            (int) (($features['weekly_dgroups_led'] ?? 0) > 0),
        );

        if ($leadershipScore > 0) {
            return $this->baselinePayload(
                $member,
                $features,
                self::CLASSIFICATION_ACTIVE,
                0.74,
                [
                    $this->pathStep('is_dgroup_leader', $features['is_dgroup_leader'], '=', 1, $features['is_dgroup_leader'] ? 'yes' : 'no', null),
                    $this->pathStep('active_disciples', $features['active_disciples'], '>', 0, ($features['active_disciples'] ?? 0) > 0 ? 'yes' : 'no', null),
                    $this->pathStep('weekly_dgroups_led', $features['weekly_dgroups_led'], '>', 0, ($features['weekly_dgroups_led'] ?? 0) > 0 ? 'yes' : 'no', self::CLASSIFICATION_ACTIVE),
                ],
                $samples,
            );
        }

        $monthsSinceRefresh = $features['months_since_refresh'];
        if (($features['follow_up_count_3m'] ?? 0) === 0 && $monthsSinceRefresh !== null && $monthsSinceRefresh > 6) {
            return $this->baselinePayload(
                $member,
                $features,
                self::CLASSIFICATION_NEEDS_FOLLOW_UP,
                0.68,
                [
                    $this->pathStep('follow_up_count_3m', $features['follow_up_count_3m'], '=', 0, 'yes', null),
                    $this->pathStep('months_since_refresh', $monthsSinceRefresh, '>', 6, 'yes', self::CLASSIFICATION_NEEDS_FOLLOW_UP),
                ],
                $samples,
            );
        }

        if (($features['follow_up_count_3m'] ?? 0) > 0 || ($monthsSinceRefresh !== null && $monthsSinceRefresh <= 6) || ($features['has_ministry_involvement'] ?? 0) === 1) {
            return $this->baselinePayload(
                $member,
                $features,
                self::CLASSIFICATION_MODERATELY_ACTIVE,
                0.66,
                [
                    $this->pathStep('follow_up_count_3m', $features['follow_up_count_3m'], '>', 0, ($features['follow_up_count_3m'] ?? 0) > 0 ? 'yes' : 'no', null),
                    $this->pathStep('months_since_refresh', $monthsSinceRefresh, '<=', 6, $monthsSinceRefresh !== null && $monthsSinceRefresh <= 6 ? 'yes' : 'no', null),
                    $this->pathStep('has_ministry_involvement', $features['has_ministry_involvement'], '=', 1, ($features['has_ministry_involvement'] ?? 0) === 1 ? 'yes' : 'no', self::CLASSIFICATION_MODERATELY_ACTIVE),
                ],
                $samples,
            );
        }

        return $this->baselinePayload(
            $member,
            $features,
            self::CLASSIFICATION_NEEDS_FOLLOW_UP,
            0.58,
            [
                $this->pathStep('follow_up_count_3m', $features['follow_up_count_3m'], '>', 0, 'no', null),
                $this->pathStep('active_disciples', $features['active_disciples'], '>', 0, 'no', null),
                $this->pathStep('weekly_dgroups_led', $features['weekly_dgroups_led'], '>', 0, 'no', self::CLASSIFICATION_NEEDS_FOLLOW_UP),
            ],
            $samples,
        );
    }

    private function baselinePayload(Member $member, array $features, string $classification, float $confidence, array $path, Collection $samples): array
    {
        return $this->resultPayload(
            member: $member,
            classification: $classification,
            confidence: $confidence,
            decisionPath: $path,
            features: $features,
            modelName: self::MODEL_BASELINE,
            modelVersion: self::BASELINE_MODEL_VERSION,
            modelFamily: 'review_baseline',
            dataSufficiency: $samples->count() >= self::MIN_SAMPLES_SPLIT
                ? 'cart_training_labels_not_diverse'
                : 'baseline_used_no_reference_labels',
            metadata: [
                'reference_labels_available' => $samples->count(),
                'training_config' => $this->trainingConfig(),
                'cart_status' => $samples->count() >= self::MIN_SAMPLES_SPLIT
                    ? 'Not enough separate reference labels per class for train, validation, and holdout splits.'
                    : 'Separate human reference labels are required before CART training can run.',
            ],
        );
    }

    private function referenceSamples(int $satelliteId): Collection
    {
        if (! class_exists(EngagementReferenceLabel::class) || ! Schema::hasTable('engagement_reference_labels')) {
            return collect();
        }

        return EngagementReferenceLabel::query()
            ->with('member')
            ->whereHas('member', fn ($query) => $query->where('satellite_id', $satelliteId))
            ->latest('id')
            ->get()
            ->unique('member_id')
            ->filter(fn (EngagementReferenceLabel $label): bool => $label->dataset_version === null && in_array($label->label, [self::CLASSIFICATION_ACTIVE, self::CLASSIFICATION_MODERATELY_ACTIVE, self::CLASSIFICATION_NEEDS_FOLLOW_UP], true))
            ->map(function (EngagementReferenceLabel $referenceLabel): ?array {
                if (! $referenceLabel->member) {
                    return null;
                }

                $features = $this->extractMemberFeatures($referenceLabel->member);

                if ($this->availableIndicatorCount($features) < self::MIN_AVAILABLE_INDICATORS) {
                    return null;
                }

                return [
                    'features' => $features,
                    'label' => $referenceLabel->label,
                    'member_id' => $referenceLabel->member_id,
                ];
            })
            ->filter()
            ->values();
    }

    private function cartTrainingRun(Collection $samples, int $satelliteId): ?array
    {
        $partitions = $this->partitionReferenceSamples($samples);

        if (! $this->canTrainCartFromPartitions($partitions)) {
            return null;
        }

        $tree = $this->trainCartTree($partitions['train']);
        $splitSummary = $this->splitSummary($partitions);
        $labelDistribution = $this->labelDistribution($samples);
        $validationMetrics = $this->evaluateTree($tree, $partitions['validation']);
        $holdoutMetrics = $this->evaluateTree($tree, $partitions['holdout']);
        $modelRun = $this->persistModelRun(
            satelliteId: $satelliteId,
            samples: $samples,
            tree: $tree,
            splitSummary: $splitSummary,
            labelDistribution: $labelDistribution,
            validationMetrics: $validationMetrics,
            holdoutMetrics: $holdoutMetrics,
        );

        return [
            'model_run' => $modelRun,
            'tree' => $tree,
            'split_summary' => $splitSummary,
            'label_distribution' => $labelDistribution,
            'validation_metrics' => $validationMetrics,
            'holdout_metrics' => $holdoutMetrics,
        ];
    }

    /**
     * @return array{train: array<int, array>, validation: array<int, array>, holdout: array<int, array>}
     */
    private function partitionReferenceSamples(Collection $samples): array
    {
        $partitions = [
            'train' => [],
            'validation' => [],
            'holdout' => [],
        ];

        $samples
            ->groupBy('label')
            ->each(function (Collection $labelSamples) use (&$partitions): void {
                $ordered = $labelSamples->sortBy('member_id')->values();
                $count = $ordered->count();

                foreach ($ordered as $index => $sample) {
                    if ($count >= self::MIN_REFERENCE_LABELS_PER_CART_CLASS) {
                        if ($index === $count - 1) {
                            $partitions['holdout'][] = $sample;
                        } elseif ($index === $count - 2) {
                            $partitions['validation'][] = $sample;
                        } else {
                            $partitions['train'][] = $sample;
                        }
                    } elseif ($count >= 3 && $index === $count - 1) {
                        $partitions['validation'][] = $sample;
                    } else {
                        $partitions['train'][] = $sample;
                    }
                }
            });

        return $partitions;
    }

    private function canTrainCartFromPartitions(array $partitions): bool
    {
        $trainLabels = collect($partitions['train'])->pluck('label')->countBy();

        return count($partitions['train']) >= self::MIN_SAMPLES_SPLIT
            && count($partitions['validation']) > 0
            && count($partitions['holdout']) > 0
            && $trainLabels->count() >= 2
            && $trainLabels->filter(fn (int $count): bool => $count >= self::MIN_SAMPLES_LEAF)->count() >= 2;
    }

    private function splitSummary(array $partitions): array
    {
        return collect($partitions)
            ->map(fn (array $samples, string $split): array => [
                'split' => $split,
                'sample_count' => count($samples),
                'label_distribution' => $this->distribution($samples),
            ])
            ->all() + [
                'strategy' => 'deterministic_stratified_by_label_and_member_id',
                'reference_label_source' => 'engagement_reference_labels',
            ];
    }

    private function persistModelRun(
        int $satelliteId,
        Collection $samples,
        array $tree,
        array $splitSummary,
        array $labelDistribution,
        array $validationMetrics,
        array $holdoutMetrics,
    ): ?EngagementModelRun {
        if (! class_exists(EngagementModelRun::class) || ! Schema::hasTable('engagement_model_runs')) {
            return null;
        }

        return EngagementModelRun::updateOrCreate(
            [
                'satellite_id' => $satelliteId,
                'model_name' => self::MODEL_CART,
                'model_version' => self::CART_MODEL_VERSION,
                'training_signature' => $this->trainingSignature($samples, $splitSummary),
            ],
            [
                'training_config' => $this->trainingConfig(),
                'split_summary' => $splitSummary,
                'label_distribution' => $labelDistribution,
                'validation_metrics' => $validationMetrics,
                'holdout_metrics' => $holdoutMetrics,
                'tree' => $tree,
                'trained_at' => now(),
                'is_active' => true,
            ]
        );
    }

    private function trainingSignature(Collection $samples, array $splitSummary): string
    {
        $payload = [
            'model_version' => self::CART_MODEL_VERSION,
            'training_config' => $this->trainingConfig(),
            'split_summary' => $splitSummary,
            'samples' => $samples
                ->sortBy('member_id')
                ->map(fn (array $sample): array => [
                    'member_id' => $sample['member_id'],
                    'label' => $sample['label'],
                    'features' => $sample['features'],
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($payload));
    }

    private function evaluateTree(array $tree, array $samples): array
    {
        if ($samples === []) {
            return [
                'available' => false,
                'sample_count' => 0,
                'accuracy' => null,
                'reason' => 'No samples available for this split.',
            ];
        }

        $correct = 0;
        $predictions = [];
        $labels = collect($samples)
            ->pluck('label')
            ->merge(self::CLASSIFICATIONS)
            ->reject(fn (string $label): bool => $label === self::CLASSIFICATION_INSUFFICIENT)
            ->unique()
            ->values()
            ->all();
        $confusion = [];

        foreach ($samples as $sample) {
            $actual = $sample['label'];
            $predicted = $this->predictFromCart($tree, $sample['features'])['classification'];

            if ($actual === $predicted) {
                $correct++;
            }

            $confusion[$actual][$predicted] = ($confusion[$actual][$predicted] ?? 0) + 1;
            $predictions[] = [
                'member_id' => $sample['member_id'],
                'actual' => $actual,
                'predicted' => $predicted,
                'match' => $actual === $predicted,
            ];
        }

        return [
            'available' => true,
            'sample_count' => count($samples),
            'accuracy' => round($correct / max(1, count($samples)), 4),
            'confusion_matrix' => $confusion,
            'per_label' => $this->perLabelMetrics($labels, $predictions),
            'predictions' => $predictions,
        ];
    }

    private function perLabelMetrics(array $labels, array $predictions): array
    {
        $metrics = [];

        foreach ($labels as $label) {
            $truePositive = 0;
            $falsePositive = 0;
            $falseNegative = 0;

            foreach ($predictions as $prediction) {
                if ($prediction['actual'] === $label && $prediction['predicted'] === $label) {
                    $truePositive++;
                } elseif ($prediction['actual'] !== $label && $prediction['predicted'] === $label) {
                    $falsePositive++;
                } elseif ($prediction['actual'] === $label && $prediction['predicted'] !== $label) {
                    $falseNegative++;
                }
            }

            $precision = ($truePositive + $falsePositive) > 0
                ? $truePositive / ($truePositive + $falsePositive)
                : null;
            $recall = ($truePositive + $falseNegative) > 0
                ? $truePositive / ($truePositive + $falseNegative)
                : null;
            $f1 = $precision !== null && $recall !== null && ($precision + $recall) > 0
                ? 2 * (($precision * $recall) / ($precision + $recall))
                : null;

            $metrics[$label] = [
                'precision' => $precision === null ? null : round($precision, 4),
                'recall' => $recall === null ? null : round($recall, 4),
                'f1' => $f1 === null ? null : round($f1, 4),
                'support' => collect($predictions)->where('actual', $label)->count(),
            ];
        }

        return $metrics;
    }

    private function trainCartTree(array $samples, int $depth = 0, string $nodeId = 'root'): array
    {
        $distribution = $this->distribution($samples);
        $prediction = $this->majorityLabel($distribution);

        if (
            $depth >= self::MAX_DEPTH
            || count($samples) < self::MIN_SAMPLES_SPLIT
            || count($distribution) === 1
        ) {
            return $this->leafNode($samples, $prediction, $distribution, $depth, $nodeId);
        }

        $split = $this->bestSplit($samples);

        if ($split === null) {
            return $this->leafNode($samples, $prediction, $distribution, $depth, $nodeId);
        }

        $leftNodeId = $nodeId.'.L';
        $rightNodeId = $nodeId.'.R';

        return [
            'type' => 'split',
            'node_id' => $nodeId,
            'feature' => $split['feature'],
            'feature_label' => self::FEATURE_LABELS[$split['feature']] ?? $split['feature'],
            'operator' => '<=',
            'threshold' => $split['threshold'],
            'left_node_id' => $leftNodeId,
            'right_node_id' => $rightNodeId,
            'depth' => $depth,
            'samples' => count($samples),
            'gini' => round($this->gini($distribution), 4),
            'distribution' => $distribution,
            'left' => $this->trainCartTree($split['left'], $depth + 1, $leftNodeId),
            'right' => $this->trainCartTree($split['right'], $depth + 1, $rightNodeId),
        ];
    }

    private function bestSplit(array $samples): ?array
    {
        $parentGini = $this->gini($this->distribution($samples));
        $best = null;

        foreach (array_keys(self::FEATURE_LABELS) as $feature) {
            $values = collect($samples)
                ->map(fn (array $sample) => $sample['features'][$feature] ?? null)
                ->filter(fn ($value) => $value !== null && is_numeric($value))
                ->unique()
                ->sort()
                ->values();

            if ($values->count() < 2) {
                continue;
            }

            for ($index = 0; $index < $values->count() - 1; $index++) {
                $threshold = (((float) $values[$index]) + ((float) $values[$index + 1])) / 2;
                [$left, $right] = $this->partitionSamples($samples, $feature, $threshold);

                if (count($left) < self::MIN_SAMPLES_LEAF || count($right) < self::MIN_SAMPLES_LEAF) {
                    continue;
                }

                $weightedGini = ((count($left) / count($samples)) * $this->gini($this->distribution($left)))
                    + ((count($right) / count($samples)) * $this->gini($this->distribution($right)));
                $gain = $parentGini - $weightedGini;

                if ($gain <= 0) {
                    continue;
                }

                if ($best === null || $gain > $best['gain']) {
                    $best = [
                        'feature' => $feature,
                        'threshold' => $threshold,
                        'gain' => $gain,
                        'left' => $left,
                        'right' => $right,
                    ];
                }
            }
        }

        return $best;
    }

    private function partitionSamples(array $samples, string $feature, float $threshold): array
    {
        $left = [];
        $right = [];

        foreach ($samples as $sample) {
            $value = $sample['features'][$feature] ?? null;

            if ($value !== null && is_numeric($value) && (float) $value <= $threshold) {
                $left[] = $sample;
            } else {
                $right[] = $sample;
            }
        }

        return [$left, $right];
    }

    private function predictFromCart(array $tree, array $features): array
    {
        $path = [];
        $node = $tree;

        while (($node['type'] ?? null) === 'split') {
            $feature = $node['feature'];
            $threshold = (float) $node['threshold'];
            $value = $features[$feature] ?? null;
            $goLeft = $value !== null && is_numeric($value) && (float) $value <= $threshold;
            $nextNode = $goLeft ? $node['left'] : $node['right'];

            $path[] = $this->pathStep(
                feature: $feature,
                value: $value,
                operator: '<=',
                threshold: $threshold,
                branch: $goLeft ? 'yes' : 'no',
                outcome: null,
                nodeId: $node['node_id'] ?? null,
                nextNodeId: $nextNode['node_id'] ?? null,
                branchTaken: $goLeft ? 'left' : 'right',
            );

            $node = $nextNode;
        }

        $classification = $node['prediction'] ?? self::CLASSIFICATION_INSUFFICIENT;
        $path[] = [
            'feature' => 'leaf',
            'label' => 'Final CART Leaf',
            'node_id' => $node['node_id'] ?? null,
            'next_node_id' => null,
            'value' => $node['samples'] ?? 0,
            'raw_value' => $node['samples'] ?? 0,
            'operator' => 'predicts',
            'threshold' => null,
            'branch' => 'final',
            'branch_taken' => 'final',
            'outcome' => $classification,
            'predicted_category' => $classification,
            'confidence' => $node['confidence'] ?? 0,
            'distribution' => $node['distribution'] ?? [],
        ];

        return [
            'classification' => $classification,
            'confidence' => (float) ($node['confidence'] ?? 0),
            'decision_path' => $path,
        ];
    }

    private function leafNode(array $samples, string $prediction, array $distribution, int $depth, string $nodeId): array
    {
        $total = max(1, count($samples));

        return [
            'type' => 'leaf',
            'node_id' => $nodeId,
            'prediction' => $prediction,
            'confidence' => round(($distribution[$prediction] ?? 0) / $total, 2),
            'depth' => $depth,
            'samples' => $total,
            'distribution' => $distribution,
        ];
    }

    private function distribution(array $samples): array
    {
        $distribution = [];

        foreach ($samples as $sample) {
            $label = $sample['label'];
            $distribution[$label] = ($distribution[$label] ?? 0) + 1;
        }

        arsort($distribution);

        return $distribution;
    }

    private function majorityLabel(array $distribution): string
    {
        arsort($distribution);

        return array_key_first($distribution) ?: self::CLASSIFICATION_INSUFFICIENT;
    }

    private function gini(array $distribution): float
    {
        $total = array_sum($distribution);

        if ($total <= 0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($distribution as $count) {
            $probability = $count / $total;
            $sum += $probability * $probability;
        }

        return 1 - $sum;
    }

    private function availableIndicatorCount(array $features): int
    {
        return collect(array_keys(self::FEATURE_LABELS))
            ->filter(fn (string $feature): bool => array_key_exists($feature, $features) && $features[$feature] !== null)
            ->count();
    }

    private function pathStep(
        string $feature,
        mixed $value,
        string $operator,
        mixed $threshold,
        string $branch,
        ?string $outcome,
        ?string $nodeId = null,
        ?string $nextNodeId = null,
        ?string $branchTaken = null,
    ): array
    {
        return [
            'feature' => $feature,
            'label' => self::FEATURE_LABELS[$feature] ?? $feature,
            'node_id' => $nodeId,
            'next_node_id' => $nextNodeId,
            'value' => $this->displayValue($value),
            'raw_value' => $value,
            'operator' => $operator,
            'threshold' => $threshold,
            'branch' => $branch,
            'branch_taken' => $branchTaken ?? $branch,
            'outcome' => $outcome,
        ];
    }

    private function usedFeatures(array $features, array $decisionPath): array
    {
        $used = collect($decisionPath)
            ->pluck('feature')
            ->filter(fn ($feature): bool => isset(self::FEATURE_LABELS[$feature]))
            ->unique()
            ->values();

        if ($used->isEmpty()) {
            $used = collect(array_keys(self::FEATURE_LABELS))
                ->filter(fn (string $feature): bool => array_key_exists($feature, $features) && $features[$feature] !== null)
                ->values();
        }

        return $used
            ->mapWithKeys(fn (string $feature): array => [
                $feature => [
                    'label' => self::FEATURE_LABELS[$feature],
                    'value' => $this->displayValue($features[$feature] ?? null),
                    'raw_value' => $features[$feature] ?? null,
                ],
            ])
            ->all();
    }

    private function resultPayload(
        ?Member $member,
        string $classification,
        float $confidence,
        array $decisionPath,
        array $features,
        string $modelName,
        string $modelVersion,
        string $modelFamily,
        string $dataSufficiency,
        array $metadata = [],
        ?int $modelRunId = null,
    ): array {
        return [
            'classification' => $classification,
            'confidence' => round($confidence, 2),
            'decision_path' => $decisionPath,
            'used_features' => $this->usedFeatures($features, $decisionPath),
            'feature_values' => collect($features)
                ->except(['model_type', 'model_id'])
                ->all(),
            'model_type' => $features['model_type'] ?? 'member',
            'model_id' => $features['model_id'] ?? $member?->id,
            'model_name' => $modelName,
            'model_version' => $modelVersion,
            'engagement_model_run_id' => $modelRunId,
            'model_family' => $modelFamily,
            'data_sufficiency' => $dataSufficiency,
            'explanation' => $this->plainLanguageExplanation($classification, $modelName),
            'human_review_required' => true,
            'generated_at' => now(),
            'model_metadata' => $metadata + [
                'max_depth' => self::MAX_DEPTH,
                'min_samples_split' => self::MIN_SAMPLES_SPLIT,
                'min_samples_leaf' => self::MIN_SAMPLES_LEAF,
                'approved_predictors_only' => true,
            ],
        ];
    }

    private function plainLanguageExplanation(string $classification, string $modelName): string
    {
        if ($classification === self::CLASSIFICATION_INSUFFICIENT) {
            return 'The available recorded indicators were not sufficient for a reliable engagement classification.';
        }

        if ($modelName === self::MODEL_CART) {
            return 'The CART model reached this classification by following the recorded engagement indicators shown in the decision path.';
        }

        return 'A review baseline was used because separate human reference labels are not yet sufficient for CART training. This result is for authorized human review only.';
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === 1 || $value === 0) {
            return $value === 1 ? 'Yes' : 'No';
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2), '0'), '.');
        }

        return (string) $value;
    }

    private function labelDistribution(Collection $samples): array
    {
        return $samples
            ->groupBy('label')
            ->map(fn (Collection $items): int => $items->count())
            ->sortDesc()
            ->all();
    }

    private function trainingConfig(): array
    {
        return [
            'algorithm' => 'CART',
            'criterion' => 'gini',
            'max_depth' => self::MAX_DEPTH,
            'min_samples_split' => self::MIN_SAMPLES_SPLIT,
            'min_samples_leaf' => self::MIN_SAMPLES_LEAF,
            'min_reference_labels_per_cart_class' => self::MIN_REFERENCE_LABELS_PER_CART_CLASS,
            'pre_pruning' => true,
            'split_strategy' => 'deterministic_stratified_by_label_and_member_id',
            'split_policy' => [
                'training' => 'All but the final validation and holdout sample inside each sufficiently represented label.',
                'validation' => 'Second to final deterministic sample per sufficiently represented label.',
                'holdout' => 'Final deterministic sample per sufficiently represented label.',
            ],
            'reference_label_source' => 'engagement_reference_labels',
            'excluded_predictors' => [
                'personal_identifiers',
                'contact_information',
                'gender',
                'birthdate',
                'member_status',
                'system_feedback',
                'feedback_sentiment',
                'previous_predictions',
            ],
        ];
    }
}
