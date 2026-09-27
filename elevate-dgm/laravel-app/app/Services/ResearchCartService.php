<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Reproducible Gini CART. This engine has no database dependencies or side effects.
 * Median imputation is fitted separately inside each CV training fold, then on
 * the final training partition. An entirely missing training feature uses zero.
 */
class ResearchCartService
{
    public const MODEL_VERSION = '2026.09.10-research-cart-v3';

    public const LABELS = ['Active', 'Moderately Active', 'Possible Need for Follow-Up'];

    public const FEATURE_LABELS = [
        'follow_up_count_3m' => 'Recent Follow-Ups',
        'months_since_refresh' => 'Months Since Refresh',
        'active_disciples' => 'Active Disciples',
        'weekly_dgroups_led' => 'Weekly D-Groups Led',
        'is_dgroup_leader' => 'D-Group Leader Role',
        'years_since_joined' => 'Years Since Joined',
        'has_ministry_involvement' => 'Ministry Involvement Recorded',
    ];

    public const FEATURE_NAMES = [
        'follow_up_count_3m', 'months_since_refresh', 'active_disciples',
        'weekly_dgroups_led', 'is_dgroup_leader', 'years_since_joined',
        'has_ministry_involvement',
    ];

    /** Exact 20% test size, allocated by largest remainder within class bounds. */
    public function split(array $samples, int $seed = 42): array
    {
        $eligible = [];
        $excluded = [];
        $seen = [];
        foreach ($samples as $sample) {
            $id = $this->memberId($sample);
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('A member may occur only once in the reference dataset.');
            }
            $seen[$id] = true;
            if (($sample['label'] ?? null) === null || in_array($sample['label'], ['Needs Clarification', 'Needs Clarification / Excluded'], true)) {
                $excluded[] = $id;
                continue;
            }
            $eligible[] = $sample;
        }
        sort($excluded, SORT_NUMERIC);
        $eligible = $this->normalize($eligible);
        $grouped = array_fill_keys(self::LABELS, []);
        foreach ($eligible as $sample) {
            $grouped[$sample['label']][] = $sample;
        }
        $testCount = (int) round(count($eligible) * 0.2);
        $allocation = [];
        $fractions = [];
        foreach (self::LABELS as $label) {
            $count = count($grouped[$label]);
            if ($count < 2) {
                throw new InvalidArgumentException('Every target class needs at least two finalized records for a stratified split.');
            }
            $exact = $count * 0.2;
            $allocation[$label] = min($count - 1, max(1, (int) floor($exact)));
            $fractions[$label] = $exact - floor($exact);
        }
        // Small datasets still retain each class in both partitions.
        $testCount = min(count($eligible) - count(self::LABELS), max(count(self::LABELS), $testCount));
        $order = self::LABELS;
        usort($order, fn (string $a, string $b): int => ($fractions[$b] <=> $fractions[$a]) ?: (array_search($a, self::LABELS, true) <=> array_search($b, self::LABELS, true)));
        while (array_sum($allocation) !== $testCount) {
            $add = array_sum($allocation) < $testCount;
            foreach ($add ? $order : array_reverse($order) as $label) {
                if ($add && $allocation[$label] < count($grouped[$label]) - 1) {
                    $allocation[$label]++;
                } elseif (! $add && $allocation[$label] > 1) {
                    $allocation[$label]--;
                }
                if (array_sum($allocation) === $testCount) {
                    break;
                }
            }
        }
        $train = $test = $classes = [];
        foreach (self::LABELS as $label) {
            $ordered = $this->seedOrder($grouped[$label], $seed, 'holdout:'.$label);
            array_push($test, ...array_slice($ordered, 0, $allocation[$label]));
            array_push($train, ...array_slice($ordered, $allocation[$label]));
            $classes[$label] = [
                'total' => count($ordered),
                'training' => count($ordered) - $allocation[$label],
                'testing' => $allocation[$label],
            ];
        }
        $train = $this->seedOrder($train, $seed, 'training-order');
        $test = $this->seedOrder($test, $seed, 'testing-order');

