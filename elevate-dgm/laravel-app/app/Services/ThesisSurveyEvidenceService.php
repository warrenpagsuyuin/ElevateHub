<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Read-only evidence inventory. Open-ended feedback is never treated as a rating. */
class ThesisSurveyEvidenceService
{
    public const CATEGORIES = ['Ease of Use', 'Usefulness', 'Accessibility', 'Reliability', 'Overall Satisfaction'];

    public const QUESTIONS = [
        'question_1' => 'How easy was it to use the developed system?',
        'question_2' => 'How helpful were the member, seeker, and follow-up workflows?',
        'question_3' => 'How clear were the engagement classification and explanation results?',
        'question_4' => 'What problems, confusion, or missing features did you notice?',
        'question_5' => 'Overall, how satisfied are you with the developed system?',
    ];

    public function report(): array
    {
        // Do not load names, contacts, answers, payloads, or sentiment scores for Table 22.
        $submissions = Feedback::query()->get(['id', 'evaluator_user_id', 'research_consent_status', 'created_at']);
        $consented = $submissions->where('research_consent_status', 'Granted');
        $questions = [];
        foreach (self::QUESTIONS as $field => $prompt) {
            $questions[] = ['field' => $field, 'prompt' => $prompt, 'storage_type' => 'text',
                'input_type' => 'Open-ended textarea', 'likert_item' => false];
        }
        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[] = ['category' => $category, 'item_mapping' => [], 'eligible_n' => 0,
                'weighted_mean' => null, 'distribution' => null, 'interpretation' => 'N/A – No eligible observations',
                'status' => 'N/A – No eligible observations',
                'reason' => 'No validated numeric Likert item or response exists for this category.'];
        }

