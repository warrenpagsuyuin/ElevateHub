<?php

namespace App\Console\Commands;

use App\Services\ReferenceDatasetService;
use App\Services\EngagementEvaluationService;
use App\Services\EngagementEvaluationExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class PrepareEngagementReference extends Command
{
    protected $signature = 'engagement:prepare-reference {--train : Train and evaluate CART after preparing references} {--export : Save the evaluation workbook and report under private storage}';
    protected $description = 'Prepare the deterministic 206-member researcher-entered provisional reference dataset without overwriting reviews';

    public function handle(ReferenceDatasetService $references, EngagementEvaluationService $evaluation): int
    {
        try {
            $dataset = $references->initialize();
            $featureAudit = app(\App\Services\EngagementFeatureAuditService::class)->inspect($references->samples($dataset));
            $this->info('Predictor audit BEFORE training: '.$dataset->revision_version.' ('.$featureAudit['sample_count'].' eligible records)');
            $this->table(['Feature', 'Type', 'Missing', 'Unique values', 'Used by CART?', 'Reason / quality'], array_map(fn ($row) => [
                $row['feature'], $row['data_type'], $row['missing_count'], json_encode($row['unique_values']), 'Yes: supplied', $row['reason'].' '.implode('; ', $row['quality']),
            ], $featureAudit['predictors']));
            $run = $this->option('train') ? $evaluation->train($dataset) : $dataset->activeModelRun;
            $summary = $references->summary($dataset->fresh());
            $report = ['reference_dataset' => $summary, 'feature_audit' => $featureAudit, 'model' => $run ? [
                'dataset_version' => $run->dataset_version, 'model_version' => $run->model_version, 'evaluation_status' => $run->evaluation_status,
                'cross_validation' => $run->validation_metrics, 'parameters' => $run->training_config['selected_parameters'],
                'training_metrics' => $run->training_config['training_metrics'], 'heldout_metrics' => $run->holdout_metrics,
                'majority_baseline' => $run->training_config['baseline'],
                'review_status' => $run->training_config['review_status'] ?? 'MODEL REVIEW REQUIRED',
                'candidate_count' => count($run->training_config['cv']['candidates']),
                'final_training_pruning_path' => $run->training_config['final_training_pruning_path'] ?? [],
            ] : null];
            if ($this->option('export')) {
                File::ensureDirectoryExists(storage_path('app/private/engagement-research'));
                File::put(storage_path('app/private/engagement-research/evaluation.xlsx'), app(EngagementEvaluationExportService::class)->bytes($dataset->fresh()));
                File::put(storage_path('app/private/engagement-research/report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Saved storage/app/private/engagement-research/evaluation.xlsx and report.json');
            }
            $this->line(json_encode(['reference_dataset' => $summary, 'model' => $run ? array_diff_key($report['model'], ['training_metrics' => true, 'heldout_metrics' => true]) + ['accuracy' => $run->holdout_metrics['accuracy'], 'confusion_matrix' => $run->holdout_metrics['confusion_matrix']] : null], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (ValidationException $error) {
            $this->error(implode(' ', array_merge(...array_values($error->errors()))));
            return self::FAILURE;
        }
    }
}