        return [
            'train' => $train,
            'test' => $test,
            'summary' => [
                'seed' => $seed,
                'method' => 'Stratified 80/20; largest-remainder allocation; SHA-256(seed:partition:member_id) ordering (not sklearn RNG)',
                'train_count' => count($train),
                'test_count' => count($test),
                'classes' => $classes,
                'train_member_ids' => array_column($train, 'member_id'),
                'test_member_ids' => array_column($test, 'member_id'),
                'excluded_member_ids' => $excluded,
            ],
        ];
    }

    /** Select all hyperparameters using training-only stratified cross-validation. */
    public function evaluate(array $train, array $test, int $seed = 42): array
    {
        $train = $this->normalize($train);
        $test = $this->normalize($test);
        if ($train === [] || $test === []) {
            throw new InvalidArgumentException('Both training and held-out test samples are required.');
        }
        if (array_intersect(array_column($train, 'member_id'), array_column($test, 'member_id')) !== []) {
            throw new InvalidArgumentException('Training and held-out test members must be disjoint.');
        }
        $distribution = $this->distribution($train);
        if (min($distribution) < 1) {
            throw new InvalidArgumentException('Training must contain all three approved target classes.');
        }
        $foldCount = min(5, min($distribution));
        $folds = $foldCount >= 2 ? $this->folds($train, $foldCount, $seed) : [];
        $preparedFolds = [];
        $foldSummaries = [];
        foreach ($folds as $index => $validation) {
            $validationIds = array_fill_keys(array_column($validation, 'member_id'), true);
            $foldTrain = array_values(array_filter($train, fn (array $sample): bool => ! isset($validationIds[$sample['member_id']])));
            $imputation = $this->fitImputation($foldTrain);
            $preparedFolds[] = ['train' => $this->impute($foldTrain, $imputation), 'validation' => $validation, 'imputation' => $imputation];
            $foldSummaries[] = [
                'fold' => $index + 1,
                'training_member_ids' => array_column($foldTrain, 'member_id'),
                'validation_member_ids' => array_column($validation, 'member_id'),
                'training_class_counts' => $this->distribution($foldTrain),
                'validation_class_counts' => $this->distribution($validation),
                'imputation' => $imputation,
            ];
        }
        $candidates = [];
        $best = null;
        $pruningPaths = [];
        foreach ($this->parameterGrid() as $baseParameters) {
            if ($preparedFolds === []) {
                break;
            }
            $grown = [];
            $alphas = [0.0];
            $paths = [];
            foreach ($preparedFolds as $index => $fold) {
                $weighted = $this->weightSamples($fold['train'], $baseParameters['class_weight']);
                $grown[$index] = $this->growTree($weighted, $baseParameters);
                $paths[$index] = $this->pruningPath($grown[$index]);
                array_push($alphas, ...array_column($paths[$index], 'ccp_alpha'));
            }
            $alphas = array_values(array_unique($alphas, SORT_REGULAR));
            sort($alphas, SORT_NUMERIC);
            $pruningPaths[] = ['parameters' => $baseParameters, 'fold_paths' => $paths, 'candidate_alphas' => $alphas];
            foreach ($alphas as $alpha) {
            $parameters = $baseParameters + ['ccp_alpha' => $alpha];
            $scores = $accuracies = $leaves = [];
            foreach ($preparedFolds as $index => $fold) {
                [$tree] = $this->pruneTree($grown[$index], $alpha, $grown[$index]['weighted_samples']);
                $metrics = $this->scoreTree($tree, $fold['validation'], $fold['imputation']);
                $scores[] = $metrics['macro_f1'];
                $accuracies[] = $metrics['accuracy'];
                $leaves[] = $this->leafCount($tree);
            }
            $mean = array_sum($scores) / count($scores);
            $candidate = [
                'parameters' => $parameters,
                'fold_macro_f1' => $scores,
                'mean_macro_f1' => $mean,
                'std_macro_f1' => sqrt(array_sum(array_map(fn (float $score): float => ($score - $mean) ** 2, $scores)) / count($scores)),
                'mean_accuracy' => array_sum($accuracies) / count($accuracies),
                'mean_leaf_count' => array_sum($leaves) / count($leaves),
            ];
            $candidates[] = $candidate;
            if ($best === null || $this->preferCandidate($candidate, $best)) {
                $best = $candidate;
            }
            }
        }
        $parameters = $best['parameters'] ?? ['max_depth' => 3, 'min_samples_split' => 8, 'min_samples_leaf' => 4, 'ccp_alpha' => 0.0, 'class_weight' => null];
        $imputation = $this->fitImputation($train);
        $tree = $this->fitTree($this->impute($train, $imputation), $parameters);
        $trainingMetrics = $this->scoreTree($tree, $train, $imputation);
        // The first model prediction on held-out samples occurs after selection and fitting.
        $testMetrics = $this->scoreTree($tree, $test, $imputation);
        $majority = $this->majority($distribution);
        $baselineCorrect = count(array_filter($test, fn (array $sample): bool => $sample['label'] === $majority));

        return [
            'tree' => $tree,
            'imputation' => $imputation,
            'imputation_policy' => 'Numeric median fitted on each CV training fold and on final training only; all-missing training features use 0; held-out values never fit imputation.',
            'selected_parameters' => $parameters,
            'cv' => [
                'fold_count' => count($folds),
                'method' => 'Stratified SHA-256 seeded round-robin folds within the training partition only',
                'seed' => $seed,
                'selection_metric' => 'mean_macro_f1',
                'tie_break' => 'Higher mean accuracy, fewer mean leaves, shallower maximum depth, larger minimum leaf/split sizes, larger ccp_alpha; unweighted first on exact ties',
                'pruning_method' => 'Weakest-link effective alpha path on each CV training fold; union of path alphas per base configuration. All candidate generation stays inside the outer training partition.',
                'pruning_paths' => $pruningPaths,
                'candidates' => $candidates,
                'selected_score' => $best['mean_macro_f1'] ?? null,
                'selected_accuracy' => $best['mean_accuracy'] ?? null,
                'folds' => $foldSummaries,
                'reason' => $folds === [] ? 'Insufficient training members per class for cross-validation; fixed documented defaults used without held-out tuning.' : null,
            ],
            'training_metrics' => $trainingMetrics,
            'test_metrics' => $testMetrics,
            'baseline' => [
                'majority_class' => $majority,
                'training_class_counts' => $distribution,
                'accuracy' => $baselineCorrect / count($test),
                'sample_count' => count($test),
            ],
            'model_version' => self::MODEL_VERSION,
            'feature_names' => self::FEATURE_NAMES,
            'class_weights' => $this->classWeights($train, $parameters['class_weight']),
            'final_training_pruning_path' => $this->pruningPath($this->growTree($this->weightSamples($this->impute($train, $imputation), $parameters['class_weight']), $parameters)),
            'training_member_ids' => array_column($train, 'member_id'),
            'heldout_member_ids' => array_column($test, 'member_id'),
        ];
    }

    public function predict(array $tree, array $features, array $imputation = []): array
    {
        $node = $tree;
        $path = $used = [];
        $visited = 0;
        while (($node['type'] ?? null) === 'split') {
            if (++$visited > 100 || ! in_array($node['feature'] ?? null, self::FEATURE_NAMES, true)) {
                throw new RuntimeException('Invalid CART tree or unapproved predictor.');
            }
            $feature = $node['feature'];
            $rawValue = $features[$feature] ?? null;
            $recorded = $this->numeric($rawValue);
            $value = $recorded ?? $this->numeric($imputation[$feature] ?? null) ?? 0.0;
            $left = $value <= $node['threshold'];
            $next = $node[$left ? 'left' : 'right'] ?? null;
            if (! is_array($next)) {
                throw new RuntimeException('CART branch is missing its child node.');
            }
            $path[] = [
                'feature' => $feature,
                'label' => self::FEATURE_LABELS[$feature],
                'node_id' => $node['node_id'],
                'next_node_id' => $next['node_id'],
                'value' => $rawValue,
                'raw_value' => $rawValue,
                'imputed_value' => $value,
                'was_imputed' => $recorded === null,
                'operator' => '<=',
                'threshold' => $node['threshold'],
                'branch' => $left ? 'yes' : 'no',
                'branch_taken' => $left ? 'left' : 'right',
                'outcome' => null,
            ];
            $used[$feature] = ['label' => self::FEATURE_LABELS[$feature], 'value' => $rawValue, 'raw_value' => $rawValue, 'imputed_value' => $value, 'was_imputed' => $recorded === null];
            $node = $next;
        }
        if (($node['type'] ?? null) !== 'leaf' || ! in_array($node['prediction'] ?? null, self::LABELS, true)) {
            throw new RuntimeException('Invalid CART prediction leaf.');
        }
        $path[] = [
            'feature' => 'leaf',
            'label' => 'Final CART Leaf',
            'node_id' => $node['node_id'],
            'next_node_id' => null,
            'value' => $node['samples'],
            'raw_value' => $node['samples'],
            'operator' => 'predicts',
            'threshold' => null,
            'branch' => 'final',
            'branch_taken' => 'final',
            'outcome' => $node['prediction'],
            'predicted_category' => $node['prediction'],
            'confidence' => $node['confidence'],
            'distribution' => $node['distribution'],
            'weighted_distribution' => $node['weighted_distribution'] ?? $node['distribution'],
            'confidence_basis' => 'Training-leaf class proportion after any selected class weighting; not a calibrated probability.',
        ];

        return ['classification' => $node['prediction'], 'confidence' => $node['confidence'], 'decision_path' => $path, 'used_features' => $used];
    }

    /** All input predictions remain in the accuracy denominator, including failures. */
    public function metrics(array $predictions): array
    {
        $confusion = array_fill_keys(self::LABELS, array_fill_keys(self::LABELS, 0));
        $failures = array_fill_keys(self::LABELS, 0);
        $correct = 0;
        foreach ($predictions as $prediction) {
            $actual = $prediction['actual'] ?? null;
            if (! in_array($actual, self::LABELS, true)) {
                throw new InvalidArgumentException('Metrics require one of the three canonical actual labels.');
            }
            $predicted = $prediction['predicted'] ?? null;
            if (! in_array($predicted, self::LABELS, true)) {
                $failures[$actual]++;
                continue;
            }
            $confusion[$actual][$predicted]++;
            $correct += (int) ($actual === $predicted);
        }
        $perClass = [];
        $macro = $weighted = ['precision' => 0.0, 'recall' => 0.0, 'f1' => 0.0];
        $total = count($predictions);
        foreach (self::LABELS as $label) {
            $tp = $confusion[$label][$label];
            $support = array_sum($confusion[$label]) + $failures[$label];
            $predictedCount = array_sum(array_column($confusion, $label));
            $precision = $predictedCount > 0 ? $tp / $predictedCount : 0.0;
            $recall = $support > 0 ? $tp / $support : 0.0;
            $f1 = $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
            $perClass[$label] = ['precision' => $precision, 'recall' => $recall, 'f1' => $f1, 'support' => $support, 'failed_predictions' => $failures[$label]];
            foreach (['precision', 'recall', 'f1'] as $metric) {
                $macro[$metric] += $perClass[$label][$metric] / count(self::LABELS);
                $weighted[$metric] += $total > 0 ? $perClass[$label][$metric] * $support / $total : 0.0;
            }
        }

        return [
            'available' => $total > 0,
            'sample_count' => $total,
            'correct_count' => $correct,
            'failed_count' => array_sum($failures),
            'failed_predictions_by_actual' => $failures,
            'class_order' => self::LABELS,
            'confusion_matrix' => $confusion,
            'accuracy' => $total > 0 ? $correct / $total : 0.0,
            'per_class' => $perClass,
            'per_label' => $perClass,
            'macro_precision' => $macro['precision'],
            'macro_recall' => $macro['recall'],
            'macro_f1' => $macro['f1'],
            'weighted_precision' => $weighted['precision'],
            'weighted_recall' => $weighted['recall'],
            'weighted_f1' => $weighted['f1'],
            'predictions' => $predictions,
        ];
    }

    private function normalize(array $samples): array
    {
        $seen = $result = [];
        foreach ($samples as $sample) {
            $id = $this->memberId($sample);
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate member in a modeling partition.');
            }
            $seen[$id] = true;
            if (! in_array($sample['label'] ?? null, self::LABELS, true)) {
                throw new InvalidArgumentException('Only finalized approved target classes may enter CART.');
            }
            $features = [];
            foreach (self::FEATURE_NAMES as $feature) {
                $features[$feature] = $this->numeric($sample['features'][$feature] ?? null);
            }
            $result[] = ['member_id' => $id, 'label' => $sample['label'], 'features' => $features];
        }
        usort($result, fn (array $a, array $b): int => $a['member_id'] <=> $b['member_id']);

        return $result;
    }

    private function memberId(array $sample): int
    {
        $id = filter_var($sample['member_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('A positive member ID is required for partition auditing.');
        }

        return $id;
    }

    private function numeric(mixed $value): ?float
    {
        if (is_bool($value)) {
            return (float) $value;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private function seedOrder(array $samples, int $seed, string $scope): array
    {
        usort($samples, static function (array $a, array $b) use ($seed, $scope): int {
            return strcmp(hash('sha256', $seed.':'.$scope.':'.$a['member_id']), hash('sha256', $seed.':'.$scope.':'.$b['member_id'])) ?: ($a['member_id'] <=> $b['member_id']);
        });

        return $samples;
    }

    private function folds(array $train, int $count, int $seed): array
    {
        $folds = array_fill(0, $count, []);
        $offset = 0;
        foreach (self::LABELS as $label) {
            $samples = array_values(array_filter($train, fn (array $sample): bool => $sample['label'] === $label));
            foreach ($this->seedOrder($samples, $seed, 'cv:'.$label) as $index => $sample) {
                $folds[($offset + $index) % $count][] = $sample;
            }
            $offset = ($offset + count($samples)) % $count;
        }

        return $folds;
    }

    private function parameterGrid(): array
    {
        $grid = [];
        foreach ([1, 2, 3, 4, 5, 6, null] as $depth) {
            foreach ([2, 4, 6, 8, 10] as $split) {
                foreach ([1, 2, 3, 4, 5] as $leaf) {
                    foreach ([null, 'balanced'] as $weight) {
                        $grid[] = ['max_depth' => $depth, 'min_samples_split' => $split, 'min_samples_leaf' => $leaf, 'class_weight' => $weight];
                    }
                }
            }
        }

        return $grid;
    }

    private function preferCandidate(array $candidate, array $best): bool
    {
        if (abs($candidate['mean_macro_f1'] - $best['mean_macro_f1']) > 1e-12) {
            return $candidate['mean_macro_f1'] > $best['mean_macro_f1'];
        }
        if (abs($candidate['mean_accuracy'] - $best['mean_accuracy']) > 1e-12) {
            return $candidate['mean_accuracy'] > $best['mean_accuracy'];
        }
        $a = $candidate['parameters'];
        $b = $best['parameters'];

        return [$candidate['mean_leaf_count'], $a['max_depth'] ?? PHP_INT_MAX, -$a['min_samples_leaf'], -$a['min_samples_split'], -$a['ccp_alpha']]
            < [$best['mean_leaf_count'], $b['max_depth'] ?? PHP_INT_MAX, -$b['min_samples_leaf'], -$b['min_samples_split'], -$b['ccp_alpha']];
    }

    private function fitImputation(array $train): array
    {
        $imputation = [];
        foreach (self::FEATURE_NAMES as $feature) {
            $values = [];
            foreach ($train as $sample) {
                $value = $sample['features'][$feature];
                if ($value !== null) {
                    $values[] = $value;
                }
            }
            sort($values, SORT_NUMERIC);
            $count = count($values);
            $imputation[$feature] = $count === 0 ? 0.0 : ($count % 2 === 1 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2);
        }

        return $imputation;
    }

    private function impute(array $samples, array $imputation): array
    {
        foreach ($samples as &$sample) {
            foreach (self::FEATURE_NAMES as $feature) {
                $sample['features'][$feature] ??= $imputation[$feature];
            }
        }
        unset($sample);

        return $samples;
    }

    private function fitTree(array $train, array $parameters): array
    {
        $tree = $this->growTree($this->weightSamples($train, $parameters['class_weight'] ?? null), $parameters);
        [$pruned] = $this->pruneTree($tree, $parameters['ccp_alpha'], $tree['weighted_samples']);

        return $pruned;
    }

    private function growTree(array $samples, array $parameters, int $depth = 0, string $nodeId = 'root'): array
    {
        $distribution = $this->distribution($samples);
        $weightedDistribution = $this->weightedDistribution($samples);
        $prediction = $this->majority($weightedDistribution);
        $node = [
            'type' => 'leaf', 'node_id' => $nodeId, 'depth' => $depth,
            'samples' => count($samples), 'distribution' => $distribution,
            'weighted_samples' => array_sum($weightedDistribution), 'weighted_distribution' => $weightedDistribution,
            'gini' => $this->gini($weightedDistribution), 'prediction' => $prediction,
            'confidence' => $weightedDistribution[$prediction] / array_sum($weightedDistribution),
        ];
        if (($parameters['max_depth'] !== null && $depth >= $parameters['max_depth']) || count($samples) < max($parameters['min_samples_split'], 2 * $parameters['min_samples_leaf']) || $node['gini'] < 1e-12) {
            return $node;
        }
        $best = $this->bestSplit($samples, $parameters['min_samples_leaf'], $node['gini']);
        if ($best === null) {
            return $node;
        }
        $left = $right = [];
        foreach ($samples as $sample) {
            if ($sample['features'][$best['feature']] <= $best['threshold']) {
                $left[] = $sample;
            } else {
                $right[] = $sample;
            }
        }
        $node['type'] = 'split';
        $node['feature'] = $best['feature'];
        $node['feature_label'] = self::FEATURE_LABELS[$best['feature']];
        $node['operator'] = '<=';
        $node['threshold'] = $best['threshold'];
        $node['impurity_decrease'] = $best['gain'];
        $node['left_node_id'] = $nodeId.'.L';
        $node['right_node_id'] = $nodeId.'.R';
        $node['left'] = $this->growTree($left, $parameters, $depth + 1, $nodeId.'.L');
        $node['right'] = $this->growTree($right, $parameters, $depth + 1, $nodeId.'.R');

        return $node;
    }

    /** Sweep sorted values using class counts instead of repeatedly partitioning. */
    private function bestSplit(array $samples, int $minLeaf, float $parentGini): ?array
    {
        $best = null;
        $total = count($samples);
        foreach (self::FEATURE_NAMES as $feature) {
            $ordered = $samples;
            usort($ordered, fn (array $a, array $b): int => ($a['features'][$feature] <=> $b['features'][$feature]) ?: ($a['member_id'] <=> $b['member_id']));
            $leftCounts = array_fill_keys(self::LABELS, 0);
            $rightCounts = $this->weightedDistribution($ordered);
            $totalWeight = array_sum($rightCounts);
            for ($index = 0; $index < $total - 1; $index++) {
                $weight = $ordered[$index]['weight'] ?? 1.0;
                $leftCounts[$ordered[$index]['label']] += $weight;
                $rightCounts[$ordered[$index]['label']] -= $weight;
                $leftSize = $index + 1;
                $rightSize = $total - $leftSize;
                $value = $ordered[$index]['features'][$feature];
                $nextValue = $ordered[$index + 1]['features'][$feature];
                if ($leftSize < $minLeaf || $rightSize < $minLeaf || $value === $nextValue) {
                    continue;
                }
                $gain = $parentGini - (array_sum($leftCounts) * $this->gini($leftCounts) + array_sum($rightCounts) * $this->gini($rightCounts)) / $totalWeight;
                if ($gain > 1e-12 && ($best === null || $gain > $best['gain'] + 1e-12)) {
                    $threshold = $value / 2 + $nextValue / 2;
                    // Adjacent representable floats can round a midpoint upward.
                    if ($threshold >= $nextValue) {
                        $threshold = $value;
                    }
                    $best = ['feature' => $feature, 'threshold' => $threshold, 'gain' => $gain];
                }
            }
        }

        return $best;
    }

    /**
     * Exact bottom-up minimization of CART cost R(T) + alpha * leaves(T).
     * R(T) is total leaf Gini weighted by training-root sample count. For any
     * fixed alpha this returns an optimal subtree of the fully grown tree.
     */
    private function pruneTree(array $node, float $alpha, float $rootCount): array
    {
        $leafCost = ($node['weighted_samples'] ?? $node['samples']) / $rootCount * $node['gini'] + $alpha;
        if ($node['type'] === 'leaf') {
            return [$node, $leafCost];
        }
        [$left, $leftCost] = $this->pruneTree($node['left'], $alpha, $rootCount);
        [$right, $rightCost] = $this->pruneTree($node['right'], $alpha, $rootCount);
        if ($leafCost <= $leftCost + $rightCost + 1e-12) {
            $node['type'] = 'leaf';
            $node['pruned'] = true;
            $node['pruning_alpha'] = $alpha;
            unset($node['left'], $node['right'], $node['feature'], $node['feature_label'], $node['threshold'], $node['operator'], $node['left_node_id'], $node['right_node_id'], $node['impurity_decrease']);

            return [$node, $leafCost];
        }
        $node['left'] = $left;
        $node['right'] = $right;

        return [$node, $leftCost + $rightCost];
    }

    private function leafCount(array $node): int
    {
        return $node['type'] === 'leaf' ? 1 : $this->leafCount($node['left']) + $this->leafCount($node['right']);
    }

    /** Successive weakest-link subtrees, including the unpruned tree and root. */
    public function pruningPath(array $tree): array
    {
        $rootWeight = $tree['weighted_samples'] ?? $tree['samples'];
        $path = [];
        $alpha = 0.0;
        while (true) {
            $links = [];
            [$risk, $leaves] = $this->subtreeRisk($tree, $rootWeight, $links);
            $path[] = ['ccp_alpha' => $alpha, 'impurity' => $risk, 'leaf_count' => $leaves];
            if ($leaves === 1) {
                break;
            }
            $alpha = max($alpha, min($links));
            [$next] = $this->pruneTree($tree, $alpha, $rootWeight);
            if ($this->leafCount($next) >= $leaves) {
                throw new RuntimeException('Weakest-link pruning failed to reduce the tree.');
            }
            $tree = $next;
        }
        return $path;
    }

    private function subtreeRisk(array $node, float $rootWeight, array &$links): array
    {
        $risk = ($node['weighted_samples'] ?? $node['samples']) / $rootWeight * $node['gini'];
        if ($node['type'] === 'leaf') {
            return [$risk, 1];
        }
        [$leftRisk, $leftLeaves] = $this->subtreeRisk($node['left'], $rootWeight, $links);
        [$rightRisk, $rightLeaves] = $this->subtreeRisk($node['right'], $rootWeight, $links);
        $leaves = $leftLeaves + $rightLeaves;
        $subtreeRisk = $leftRisk + $rightRisk;
        $links[] = max(0.0, ($risk - $subtreeRisk) / ($leaves - 1));
        return [$subtreeRisk, $leaves];
    }

    private function classWeights(array $samples, ?string $mode): array
    {
        $counts = $this->distribution($samples);
        return array_map(fn ($count) => $mode === 'balanced' && $count > 0 ? count($samples) / (3 * $count) : 1.0, $counts);
    }

    private function weightSamples(array $samples, ?string $mode): array
    {
        $weights = $this->classWeights($samples, $mode);
        foreach ($samples as &$sample) {
            $sample['weight'] = $weights[$sample['label']];
        }
        unset($sample);
        return $samples;
    }

    private function weightedDistribution(array $samples): array
    {
        $counts = array_fill_keys(self::LABELS, 0.0);
        foreach ($samples as $sample) {
            $counts[$sample['label']] += $sample['weight'] ?? 1.0;
        }
        return $counts;
    }

    private function distribution(array $samples): array
    {
        $counts = array_fill_keys(self::LABELS, 0);
        foreach ($samples as $sample) {
            $counts[$sample['label']]++;
        }

        return $counts;
    }

    private function majority(array $distribution): string
    {
        // Canonical class order is the deterministic tie break.
        return array_search(max($distribution), $distribution, true);
    }

    private function gini(array $distribution): float
    {
        $total = array_sum($distribution);
        if ($total <= 1e-12) {
            return 0.0;
        }
        $squares = 0.0;
        foreach ($distribution as $count) {
            $squares += ($count / $total) ** 2;
        }

        return max(0.0, 1.0 - $squares);
    }

    private function scoreTree(array $tree, array $samples, array $imputation): array
    {
        $predictions = [];
        foreach ($samples as $sample) {
            try {
                $prediction = $this->predict($tree, $sample['features'], $imputation);
                $predictions[] = ['member_id' => $sample['member_id'], 'actual' => $sample['label'], 'predicted' => $prediction['classification'], 'match' => $sample['label'] === $prediction['classification']];
            } catch (RuntimeException $exception) {
                $predictions[] = ['member_id' => $sample['member_id'], 'actual' => $sample['label'], 'predicted' => null, 'match' => false, 'failure' => $exception->getMessage()];
            }
        }

        return $this->metrics($predictions);
    }
}