        return [
            'metadata' => [
                'submission_count' => $submissions->count(),
                'distinct_respondent_count' => $submissions->pluck('evaluator_user_id')->filter(fn ($id) => $id !== null)->unique()->count(),
                'submissions_without_respondent' => $submissions->whereNull('evaluator_user_id')->count(),
                'consented_submission_count' => $consented->count(),
                'consented_distinct_respondent_count' => $consented->pluck('evaluator_user_id')->filter(fn ($id) => $id !== null)->unique()->count(),
                'denied_submission_count' => $submissions->where('research_consent_status', 'Denied')->count(),
                'unknown_consent_submission_count' => $submissions->filter(fn ($row) => ! in_array($row->research_consent_status, ['Granted', 'Denied'], true))->count(),
                'eligible_likert_n' => 0,
                'respondent_count_basis' => 'Distinct non-null evaluator_user_id; repeated submissions do not increase this count.',
                'read_only' => true,
            ],
            'questions' => $questions,
            'table22' => [
                'status' => 'N/A – No eligible observations',
                'reason' => 'The stored evaluation instrument contains five open-ended text questions, not numeric Likert items. Sentiment labels, model scores, confidence, and textual numbers cannot supply survey ratings.',
                'eligible_n' => 0,
                'categories' => $categories,
                'overall_weighted_mean' => null,
                'overall_interpretation' => 'N/A – No eligible observations',
                'item_mapping_status' => 'No approved numeric item-to-category mapping is stored. The listed prompts are the actual qualitative instrument, not scoring mappings.',
                'missing_data_action' => 'Supply actual consented numeric survey responses, the original instrument with its item-to-category mapping, eligibility and missing-response rules, and the agreed weighting method. Do not backfill ratings from text.',
                'formula' => 'For explicit ratings only: weighted mean = sum(rating * frequency) / sum(frequency).',
                'overall_method' => 'N/A – No eligible observations: no category/item weights are established for the current open-ended instrument.',
                'interpretation_scale' => [
                    '4.50-5.00' => 'Excellent', '3.50-4.49' => 'Very Satisfactory', '2.50-3.49' => 'Satisfactory',
                    '1.50-2.49' => 'Fair', '1.00-1.49' => 'Poor',
                ],
                'rounding_rule' => 'Round the arithmetic mean to two decimal places, half up, before applying the stated interpretation intervals.',
            ],
            'qualitative' => $this->qualitative(),
            'validations' => [
                'text_converted_to_ratings' => false,
                'human_themes_inferred_from_sentiment' => false,
                'raw_text_in_portable_report' => false,
                'source_records_modified' => false,
            ],
        ];
    }

    private function qualitative(): array
    {
        $policies = DB::table('sentiment_research_settings')->get();
        $policy = $policies->count() === 1 ? $policies->first() : null;
        $reviewers = array_fill_keys(\App\Models\User::pluck('id')->all(), true);
        $validPeriod = $policy && $policy->period_start && $policy->period_end && $policy->period_start <= $policy->period_end
            && isset($reviewers[$policy->approved_by]) && $policy->approved_at && trim((string)$policy->approval_reason) !== '';
        $units = FeedbackAnalysisUnit::query()->with('feedback')->orderBy('id')->get();
        $parentIds = $units->pluck('parent_unit_id')->filter()->all();
        $byId = $units->keyBy('id');
        $potential = [];
        $candidates = [];
        $excludedReasons = [];
        $seen = [];
        $splitParents = 0;
        $unconsented = 0;

        foreach ($units as $unit) {
            $isParent = $unit->scope_status === 'Split' || in_array($unit->id, $parentIds, true);
            if ($isParent) {
                $splitParents++;
                continue;
            }
            if ($unit->feedback?->research_consent_status !== 'Granted') {
                $unconsented++;
                continue;
            }
            $reasons = [];
            if (!trim((string)$unit->feedback->research_consent_source) || !trim((string)$unit->feedback->research_consent_evidence) || !$unit->feedback->research_consented_at) {
                $reasons[] = 'Documented consent source, evidence and timestamp required';
            }
            if ($unit->scope_status !== 'System Feedback' || !isset($reviewers[$unit->scope_reviewed_by]) || ! $unit->scope_reviewed_at) {
                $reasons[] = 'Human system-feedback scope review required';
            }
            if ($unit->is_test_record !== false) {
                $reasons[] = 'Actual evaluation/non-test status needs human review';
            }
            if (!$validPeriod) {
                $reasons[] = 'Approved evaluation period missing';
            } elseif (! $unit->feedback->created_at || $unit->feedback->created_at->toDateString() < $policy->period_start || $unit->feedback->created_at->toDateString() > $policy->period_end) {
                $reasons[] = 'Outside approved evaluation period';
            }
            if (preg_match('/\p{L}{2}/u', $unit->feedback_text) !== 1) {
                $reasons[] = 'Blank or non-meaningful text';
            }
            $cursor=$unit; $visited=[$unit->id=>true];
            while ($cursor->parent_unit_id !== null) {
                $parent=$byId->get($cursor->parent_unit_id);
                if (!$parent || isset($visited[$parent->id])) { $reasons[]='Missing or cyclic parent'; break; }
                $visited[$parent->id]=true;
                if ($parent->feedback_id !== $unit->feedback_id || !in_array($parent->scope_status,['Split','System Feedback'],true)
                    || $parent->is_test_record === true || !str_contains($parent->feedback_text,$cursor->feedback_text)) {
                    $reasons[]='Parent source, scope or verbatim lineage is invalid';
                }
                $cursor=$parent;
            }
            // Duplicate handling applies only after other eligibility checks. A pending
            // earlier unit cannot remove an otherwise eligible later unit.
            if ($reasons === []) {
                $hash = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($unit->feedback_text))));
                if (isset($seen[$hash])) {
                    $reasons[] = 'Duplicate of '.$seen[$hash];
                } else {
                    $seen[$hash] = $unit->feedback_code;
                }
            }
            $row = [
                'feedback_code' => $unit->feedback_code ?: 'F'.str_pad((string) $unit->id, 3, '0', STR_PAD_LEFT),
                'parent_code' => $unit->parent_unit_id ? $byId->get($unit->parent_unit_id)?->feedback_code : null,
                'language_group' => $unit->language_review_status === 'Final' ? $unit->language_group : null,
                'quote_approved' => (bool) $unit->quote_approved,
                'text' => null,
                'text_status' => 'WITHHELD: use authorized local review; portable reports contain no verbatim text.',
                'review_url' => '/admin/analytics/sentiment/research/units/'.$unit->id,
                'thematic_codes' => [],
                'eligible_for_human_coding' => $reasons === [],
                'reasons' => $reasons,
            ];
            $potential[] = $row;
            if ($reasons === []) {
                $candidates[] = $row;
            } else {
                foreach ($reasons as $reason) {
                    $excludedReasons[$reason] = ($excludedReasons[$reason] ?? 0) + 1;
                }
            }
        }

        return [
            'status' => 'HUMAN THEMATIC CODING REQUIRED',
            'reason' => 'No stored human thematic codebook, coded theme decisions, coder provenance, or adjudication records exist. Human sentiment labels do not establish themes.',
            'themes' => [],
            'coded_n' => 0,
            'candidate_count' => count($candidates),
            'potential_leaf_count' => count($potential),
            'excluded_split_parent_count' => $splitParents,
            'excluded_unconsented_leaf_count' => $unconsented,
            'pending_or_excluded_consented_leaf_count' => count($potential) - count($candidates),
            'exclusion_reason_counts' => $excludedReasons,
            'candidates' => $candidates,
            'review_queue' => $potential,
            'candidate_rule' => 'Consented, human-reviewed system feedback, non-test, within the approved period, meaningful and deduplicated leaf units. Sentiment finalization and model correctness are not inclusion requirements.',
            'counting_rule' => 'A split parent is excluded whenever children exist. Unit counts describe clauses, not distinct respondents; no thematic frequencies are reported before actual human coding.',
            'privacy_rule' => 'All verbatim text is withheld from portable reports, including quote-approved text. Only authorized local review routes can show source wording.',
            'missing_data_action' => 'Complete actual scope/non-test review of consented leaf units, then supply a human codebook, coded decisions, coder attribution and adjudication evidence. Separately review any proposed quotation for consent and identifying details.',
        ];
    }

    /** A future-data calculator only; report() never passes open-ended feedback here. */
    public function calculateWeightedMean(array $ratings): array
    {
        $distribution = array_fill_keys(range(1, 5), 0);
        foreach ($ratings as $rating) {
            if (! is_int($rating) || $rating < 1 || $rating > 5) {
                throw new InvalidArgumentException('Each explicit rating must be an integer from 1 to 5; no text or numeric-string conversion is permitted.');
            }
            $distribution[$rating]++;
        }
        $n = count($ratings);
        $raw = $n > 0 ? array_sum($ratings) / $n : null;
        $mean = $raw === null ? null : round($raw, 2, PHP_ROUND_HALF_UP);

        return ['n' => $n, 'distribution' => $distribution, 'raw_weighted_mean' => $raw,
            'weighted_mean' => $mean, 'interpretation' => $this->interpretation($mean)];
    }

    /** Explicit prospective instrument weights; never called for the existing text instrument. */
    public function calculateOverallMean(array $categoryRatings, array $weights): array
    {
        if (array_diff(self::CATEGORIES,array_keys($categoryRatings)) || array_diff(array_keys($categoryRatings),self::CATEGORIES)
            || array_diff(self::CATEGORIES,array_keys($weights)) || array_diff(array_keys($weights),self::CATEGORIES)) {
            throw new InvalidArgumentException('Every defined category and its explicit weight are required.');
        }
        $numerator=0; $denominator=0; $missing=[];
        foreach (self::CATEGORIES as $category) {
            $weight=$weights[$category];
            if ((!is_int($weight) && !is_float($weight)) || !is_finite((float)$weight) || $weight<=0) throw new InvalidArgumentException('Weights must be finite positive numbers.');
            $mean=$this->calculateWeightedMean($categoryRatings[$category])['raw_weighted_mean'];
            if($mean===null) $missing[]=$category;
            else {$numerator+=$mean*$weight; $denominator+=$weight;}
        }
        $raw=$missing ? null : $numerator/$denominator;
        $rounded=$raw===null ? null : round($raw,2,PHP_ROUND_HALF_UP);
        return ['raw_weighted_mean'=>$raw,'weighted_mean'=>$rounded,'interpretation'=>$this->interpretation($rounded),'missing_categories'=>$missing];
    }

    private function interpretation(?float $mean): string
    {
        return match (true) {
            $mean === null => 'N/A – No eligible observations',
            $mean >= 4.50 => 'Excellent',
            $mean >= 3.50 => 'Very Satisfactory',
            $mean >= 2.50 => 'Satisfactory',
            $mean >= 1.50 => 'Fair',
            default => 'Poor',
        };
    }
}
