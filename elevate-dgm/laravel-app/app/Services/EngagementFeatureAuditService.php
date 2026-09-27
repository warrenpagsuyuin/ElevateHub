<?php

namespace App\Services;

class EngagementFeatureAuditService
{
    public function inspect(array $samples): array
    {
        $eligible = array_values(array_filter($samples, fn ($row) => in_array($row['label'], ResearchCartService::LABELS, true)));
        $total = count($eligible);
        $sources = [
            'follow_up_count_3m' => 'Completed follow_ups with follow_up_date in the frozen observation window. Zero means no recorded completed follow-up, not demonstrated inactivity.',
            'months_since_refresh' => 'members.refresh_date, converted to months at observation end; future dates excluded.',
            'active_disciples' => 'members.active_disciples; recorded zeros are retained but cannot establish that the count was independently checked.',
            'weekly_dgroups_led' => 'members.weekly_dgroups_led; blank remains missing.',
            'is_dgroup_leader' => 'members.dgroup_role encoded as leader/nonleader; a role is a limited proxy for engagement.',
            'years_since_joined' => 'members.year_joined converted to elapsed years; tenure is a limited proxy for recent engagement.',
            'has_ministry_involvement' => 'members.ministry_involvement encoded as recorded involvement; blank remains missing.',
        ];
        $rows = [];
        foreach (ResearchCartService::FEATURE_NAMES as $feature) {
            $values = [];
            foreach ($eligible as $sample) {
                $value = $sample['features'][$feature] ?? null;
                if (is_numeric($value) && is_finite((float) $value)) {
                    $values[] = (float) $value;
                }
            }
            $frequencies = [];
            foreach ($values as $value) {
                $key = (string) $value;
                $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
            }
            ksort($frequencies, SORT_NUMERIC);
            $missing = $total - count($values);
            $dominant = $values ? max($frequencies) / count($values) : null;
            $quality = [];
            if (count($frequencies) <= 1) $quality[] = $values ? 'Constant among observed values' : 'Entirely missing';
            elseif ($dominant >= .95) $quality[] = 'Nearly constant: at least 95% of observed values identical';
            if ($total && $missing / $total >= .8) $quality[] = 'Excessive missingness: at least 80%';
            if (($frequencies[0] ?? 0) >= $total * .8 && $total) $quality[] = 'At least 80% recorded zero; check evidence completeness';
            $rows[] = ['feature' => $feature, 'data_type' => in_array($feature, ['is_dgroup_leader', 'has_ministry_involvement'], true) ? 'binary / null' : 'numeric / null',
                'sample_count' => $total, 'missing_count' => $missing, 'missing_percentage' => $total ? round(100 * $missing / $total, 2) : 0,
                'unique_count' => count($frequencies), 'unique_values' => array_map('floatval', array_keys($frequencies)),
                'value_counts' => $frequencies, 'zero_count' => $frequencies[0] ?? 0, 'dominant_observed_fraction' => $dominant,
                'used_by_cart' => true, 'reason' => $sources[$feature], 'quality' => $quality ?: ['Observed variation; does not establish predictive validity']];
        }
        return ['sample_count' => $total, 'predictors' => $rows,
            'scope' => 'Descriptive audit of frozen eligible records before training; no audit-derived feature selection or imputation. All seven preapproved predictors remain supplied; only training data determines splits.',
            'excluded_predictors' => ['member_id', 'name', 'first_name', 'last_name', 'email', 'phone', 'gender', 'facebook_link', 'birthdate', 'satellite_id', 'satellite_name', 'seeker_source', 'sentiment', 'existing_engagement_classification', 'reference_label'],
            'limitation' => 'Seeded provisional labels are assigned independently of engagement evidence. Better model selection cannot establish a meaningful label-feature relationship. Actual evidence and authorized review are needed.'];
    }
}
