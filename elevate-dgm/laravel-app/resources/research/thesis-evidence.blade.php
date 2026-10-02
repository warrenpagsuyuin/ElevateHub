@php
$s=$report['sentiment']; $survey=$report['survey']; $n=$s['counts']['evaluation_n'];
$pct=fn($v)=>$v===null?'N/A – No eligible observations':number_format($v*100,2).'%';
$json=fn($v)=>json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$titles=[11=>'Distribution of Human-Labeled System Feedback',12=>'System Feedback and Sentiment Classification',13=>'XAI Functional Evaluation',14=>'Follow-Up Recommendation Functional Test Results',18=>'Confusion Matrix of System-Feedback Sentiment Classification',19=>'Overall Sentiment Classification Performance',20=>'Sentiment Performance According to Language',22=>'System Evaluation Results',23=>'Qualitative System Feedback Readiness'];
$letters=[11=>'B',12=>'C',13=>'D',14=>'E',18=>'F',19=>'G',20=>'H',22=>'I',23=>'J'];
@endphp
<p class="meta">Generated {{ $report['generated_at'] }} · {{ $report['method'] }}</p>
<p>{{ $report['privacy'] }}</p>
@if(!$only)
<section id="audit"><h2>A. Research Data Audit</h2>
<p class="status">{{ $s['status'] }}</p>
<p>{{ $s['counts']['total_feedback'] }} source submissions; {{ $s['counts']['consented_feedback'] }} granted consent; {{ $s['counts']['analysis_units'] }} stored analysis units; {{ $n }} eligible evaluated units.</p>
<p>Only documented consent, approved period, reviewed system-feedback scope, non-test status, finalized human language and independent blinded sentiment labels qualify. Split parents, unresolved scope, denied consent and missing provenance are excluded. Quotation is separately restricted.</p>
<div class="scroll"><table><thead><tr><th>Source code</th><th>Consent</th><th>Documented</th><th>Legacy human label</th><th>Legacy prediction</th></tr></thead><tbody>
@foreach($s['audit']['source_feedback'] as $row)<tr><td>{{ $row['id'] }}</td><td>{{ $row['consent_status'] }}</td><td>{{ $row['documented_consent']?'Yes':'No' }}</td><td>{{ $row['legacy_human_reference'] ?? 'Missing' }}</td><td>{{ $row['legacy_prediction'] ?? 'Missing' }}</td></tr>@endforeach
</tbody></table></div>
<p>Legacy predictions are source-level metadata, not predictions for the evaluated child units. Saved human labels are preserved even when their independent review is undocumented.</p>
<div class="scroll"><table><thead><tr><th>Unit / source / parent</th><th>Language / review</th><th>Human / review</th><th>Stored prediction</th><th>Quote</th><th>Eligibility and reasons</th></tr></thead><tbody>
@foreach($s['audit']['units'] as $row)<tr><td>{{ $row['feedback_code'] }} (#{{ $row['id'] }}) / {{ $row['feedback_id'] }} / {{ $row['parent_unit_id'] ?? '—' }}</td><td>{{ $row['language_group'] ?? 'Missing' }} / {{ $row['language_review_status'] }}</td><td>{{ $row['human_reference_sentiment'] ?? 'Missing' }} / {{ $row['human_review_status'] }}</td><td>{{ $row['stored_prediction'] ?? 'Missing' }}</td><td>{{ $row['quote_approved']?'Approved':'Not approved' }}</td><td>{{ $row['included']?'Included':'Excluded' }}. {{ implode('; ', $row['reasons']) }}</td></tr>@endforeach
</tbody></table></div>
<h3>Source inventory and preserved CART run</h3><pre>{{ $json($report['database_counts']) }}</pre><pre>{{ $json($report['cart_preserved']) }}</pre>
<p>Tables 15–17 are not recomputed or changed. Stored CART metrics remain separate from sentiment metrics. Functional tests use isolated fixtures and do not create live recommendation reviews.</p>
<p>The active CART holdout remains 40 records with 21 correct classifications (52.50%), below its 55.00% majority baseline. The database labels this run Provisional; the report preserves this provenance as well as its numeric results.</p>
<h3>Application audit and changes</h3>
<p>Research sources are the feedback and analysis-unit models, consent/scope/language/human review workflow, sentiment research settings and audit history. The fixed SentimentAnalysisService and SentimentResearchMetrics calculate sentiment; the CART services and stored engagement results supply engagement explanations. Existing operational sentiment eligibility does not require all independence/provenance checks, so this offline report uses a separate strict read-only cohort.</p>
<p>Protected research routes use auth and role:admin. Satellite XAI and recommendation pages use satellite.access and controller/form-request authorization. Missing recommendation list/detail views and assignment controls were supplied; assignments now require recorded catalogue approval and matching conditions. Modified wording persists before final approval or rejection, and assignment/review snapshots record the reviewer and time in engagement_research_audits.</p>
<p>AdminAnalyticsController contains static overview chart values; AdminReportController contains placeholder report values. They are excluded from thesis evidence. Older unused controller stubs and the unused pass-through AdminOnly middleware are not evidence of active enforcement. AdminUserController still creates a default password; this existing deployment weakness is outside the research-table changes. The live database has no numeric survey instrument or human thematic-coding storage. See docs/chapter3-evidence-report.md for the broader system inventory.</p>
<h3>Evaluation period and sentiment implementation</h3><pre>{{ $json($s['period']) }}</pre><pre>{{ $json($s['engine']) }}</pre>
</section>
@endif
@foreach($titles as $number=>$title)
@continue($only && (string)$only !== (string)$number)
@php
$status=in_array($number,[13,14])?(count($report['table'.$number]) && count(array_filter($report['table'.$number],fn($r)=>$r['status']==='PASS'))===count($report['table'.$number])?'PASS':(count(array_filter($report['table'.$number],fn($r)=>$r['status']==='FAIL'))?'FAIL':'UNTESTED')):($number===22?'INSUFFICIENT DATA / UNTESTED':($number===23?'HUMAN THEMATIC CODING REQUIRED':($number===12&&!$s['table12']?'INSUFFICIENT DATA / UNTESTED':$s['status'])));
@endphp
<section id="table-{{ $number }}"><h2>{{ $letters[$number] }}. Table {{ $number }} — {{ $title }}</h2><span class="status">{{ $status }}</span>
<p class="meta">Generated {{ $report['generated_at'] }} · {{ in_array($number,[13,14])?'Research sample N: not applicable; automated functional evidence':('Evaluation N: '.($number===22?0:($number===23?$survey['qualitative']['candidate_count']:$n))) }}</p>
@if($number===11)
<div class="scroll"><table><tr><th>Language Group</th><th>Positive</th><th>Neutral</th><th>Negative</th><th>Total</th></tr>
@foreach($s['table11'] as $language=>$row)<tr><td>{{ $language }}</td>@foreach($row as $count)<td>{{ $count }}</td>@endforeach<td>{{ array_sum($row) }}</td></tr>@endforeach
<tr><th>Total</th>@foreach(['Positive','Neutral','Negative'] as $label)<td>{{ array_sum(array_column($s['table11'],$label)) }}</td>@endforeach<td>{{ $n }}</td></tr></table></div>
<p>Counts describe the exact eligible evaluation cohort. Empty cells count zero units; they do not establish a completed research distribution.</p><h3>Individual unit codes behind each count</h3><pre>{{ $json($s['table11_ids']) }}</pre>
@elseif($number===12)
<table><tr><th>Feedback Code</th><th>Language</th><th>Original Feedback</th><th>Human Reference</th><th>System Prediction</th></tr>
@forelse($s['table12'] as $row)<tr><td>{{ $row['feedback_code'] }}</td><td>{{ $row['language_group'] }}</td><td>{{ $row['feedback_text'] }}</td><td>{{ $row['human_reference_sentiment'] }}</td><td>{{ $row['system_prediction'] }}</td></tr>@empty<tr><td colspan="5">No eligible quotation examples. No sample comments have been invented.</td></tr>@endforelse</table>
<p>At most one quote-approved eligible unit per language is selected. Portable reports withhold all verbatim text pending manual de-identification, even where quote approval exists. Review original wording only in the authorized sentiment unit page.</p>
@elseif(in_array($number,[13,14]))
<p>Method: actual Laravel HTTP tests using SQLite :memory: fixtures. Evidence is source-version matched; missing or stale verification remains UNTESTED. These are functional checks, not live research participant outcomes.</p>
<div class="scroll"><table><tr><th>{{ $number===13?'Test Condition':'Function' }}</th><th>Expected Result</th><th>Actual Result</th><th>Status</th></tr>
@forelse($report['table'.$number] as $row)<tr><td>{{ $row['condition'] }}</td><td>{{ $row['expected'] }}</td><td>{{ $row['actual'] }}</td><td>{{ $row['status'] }}</td></tr>@empty<tr><td colspan="4">No verification condition definitions are available.</td></tr>@endforelse</table></div>
@foreach($report['table'.$number] as $row)<h3>{{ $row['condition'] }} — evidence</h3><p class="meta">Route: {{ $row['route'] }} · Source: {{ $row['source'] }} · Screenshot: {{ $row['screenshot'] }}</p><pre>{{ implode("\n",$row['tests']) }}</pre>@endforeach
<p>Live recommendation catalogue: {{ $report['database_counts']['recommendation_actions'] }} actions; recommendations: {{ $report['database_counts']['recommendations'] }}. Live use and review outcomes cannot be inferred from fixtures.</p>
@elseif($number===18)
<h3>Per-unit prediction audit</h3><table><tr><th>Feedback code</th><th>Language</th><th>Human Reference</th><th>System Prediction</th><th>Correct?</th></tr>
@forelse($s['records'] as $row)<tr><td>{{ $row['feedback_code'] }} (#{{ $row['id'] }})</td><td>{{ $row['language_group'] }}</td><td>{{ $row['human_reference_sentiment'] }}</td><td>{{ $row['system_prediction'] }}</td><td>{{ $row['correct']?'Yes':'No' }}</td></tr>@empty<tr><td colspan="5">No eligible evaluated units.</td></tr>@endforelse</table>
<table><tr><th>Actual / Predicted</th><th>Positive</th><th>Neutral</th><th>Negative</th></tr>@foreach($s['table18'] as $label=>$row)<tr><th>{{ $label }}</th>@foreach($row as $count)<td>{{ $count }}</td>@endforeach</tr>@endforeach</table>
<p>Rows are independent human references; columns are in-memory system predictions. Each evaluated unit contributes once. Sum of cells = {{ array_sum(array_map('array_sum',$s['table18'])) }} = N {{ $n }}.</p>
@elseif($number===19)
<table><tr><th>Sentiment / Average</th><th>Precision</th><th>Recall</th><th>F1-Score</th><th>Support</th></tr>
@foreach($s['table19']['per_class'] as $label=>$row)<tr><td>{{ $label }}</td><td>{{ $pct($row['precision']) }}</td><td>{{ $pct($row['recall']) }}</td><td>{{ $pct($row['f1']) }}</td><td>{{ $row['support'] }}</td></tr>@endforeach
@foreach(['macro'=>'Macro Average','weighted'=>'Weighted Average'] as $key=>$label)<tr><td>{{ $label }}</td>@foreach(['precision','recall','f1'] as $metric)<td>{{ $pct($s['table19'][$key][$metric]) }}</td>@endforeach<td>{{ $n }}</td></tr>@endforeach
<tr><td>Overall Accuracy</td><td>—</td><td>—</td><td>{{ $pct($s['table19']['accuracy']) }}</td><td>{{ $n }}</td></tr></table>
<p>Precision = TP/(TP+FP); recall = TP/(TP+FN); F1 = 2PR/(P+R). Macro means use all three classes; weighted means use human support/N. Accuracy = {{ $s['table19']['correct'] }}/{{ $n }}{{ $n?'':' (undefined)' }}. For N&gt;0, a zero denominator contributes 0 by convention; a class with zero support cannot be meaningfully assessed. For N=0, every performance metric is N/A – No eligible observations, never 0%.</p>
@elseif($number===20)
<table><tr><th>Language</th><th>Test n</th><th>Accuracy</th><th>Macro Precision</th><th>Macro Recall</th><th>Macro F1</th></tr>@foreach($s['table20'] as $language=>$row)<tr><td>{{ $language }}</td><td>{{ $row['n'] }}</td><td>{{ $pct($row['accuracy']) }}</td>@foreach(['precision','recall','f1'] as $metric)<td>{{ $pct($row['macro'][$metric]) }}</td>@endforeach</tr>@endforeach</table>
<p>Language subsets use the exact Table 11/18/19 unit IDs. Sum of language Test n = {{ array_sum(array_column($s['table20'],'n')) }}. Small samples support descriptive reporting only.</p>
@elseif($number===22)
<p>{{ $survey['table22']['reason'] }}</p><p>Actual submissions: {{ $survey['metadata']['submission_count'] }}; distinct identified respondents: {{ $survey['metadata']['distinct_respondent_count'] }}; consented submissions: {{ $survey['metadata']['consented_submission_count'] }}; numeric rating responses: 0.</p>
<table><tr><th>Software-Quality Characteristic</th><th>Weighted Mean</th><th>Verbal Interpretation</th><th>Responses</th></tr>@foreach($survey['table22']['categories'] as $row)<tr><td>{{ $row['category'] }}</td><td>UNTESTED</td><td>{{ $row['interpretation'] }}</td><td>{{ $row['eligible_n'] }}</td></tr>@endforeach<tr><td>Overall Weighted Mean</td><td>UNTESTED</td><td>UNTESTED</td><td>0</td></tr></table>
<p>{{ $survey['table22']['formula'] }} Current numerator and denominator are unavailable, so neither category means nor an overall mean is calculated. No numeric response distribution or approved item mapping exists.</p>
<table><tr><th>Field</th><th>Actual question</th><th>Input</th></tr>@foreach($survey['questions'] as $q)<tr><td>{{ $q['field'] }}</td><td>{{ $q['prompt'] }}</td><td>{{ $q['input_type'] }}</td></tr>@endforeach</table>
<p>Q1 concerns ease, Q2 usefulness and Q5 satisfaction qualitatively. Q3 concerns explanation clarity; Q4 asks about problems. Neither supplies a validated Accessibility or Reliability rating. Text answers cannot be converted into Likert scores.</p><pre>{{ $json($survey['table22']['interpretation_scale']) }}</pre><p>{{ $survey['table22']['rounding_rule'] }}</p>
@elseif($number===23)
<p>{{ $survey['qualitative']['reason'] }}</p><table><tr><th>Theme</th><th>Description</th><th>Frequency</th><th>Representative Evidence</th></tr><tr><td colspan="4">No stored human-coded themes. Frequencies and representative quotations are unavailable.</td></tr></table>
<p>Consented potential leaf units: {{ $survey['qualitative']['potential_leaf_count'] }}; currently eligible for human coding: {{ $survey['qualitative']['candidate_count'] }}. Sentiment correctness is not a thematic inclusion criterion.</p>
<pre>{{ $json($survey['qualitative']['review_queue']) }}</pre>
<p>{{ $survey['qualitative']['missing_data_action'] }}</p>
@endif
<p class="meta">Source: {{ in_array($number,[13,14])?'tests/Feature/ThesisFunctionalEvidenceTest.php; current verification artifact':(in_array($number,[22,23])?'feedback; feedback_analysis_units; ThesisSurveyEvidenceService':'feedback; feedback_analysis_units; sentiment_research_settings; ThesisSentimentEvidenceService; SentimentResearchMetrics') }}.</p>
@if(in_array($number,[11,12,18,19,20]))<p class="meta">Engine: {{ $s['engine']['name'] }} {{ $s['engine']['version'] }}. Eligibility and exclusions: documented consent, period, scope, non-test and independent finalized reviews; split parents excluded. Excluded units: {{ $s['counts']['excluded'] }}. Audit details are in section A and the full JSON export.</p>@endif
</section>
@endforeach
@if(!$only)
<section id="tests"><h2>K. Automated Test Results</h2><p class="status">{{ $report['verification']['status'] }}</p><p>Tests: {{ $report['verification']['total'] }} · Passed: {{ $report['verification']['passed'] }} · Failed: {{ $report['verification']['failed'] }} · Skipped/unverified: {{ $report['verification']['skipped'] }}.</p><p>{{ $report['verification']['reason'] }}</p><p>Run: {{ $report['verification']['generated_at'] ?? 'Unavailable' }}. Reproduce: <code>php scripts/verify-thesis-evidence.php</code>.</p><pre>{{ $json($report['verification']) }}</pre></section>
<section id="missing"><h2>L. Remaining Missing Research Data</h2>@foreach($s['warnings'] as $warning)<p>{{ $warning }}</p>@endforeach<p>Complete legitimate consent/scope/non-test/language review and independently blinded human labeling before sentiment evaluation. Do not infer missing attestations or overwrite labels from predictions.</p><p>{{ $survey['table22']['missing_data_action'] }}</p><p>{{ $survey['qualitative']['missing_data_action'] }}</p><h3>Automatic reconciliation</h3><pre>{{ $json($s['validations']) }}</pre><p>Zero totals reconcile arithmetically; they do not validate performance. Any cohort/count discrepancy stops report generation.</p></section>
<section id="thesis-output"><h2>M. Thesis-Ready Output</h2><p>Exact current values and computations appear in sections B–J. Tables 13 and 14 may be reported as functional test results only where their current statuses are PASS. Cite the named tests, routes and sources alongside the isolated test method.</p><p><strong>Suggested discussion:</strong> The implemented engagement explanations and recommendation review workflow were evaluated through authenticated HTTP requests and database assertions in an isolated test environment. The results document software behavior, not model accuracy or effectiveness of real follow-up actions. The live research database currently lacks a complete eligible independent sentiment cohort and numeric survey ratings; consequently sentiment performance and survey weighted means remain untested. Human thematic coding is still required.</p><p>Use the offline Markdown report for the thesis tables. For Table 13, capture the authorized engagement detail and version/path/notice. For Table 14, capture the isolated recommendation review and history pages; do not create research-database reviews for screenshots. Redact identities. An empty live catalogue is evidence of unavailable operational data, not a functional failure.</p><p>Never present missing tables as completed findings. The sentiment engine is fixed multilingual lexicon- and rule-based; it is not a transformer or a newly trained model.</p></section>
@endif
