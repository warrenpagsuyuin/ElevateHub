<section class="panel"><h2>Eligibility checklist</h2><p>Green items are complete. Yellow items need human review. Red items are excluded. Consent, scope, and non-test status may be inherited from the original System Evaluation Form when the source provenance proves it; language, human sentiment, and independence are never invented.</p><div class="table-wrap"><table><thead><tr><th>Code</th><th>Consent</th><th>System Feedback</th><th>Actual / Non-Test</th><th>Evaluation Period</th><th>Language</th><th>Human Sentiment</th><th>Independent Review</th><th>Eligible</th><th>Missing requirement</th></tr></thead><tbody>
@foreach($report['eligibility_checklist'] as $audit)
@php
	$reasons = $audit['reasons'] ?? [];
	$has = fn (string $needle) => !collect($reasons)->contains(fn ($reason) => str_contains(strtolower($reason), strtolower($needle)));
	$excluded = in_array('Research consent is not granted', $reasons, true) || ($audit['scope_status'] ?? '') === 'Split' && !empty($audit['child_unit_ids']);
	$state = fn (bool $complete, bool $red = false) => $red ? 'check-red' : ($complete ? 'check-green' : 'check-yellow');
@endphp
<tr><td>{{ $audit['feedback_code'] }}</td>
<td><span class="{{ $state(($audit['effective_consent'] ?? $audit['consent_status'] ?? null) === 'Granted', ($audit['effective_consent'] ?? $audit['consent_status'] ?? null) === 'Denied') }}">{{ $audit['effective_consent'] ?? $audit['consent_status'] ?? 'Needs review' }}</span></td>
<td><span class="{{ $state(($audit['scope_status'] ?? null) === 'System Feedback' && empty($audit['is_split_parent']), !empty($audit['is_split_parent'])) }}">{{ !empty($audit['is_split_parent']) ? 'Excluded parent' : (($audit['scope_status'] ?? null) === 'System Feedback' ? 'Complete' : 'Needs review') }}</span></td>
<td><span class="{{ $state(($audit['is_test_record'] ?? null) === false, ($audit['is_test_record'] ?? null) === true) }}">{{ ($audit['is_test_record'] ?? null) === false ? 'Actual' : (($audit['is_test_record'] ?? null) === true ? 'Test / Excluded' : 'Needs review') }}</span></td>
<td><span class="{{ $state(!in_array('Documented approved evaluation period is missing', $reasons, true)) }}">{{ in_array('Documented approved evaluation period is missing', $reasons, true) ? 'Needs review' : 'Complete' }}</span></td>
<td><span class="{{ $state(($audit['language_review_status'] ?? null) === 'Final' && !empty($audit['language_group'])) }}">{{ ($audit['language_review_status'] ?? null) === 'Final' ? $audit['language_group'] : 'Needs review' }}</span></td>
<td><span class="{{ $state(($audit['human_review_status'] ?? null) === 'Final' && !empty($audit['human_reference_sentiment'])) }}">{{ ($audit['human_review_status'] ?? null) === 'Final' ? $audit['human_reference_sentiment'] : 'Needs review' }}</span></td>
<td><span class="{{ $state(($audit['independent_attested'] ?? false) === true && in_array($audit['review_independence'] ?? null, ['Blinded', 'Blinded Re-Review'], true)) }}">{{ ($audit['independent_attested'] ?? false) ? ($audit['review_independence'] ?? 'Attested') : 'Needs review' }}</span></td>
<td><strong class="{{ ($audit['included'] ?? false) ? 'check-green' : 'check-yellow' }}">{{ ($audit['included'] ?? false) ? 'YES' : 'NO' }}</strong></td>
<td>{{ $reasons ? implode('; ', $reasons) : 'Ready for prediction/evaluation' }}</td></tr>
@endforeach
</tbody></table></div></section>
