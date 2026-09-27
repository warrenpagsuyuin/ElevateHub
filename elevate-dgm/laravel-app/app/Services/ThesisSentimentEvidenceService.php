<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only thesis evidence. Never synchronizes, recognizes labels, or saves predictions.
 * Raw comments and free-text consent/reviewer notes never leave this service.
 */
class ThesisSentimentEvidenceService
{
    public function __construct(
        private SentimentAnalysisService $sentiment,
        private SentimentResearchMetrics $metrics,
    ) {}

    public function report(): array
    {
        $feedback = Feedback::orderBy('id')->get()->keyBy('id');
        $units = FeedbackAnalysisUnit::orderBy('id')->get()->keyBy('id');
        $reviewers = array_fill_keys(User::pluck('id')->all(), true);
        $settings = DB::table('sentiment_research_settings')->orderBy('id')->get();
        $policy = $settings->count() === 1 ? $settings->first() : null;
        $periodValid = $policy && $this->date($policy->period_start) && $this->date($policy->period_end)
            && $policy->period_start <= $policy->period_end
            && isset($reviewers[$policy->approved_by]) && $this->date($policy->approved_at)
            && $this->present($policy->approval_reason);
        $children = [];
        foreach ($units as $unit) {
            if ($unit->parent_unit_id !== null) $children[$unit->parent_unit_id][] = $unit->id;
        }

        $sourceAudit = [];
        foreach ($feedback as $source) {
            $sourceAudit[] = [
                'id' => $source->id,
                'created_at' => $this->date($source->getRawOriginal('created_at')),
                'consent_status' => $this->known($source->research_consent_status, ['Granted', 'Denied', 'Unknown']),
                'consent_source_present' => $this->present($source->research_consent_source),
                'consent_evidence_present' => $this->present($source->research_consent_evidence),
                'consented_at' => $this->date($source->getRawOriginal('research_consented_at')),
                'documented_consent' => $this->consentReasons($source) === [],
                'legacy_human_reference' => $this->known($source->human_sentiment_label, SentimentResearchMetrics::LABELS),
                'legacy_human_reviewer_present' => isset($reviewers[$source->human_labeled_by_id]),
                'legacy_prediction' => $this->known($source->sentiment, SentimentResearchMetrics::LABELS),
                'has_analysis_unit' => $units->contains(fn ($unit) => $unit->feedback_id === $source->id),
                'text_withheld' => true,
            ];
        }

        $audit = $candidates = $seen = [];
        foreach ($units as $unit) {
            $source = $feedback->get($unit->feedback_id);
            $sourceIsSystemFeedback = $this->sourceIsSystemFeedback($source);
            // Form origin is metadata, not permission to override an exclusion or
            // infer that an unreviewed submission is a real non-test observation.
            $effectiveScope = $unit->scope_status;
            $effectiveIsTestRecord = $unit->is_test_record;
            $reasons = $this->consentReasons($source);
            if (!$this->meaningful($unit->feedback_text)) $reasons[] = 'Blank or non-meaningful text';
            if ($unit->text_hash !== $this->textHash($unit->feedback_text)) $reasons[] = 'Stored text hash does not match the analysis text';
            if (isset($children[$unit->id]) || $unit->scope_status === 'Split') $reasons[] = 'Split parent excluded from the analysis cohort';
            if ($effectiveScope !== 'System Feedback') $reasons[] = 'System-feedback scope review is not finalized';
            if ($effectiveIsTestRecord !== false) $reasons[] = 'Actual non-test feedback status is not documented';
            if (!isset($reviewers[$unit->scope_reviewed_by]) || !$this->date($unit->getRawOriginal('scope_reviewed_at'))) {
                $reasons[] = 'Scope review attribution or timestamp is missing';
            }
            if (!$periodValid) $reasons[] = 'Documented approved evaluation period is missing';
            elseif (!$this->date($source?->getRawOriginal('created_at'))
                || substr($this->date($source->getRawOriginal('created_at')), 0, 10) < $policy->period_start
                || substr($this->date($source->getRawOriginal('created_at')), 0, 10) > $policy->period_end) {
                $reasons[] = 'Feedback is outside the approved evaluation period';
            }
            if ($unit->language_review_status !== 'Final' || !in_array($unit->language_group, SentimentResearchMetrics::LANGUAGES, true)) {
                $reasons[] = 'Final human language review is missing';
            }
            if (!isset($reviewers[$unit->language_reviewed_by]) || !$this->date($unit->getRawOriginal('language_reviewed_at'))) {
                $reasons[] = 'Language review attribution or timestamp is missing';
            }
            if ($unit->human_review_status !== 'Final' || !in_array($unit->human_reference_sentiment, SentimentResearchMetrics::LABELS, true)) {
                $reasons[] = 'Final human sentiment reference is missing';
            }
            if (!isset($reviewers[$unit->human_reviewed_by]) || !$this->date($unit->getRawOriginal('human_reviewed_at'))) {
                $reasons[] = 'Human sentiment review attribution or timestamp is missing';
            }
            if ($unit->reference_source !== 'Human Reviewer') $reasons[] = 'Human reviewer reference provenance is not documented';
            if ($unit->independent_attested !== true || !in_array($unit->review_independence, ['Blinded', 'Blinded Re-Review'], true)) {
                $reasons[] = 'Independent blinded human review is not documented';
            }
            $reasons = array_merge($reasons, $this->lineageReasons($unit, $units->all(), $feedback->all()));
            if ($reasons === []) {
                $hash = $this->textHash($unit->feedback_text);
                if (isset($seen[$hash])) $reasons[] = 'Duplicate of analysis unit '.$seen[$hash];
                else $seen[$hash] = $unit->id;
            }
            $stored = $this->known($unit->system_prediction, SentimentResearchMetrics::LABELS);
            $audit[$unit->id] = [
                'id' => $unit->id,
                'feedback_code' => $this->code($unit),
                'feedback_id' => $unit->feedback_id,
                'parent_unit_id' => $unit->parent_unit_id,
                'child_unit_ids' => $children[$unit->id] ?? [],
                'consent_status' => $this->known($source?->research_consent_status, ['Granted', 'Denied', 'Unknown']),
                'scope_status' => $this->known($effectiveScope, ['System Feedback', 'Split', 'Excluded', 'Needs Clarification', 'Pending']),
                'effective_consent' => $source?->research_consent_status,
                'is_test_record' => $effectiveIsTestRecord,
                'source_is_system_evaluation_form' => $sourceIsSystemFeedback,
                'scope_reviewer_present' => isset($reviewers[$unit->scope_reviewed_by]),
                'scope_reviewed_at' => $this->date($unit->getRawOriginal('scope_reviewed_at')),
                'language_group' => $this->known($unit->language_group, SentimentResearchMetrics::LANGUAGES),
                'language_review_status' => $this->known($unit->language_review_status, ['Final', 'Pending', 'Needs Clarification']),
                'language_reviewer_present' => isset($reviewers[$unit->language_reviewed_by]),
                'language_reviewed_at' => $this->date($unit->getRawOriginal('language_reviewed_at')),
                'human_reference_sentiment' => $this->known($unit->human_reference_sentiment, SentimentResearchMetrics::LABELS),
                'human_review_status' => $this->known($unit->human_review_status, ['Final', 'Pending', 'Needs Clarification']),
                'human_reviewer_present' => isset($reviewers[$unit->human_reviewed_by]),
                'human_reviewed_at' => $this->date($unit->getRawOriginal('human_reviewed_at')),
                'reference_source' => $this->known($unit->reference_source, ['Human Reviewer']),
                'independent_attested' => $unit->independent_attested,
                'review_independence' => $this->known($unit->review_independence, ['Blinded', 'Blinded Re-Review', 'Not documented']),
                'quote_approved' => $unit->quote_approved,
                'stored_prediction' => $stored,
                'stored_prediction_current' => $stored !== null && $stored !== 'Unrecognized value'
                    && $unit->sentiment_model_version === SentimentAnalysisService::MODEL_VERSION
                    && $unit->prediction_text_hash === $this->textHash($unit->feedback_text),
                'stored_prediction_version_matches' => $unit->sentiment_model_version === SentimentAnalysisService::MODEL_VERSION,
                'stored_predicted_at' => $this->date($unit->getRawOriginal('predicted_at')),
                'computed_prediction' => null,
                'computed_confidence' => null,
                'prediction_status' => $reasons === [] ? 'Pending in-memory analysis' : 'Not run: ineligible',
                'candidate' => $reasons === [],
                'included' => false,
                'reasons' => array_values(array_unique($reasons)),
                'text_withheld' => true,
            ];
            if ($reasons === []) $candidates[] = $unit;
        }

        // Analyze every eligible case. One failure blocks the entire publication cohort.
        $records = $failures = [];
        foreach ($candidates as $unit) {
            try {
                $prediction = $this->sentiment->analyze($unit->feedback_text);
                if (!in_array($prediction['label'] ?? null, SentimentResearchMetrics::LABELS, true)
                    || ($prediction['model_version'] ?? null) !== SentimentAnalysisService::MODEL_VERSION) {
                    throw new \RuntimeException('Prediction does not match the fixed engine contract.');
                }
                $audit[$unit->id]['computed_prediction'] = $prediction['label'];
                $audit[$unit->id]['computed_confidence'] = $prediction['confidence'] ?? null;
                $audit[$unit->id]['prediction_status'] = 'Computed in memory; not saved';
                $records[] = [
                    'id' => $unit->id, 'feedback_code' => $this->code($unit),
                    'feedback_id' => $unit->feedback_id, 'parent_unit_id' => $unit->parent_unit_id,
                    'language_group' => $unit->language_group,
                    'human_reference_sentiment' => $unit->human_reference_sentiment,
                    'system_prediction' => $prediction['label'],
                    'correct' => $unit->human_reference_sentiment === $prediction['label'],
                    'quote_approved' => $unit->quote_approved,
                    'feedback_text' => '[WITHHELD: manual de-identification required before quotation]',
                    'text_withheld' => true, 'partition' => 'Evaluation',
                    'reference_source' => 'Human Reviewer', 'review_independence' => $unit->review_independence,
                ];
            } catch (\Throwable) {
                // Exception messages may contain source text or other sensitive input.
                $failures[] = $unit->id;
                $audit[$unit->id]['prediction_status'] = 'Failed in-memory analysis';
            }
        }
        if ($failures !== []) $records = [];
        foreach ($candidates as $unit) {
            $audit[$unit->id]['included'] = $failures === [];
            if ($failures !== []) $audit[$unit->id]['reasons'][] = 'Entire evaluation cohort blocked by an in-memory prediction failure';
        }

        $table11 = array_fill_keys(SentimentResearchMetrics::LANGUAGES, array_fill_keys(SentimentResearchMetrics::LABELS, 0));
        $table11Ids = array_fill_keys(SentimentResearchMetrics::LANGUAGES, array_fill_keys(SentimentResearchMetrics::LABELS, []));
        foreach ($records as $record) {
            $table11[$record['language_group']][$record['human_reference_sentiment']]++;
            $table11Ids[$record['language_group']][$record['human_reference_sentiment']][] = $record['id'];
        }
        $overall = $this->metrics->calculate($records);
        $overall['record_ids'] = array_column($records, 'id');
        $languages = [];
        $samples = [];
        foreach (SentimentResearchMetrics::LANGUAGES as $language) {
            $group = array_values(array_filter($records, fn ($row) => $row['language_group'] === $language));
            $languages[$language] = $this->metrics->calculate($group) + ['record_ids' => array_column($group, 'id')];
            $approved = array_values(array_filter($group, fn ($row) => $row['quote_approved']));
            if ($approved !== []) $samples[] = $approved[0];
        }
        $missing = [];
        foreach ($audit as $row) {
            if ($row['reasons'] !== []) $missing[] = ['feedback_code' => $row['feedback_code'], 'unit_id' => $row['id'], 'reasons' => $row['reasons']];
        }
        foreach ($sourceAudit as $source) {
            if (!$source['has_analysis_unit']) $missing[] = ['feedback_id' => $source['id'], 'reasons' => ['No stored analysis unit; this read-only report does not create one']];
        }
        $warnings = [];
        if ($records === []) $warnings[] = 'INSUFFICIENT DATA / UNTESTED: no complete eligible evaluation cohort is available.';
        if ($failures !== []) $warnings[] = 'All tables are withheld because at least one candidate prediction failed; candidate_n remains disclosed.';
        if (count($records) > 0 && count($records) < 30) $warnings[] = 'The sample is small; performance estimates are descriptive and unstable.';
        if (array_filter($overall['per_class'], fn ($row) => $row['support'] === 0)) $warnings[] = 'At least one sentiment class has no actual support; full three-class performance cannot be established.';
        $warnings[] = 'All original text is withheld. Quotation requires explicit approval and manual de-identification.';
        $warnings[] = 'This strict thesis cohort may differ from the operational research report: consent evidence, review provenance, blinding, and parent integrity are mandatory here.';

        $n = count($records);
        $table11FlatIds = [];
        foreach ($table11Ids as $groups) foreach ($groups as $ids) $table11FlatIds = array_merge($table11FlatIds, $ids);
        $languageIds = [];
        foreach ($languages as $group) $languageIds = array_merge($languageIds, $group['record_ids']);
        $recordIds = array_column($records, 'id');
        $sameIds = $this->sameIds($recordIds, $table11FlatIds) && $this->sameIds($recordIds, $languageIds)
            && $this->sameIds($recordIds, $overall['record_ids']);
        $counts = [
            'total_feedback' => $feedback->count(),
            'consented_feedback' => count(array_filter($sourceAudit, fn ($row) => $row['consent_status'] === 'Granted')),
            'documented_consented_feedback' => count(array_filter($sourceAudit, fn ($row) => $row['documented_consent'])),
            'legacy_human_labels' => $feedback->filter(fn ($row) => in_array($row->human_sentiment_label, SentimentResearchMetrics::LABELS, true))->count(),
            'source_predictions_stored' => $feedback->filter(fn ($row) => in_array($row->sentiment, SentimentResearchMetrics::LABELS, true))->count(),
            'analysis_units' => $units->count(), 'candidate_n' => count($candidates),
            'included' => $n, 'excluded' => $units->count() - $n,
            'human_labeled' => $units->filter(fn ($row) => $row->human_review_status === 'Final' && in_array($row->human_reference_sentiment, SentimentResearchMetrics::LABELS, true))->count(),
            'eligible_human_labeled' => count($candidates),
            'blinding_not_documented' => $units->filter(fn ($row) => $row->human_review_status === 'Final'
                && ($row->independent_attested !== true || !in_array($row->review_independence, ['Blinded', 'Blinded Re-Review'], true)))->count(),
            'needs_clarification' => $units->where('human_review_status', 'Needs Clarification')->count(),
            'predictions_available' => count($candidates) - count($failures),
            'unit_predictions_stored' => $units->filter(fn ($row) => in_array($row->system_prediction, SentimentResearchMetrics::LABELS, true))->count(),
            'evaluation_n' => $n, 'missing_predictions' => count($failures),
            'evaluation_ready' => $n > 0 && $failures === [],
        ];
        $report = [
            'status' => $n ? 'Evaluated' : 'INSUFFICIENT DATA / UNTESTED',
            'audit' => ['source_feedback' => $sourceAudit, 'units' => array_values($audit)],
            'records' => $records, 'evaluation_records' => $records,
            'table11' => $table11, 'table11_ids' => $table11Ids, 'table12' => $samples,
            'table18' => $overall['confusion_matrix'], 'table18_ids' => $recordIds,
            'table19' => $overall, 'table20' => $languages,
            'counts' => $counts,
            'validations' => [
                'table11' => array_sum(array_map('array_sum', $table11)) === $n,
                'table12' => array_diff(array_column($samples, 'id'), $recordIds) === [] && count(array_filter($samples, fn ($row) => !$row['quote_approved'])) === 0,
                'table18_19' => array_sum(array_map('array_sum', $overall['confusion_matrix'])) === $n
                    && array_sum(array_column($overall['per_class'], 'support')) === $n,
                'accuracy' => $n === 0 ? $overall['accuracy'] === null : abs($overall['accuracy'] - $overall['correct'] / $n) < 1e-12,
                'table19_20' => array_sum(array_column($languages, 'n')) === $n,
                'same_evaluation_records' => $sameIds,
                'human_labels_from_predictions' => false,
                'source_text_withheld' => true,
                'read_only' => true,
            ],
            'engine' => [
                'type' => 'Lexicon/rule-based', 'name' => SentimentAnalysisService::MODEL_NAME,
                'service' => SentimentAnalysisService::class, 'version' => SentimentAnalysisService::MODEL_VERSION,
                'source_sha256' => hash_file('sha256', (new \ReflectionClass(SentimentAnalysisService::class))->getFileName()),
                'partition_method' => 'Fixed rule-based engine evaluated in memory against independently finalized human references. No training split, model fitting, or cross-validation was performed.',
                'prediction_storage' => 'In memory only; stored predictions are retained solely as audit metadata.',
            ],
            'period' => [
                'period_start' => $policy?->period_start, 'period_end' => $policy?->period_end,
                'approved_at' => $this->date($policy?->approved_at), 'approval_evidence_present' => $this->present($policy?->approval_reason),
                'approved_reviewer_present' => $policy && isset($reviewers[$policy->approved_by]),
                'documented' => (bool) $periodValid,
            ],
            'missing_data' => $missing, 'warnings' => $warnings,
        ];
        $report['dataset_signature'] = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));

        return $report;
    }

    private function consentReasons(?Feedback $source): array
    {
        if (!$source) return ['Source feedback is missing'];
        $reasons = [];
        if ($source->research_consent_status !== 'Granted') $reasons[] = 'Research consent is not granted';
        if (!$this->present($source->research_consent_source) || !$this->present($source->research_consent_evidence)
            || !$this->date($source->getRawOriginal('research_consented_at'))) {
            $reasons[] = 'Research consent source, evidence, or timestamp is missing';
        }
        return $reasons;
    }

    private function sourceIsSystemFeedback(?Feedback $source): bool
    {
        return $source?->research_consent_source === 'System Evaluation Form'
            && $source->evaluator_user_id !== null
            && collect(range(1, 5))->every(fn (int $number): bool => filled($source->{'question_'.$number}));
    }

    private function lineageReasons(FeedbackAnalysisUnit $unit, array $units, array $sources): array
    {
        $reasons = [];
        $seen = [$unit->id => true];
        $cursor = $unit;
        while ($cursor->parent_unit_id !== null) {
            $parent = $units[$cursor->parent_unit_id] ?? null;
            if (!$parent) return [...$reasons, 'Parent analysis unit is missing'];
            if (isset($seen[$parent->id])) return [...$reasons, 'Analysis-unit parent cycle detected'];
            $seen[$parent->id] = true;
            if ($parent->feedback_id !== $unit->feedback_id) $reasons[] = 'Parent and child source feedback do not match';
            if (!in_array($parent->scope_status, ['Split', 'System Feedback'], true) || $parent->is_test_record === true) {
                $reasons[] = 'Parent feedback is excluded, a test record, or has unresolved scope';
            }
            if ($parent->text_hash !== $this->textHash($parent->feedback_text)) $reasons[] = 'Parent text hash does not match its source text';
            if ($cursor->feedback_text === '' || !str_contains($parent->feedback_text, $cursor->feedback_text)) {
                $reasons[] = 'Child text is not a verbatim span of its parent';
            }
            if ($this->consentReasons($sources[$parent->feedback_id] ?? null) !== []) $reasons[] = 'Parent source lacks documented granted consent';
            $cursor = $parent;
        }
        $source = $sources[$cursor->feedback_id] ?? null;
        if ($source && $this->textHash($this->sentiment->feedbackText($source)) !== $this->textHash($cursor->feedback_text)) {
            $reasons[] = 'Root analysis text does not match the stored feedback source';
        }
        return array_values(array_unique($reasons));
    }

    private function sameIds(array $left, array $right): bool
    {
        sort($left); sort($right);
        return $left === $right && count(array_unique($right)) === count($right);
    }

    private function textHash(string $text): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? ''));
    }

    private function meaningful(string $text): bool { return preg_match('/\p{L}{2}/u', $text) === 1; }
    private function present(mixed $value): bool { return is_string($value) && trim($value) !== ''; }
    private function known(mixed $value, array $allowed): ?string
    {
        return $value === null ? null : (in_array($value, $allowed, true) ? $value : 'Unrecognized value');
    }
    private function code(FeedbackAnalysisUnit $unit): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string) $unit->feedback_code) === 1 ? $unit->feedback_code : 'U'.$unit->id;
    }
    private function date(mixed $value): ?string
    {
        if (!$this->present($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T].*)?$/D', $value)) return null;
        try { return CarbonImmutable::parse($value)->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
