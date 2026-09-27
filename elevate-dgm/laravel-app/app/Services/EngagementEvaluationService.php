<?php

namespace App\Services;

use App\Models\EngagementModelRun;
use App\Models\EngagementReferenceDataset;
use App\Models\EngagementReferenceLabel;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngagementEvaluationService
{
    public function __construct(private ReferenceDatasetService $references, private ResearchCartService $cart) {}

    public function train(EngagementReferenceDataset $dataset, ?User $actor = null): EngagementModelRun
    {
        if ($actor) {
            $this->references->authorize($actor);
        }
        $dataset->refresh();
        if ($dataset->version !== ReferenceDatasetService::VERSION) {
            throw ValidationException::withMessages(['dataset' => 'Prepare the corrected current reference version before training. Historical datasets cannot be retrained here.']);
        }
        if ($dataset->references()->count() !== (int) $dataset->member_count) {
            throw ValidationException::withMessages(['dataset' => 'A cohort member is missing. Resolve the dataset before training.']);
        }
        $samples = $this->references->samples($dataset);
        $signature = hash('sha256', json_encode([$dataset->revision_version, $dataset->evaluation_status, ResearchCartService::MODEL_VERSION, $samples], JSON_THROW_ON_ERROR));
        $existing = $dataset->activeModelRun;
        if ($existing && $existing->is_active && $existing->training_signature === $signature) {
            return $existing;
        }
        try {
            $split = $this->cart->split($samples, $dataset->seed);
            $evaluation = $this->cart->evaluate($split['train'], $split['test'], $dataset->seed);
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['dataset' => $error->getMessage()]);
        }
        $revision = $dataset->revision;
        $status = $dataset->evaluation_status;
        return DB::transaction(function () use ($dataset, $revision, $status, $signature, $split, $evaluation, $actor) {
            $current = EngagementReferenceDataset::lockForUpdate()->findOrFail($dataset->id);
            if ($current->revision !== $revision || $current->evaluation_status !== $status) {
                throw ValidationException::withMessages(['dataset' => 'Reference reviews changed during training. Run the evaluation again.']);
            }
            if ($current->active_model_run_id) {
                EngagementModelRun::whereKey($current->active_model_run_id)->update(['is_active' => false]);
            }
            $summary = $split['summary'] + [
                'train' => ['sample_count' => count($split['train'])], 'holdout' => ['sample_count' => count($split['test'])],
            ];
            $config = [
                'algorithm' => 'Gini CART with minimal cost-complexity subtree pruning',
                'seed' => $current->seed, 'selected_parameters' => $evaluation['selected_parameters'],
                'cv' => $evaluation['cv'], 'imputation' => $evaluation['imputation'], 'imputation_policy' => $evaluation['imputation_policy'],
                'feature_audit' => app(EngagementFeatureAuditService::class)->inspect($this->references->samples($current)),
                'class_weights' => $evaluation['class_weights'], 'final_training_pruning_path' => $evaluation['final_training_pruning_path'],
                'review_status' => $evaluation['test_metrics']['accuracy'] <= $evaluation['baseline']['accuracy'] ? 'MODEL REVIEW REQUIRED' : 'MODEL REVIEW REQUIRED: provisional development labels require evidence-based verification',
                'feature_names' => $evaluation['feature_names'], 'training_metrics' => $evaluation['training_metrics'],
                'baseline' => $evaluation['baseline'], 'observation_start' => $current->observation_start->toDateString(),
                'observation_end' => $current->observation_end->toDateString(), 'assignment_method' => $current->assignment_method,
                'reference_snapshot' => $current->references()->orderBy('member_id')->get(['member_id', 'reference_label', 'review_status', 'reference_source', 'verification_status', 'reviewed_by', 'reviewed_at', 'review_revision'])->toArray(),
                'training_member_ids' => $evaluation['training_member_ids'], 'heldout_member_ids' => $evaluation['heldout_member_ids'],
                'approved_predictors_only' => true, 'heldout_used_for_selection' => false,
                'excluded_predictors' => ['member_id', 'name', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'facebook_link', 'gender', 'birthdate', 'seeker_source', 'system_feedback', 'sentiment', 'reference_label', 'existing_prediction', 'member_status', 'satellite_id'],
            ];
            $run = EngagementModelRun::create([
                'satellite_id' => null, 'model_name' => EngagementClassificationService::MODEL_CART,
                'model_version' => $evaluation['model_version'], 'dataset_version' => $current->revision_version,
                'evaluation_status' => $current->evaluation_status, 'training_signature' => $signature,
                'training_config' => $config, 'split_summary' => $summary,
                'label_distribution' => array_map(fn ($row) => $row['total'], $summary['classes']),
                'validation_metrics' => ['available' => $evaluation['cv']['fold_count'] > 1, 'accuracy' => $evaluation['cv']['selected_accuracy'], 'macro_f1' => $evaluation['cv']['selected_score'], 'fold_count' => $evaluation['cv']['fold_count']],
                'holdout_metrics' => $evaluation['test_metrics'], 'tree' => $evaluation['tree'], 'trained_at' => now(), 'is_active' => true,
            ]);
            $current->update(['active_model_run_id' => $run->id, 'split_summary' => $summary]);
            $this->references->audit('cart.trained', $current, $actor, null, null, ['model_run_id' => $run->id, 'dataset_version' => $run->dataset_version, 'parameters' => $evaluation['selected_parameters'], 'cv_folds' => $evaluation['cv']['fold_count'], 'training_member_ids' => $evaluation['training_member_ids']]);
            $this->references->audit('cart.evaluated', $current, $actor, null, null, ['model_run_id' => $run->id, 'heldout_metrics' => $evaluation['test_metrics'], 'majority_baseline' => $evaluation['baseline']]);
            foreach ($current->references()->with('member')->get() as $reference) {
                if ($reference->member) {
                    app(EngagementClassificationService::class)->storeClassification($this->payload($reference->member, $run, $reference->feature_snapshot ?? []), $actor?->id);
                }
            }
            return $run;
        });
    }

    public function predictionFor(EngagementReferenceLabel $reference): ?array
    {
        $dataset = EngagementReferenceDataset::where('version', $reference->dataset_version)->first();
        $run = $dataset?->activeModelRun;
        if (! $run || ! $run->is_active || $run->dataset_version !== $dataset->revision_version || ! $reference->member) {
            return null;
        }
        return $this->payload($reference->member, $run, $reference->feature_snapshot ?? []);
    }

    public function forMember(Member $member, EngagementReferenceDataset $dataset): ?array
    {
        $run = $dataset->activeModelRun;
        if (! $run || ! $run->is_active || $run->dataset_version !== $dataset->revision_version) {
            return null;
        }
        $reference = $dataset->references()->where('member_id', $member->id)->first();
        $features = $reference?->feature_snapshot ?? $this->references->features($member, CarbonImmutable::parse($run->training_config['observation_start']), CarbonImmutable::parse($run->training_config['observation_end']));
        return $this->payload($member, $run, $features);
    }

    private function payload(Member $member, EngagementModelRun $run, array $features): array
    {
        $prediction = $this->cart->predict($run->tree, $features, $run->training_config['imputation']);
        $used = [];
        foreach ($prediction['used_features'] as $feature => $value) {
            $used[] = $value + ['feature' => $feature, 'display_value' => $value['value'] === null ? 'Not recorded' : (string) $value['value']];
        }
        $notice = $run->evaluation_status === 'Human Verified Final'
            ? 'Trained using a human-verified reference dataset. Recommendations still require authorized human review.'
            : 'Provisional evaluation using researcher-entered reference assignments. This result is not independent human verification and must be reviewed before action.';
        return [
            'classification' => $prediction['classification'], 'confidence' => round($prediction['confidence'] * 100, 2),
            'decision_path' => $prediction['decision_path'], 'used_features' => $used, 'feature_values' => $features,
            'model_type' => 'member', 'model_id' => $member->id, 'model_name' => $run->model_name, 'model_version' => $run->model_version,
            'engagement_model_run_id' => $run->id, 'model_family' => 'cart', 'data_sufficiency' => 'research_snapshot_with_training_imputation',
            'explanation' => 'The recorded CART path shows the actual rules and final leaf used for this prediction. '.$notice,
            'human_review_required' => true, 'generated_at' => now(), 'reference_notice' => $notice,
            'model_metadata' => ['model_run_id' => $run->id, 'dataset_version' => $run->dataset_version, 'evaluation_status' => $run->evaluation_status,
                'selected_parameters' => $run->training_config['selected_parameters'], 'approved_predictors_only' => true,
                'training_samples' => $run->split_summary['train_count'], 'split_summary' => $run->split_summary,
                'imputation' => $run->training_config['imputation'], 'reference_notice' => $notice],
        ];
    }
}
