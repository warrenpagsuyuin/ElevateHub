# Chapter 3 Evidence Report

## Elevate Discipleship Group Management System

**Thesis title:** *Elevate Discipleship Group Management System: Predictive Analytics and Sentiment Analysis for Seeker Tracking and Member Engagement*  
**Technology:** Laravel/PHP, SQLite, Blade, PHP CART implementation, lexicon/rule-based sentiment service  
**Evidence date:** 18 September 2026  
**Evidence source:** Live `database/database.sqlite`, current application code, migrations, routes, services, Blade views, and automated tests

> **Research-integrity statement:** This report uses stored system records and read-only evidence services. No member, seeker, feedback, consent decision, human label, prediction, or research record was created or changed while preparing this report. No denied-consent feedback is included in research metrics. No synthetic survey response or research finding is presented as real evidence.

## Executive conclusion

The current system can support the operational workflows for members, seekers, follow-ups, engagement classification, XAI review, and administrator research review. The live SQLite database passes its integrity check.

The engagement research workflow currently contains 206 reference records, but all 206 are `Provisional` and researcher-entered. None is human verified. The active CART run is therefore a reproducible development/evaluation workflow, not evidence of validated real-world engagement prediction. The held-out accuracy is 52.50%, below the 55.00% majority-class baseline by 2.50 percentage points. The correct thesis conclusion is **MODEL REVIEW REQUIRED**, not “the model is accurate” or “the model is ready for deployment.”

The strict sentiment research cohort is not evaluable at this time. There are five stored feedback submissions, three with Granted consent and two with Denied consent, but zero eligible records after consent, scope, date, language, provenance, and independent-review requirements. Tables 18–20 are therefore `UNTESTED`, not 0%.

The system-evaluation instrument contains five open-ended text questions, not numeric Likert items. Consequently, Table 22 weighted means and 1–5 interpretations are `UNTESTED`. Text cannot be converted into numeric ratings without actual numeric responses and an approved instrument mapping.

## 1. System and evidence scope

### Implemented system surfaces inspected

- Authentication, password login for administrators, and OTP login for other accounts.
- Role middleware and satellite/district access middleware.
- Administrator dashboard, satellite dashboard, users, satellites, members, seekers, reports, analytics, and feedback pages.
- Member and seeker models, migrations, controllers, and forms.
- Member-based DGroup leader representation and seeker-to-leader assignment.
- Follow-up records, statuses, methods, notes, and follow-up sentiment.
- Baseline engagement classification and current CART mode.
- CART split, training-only cross-validation, imputation, class selection, pruning, holdout evaluation, and XAI decision paths.
- Recommendation catalogue, assignment, review, status history, and audit events.
- System feedback submission, consent, feedback analysis units, human sentiment review, language review, split-unit workflow, and sentiment evaluation.
- Engagement research reference review, dataset split, model training, model export, and audit history.
- CSV/XLSX exports, print/PDF Table 9 support, and automated feature/metric/authorization tests.

### Important implementation distinction

The application has no standalone `dgroups` table. DGroups are represented by active member records with `dgroup_role = 'Dgroup Leader'`; member assignment is represented by `dgroup_leader_name`. Therefore, “DGroups” in the dashboard means the active-leader-derived DGroup count, not a separate count of rows in a DGroup table.

## 2. Live database inventory

The following values were read from the live SQLite database with `PRAGMA query_only=ON`.

| Table/evidence area | Live count/value |
|---|---:|
| Members | 206 |
| Satellites | 6 |
| Seekers | 522 |
| Follow-ups | 7 |
| Feedback submissions | 5 |
| Feedback analysis units | 11 |
| Engagement results | 206 |
| Recommendations | 0 |
| Recommendation catalogue actions | 0 |
| Engagement reference datasets | 2, v1 and v2 |
| Engagement reference labels | 412, 206 per dataset version |
| Engagement model runs | 2, v1 inactive and v2 active |
| Engagement research audit rows | 9 |
| Sentiment research audit rows | 15 |
| Sentiment evaluation runs | 0 |
| SQLite integrity check | `ok` |

### Member and DGroup data

| Member category | Count |
|---|---:|
| Active DGroup Leaders | 28 |
| Active DGroup Members | 149 |
| Other active total | 177 |
| Inactive members | 23 |
| New members | 6 |
| Total members | 206 |

The 28 active DGroup Leader records are distributed as follows:

| Satellite | Hub | Leaders | Members | Seekers | New members |
|---|---|---:|---:|---:|---:|
| Pasig | MAIN | 14 | 105 | 53 | 5 |
| MAIN ORTIGAS | MAIN | 14 | 101 | 0 | 1 |
| Anonas | MAIN | 0 | 0 | 0 | 0 |
| UBELT | MAIN | 0 | 0 | 0 | 0 |
| THE HUB | MAIN | 0 | 0 | 0 | 0 |
| EASTWOOD | MAIN | 0 | 0 | 32 | 0 |
| **Total** | **MAIN** | **28** | **206** | **85** | **6** |

The live database currently has no satellite rows under NCP or ICP. Any thesis table presenting nonzero NCP/ICP operational totals must be identified as historical, configured, or external data rather than live database evidence.

### Seeker data

| Seeker status | Count |
|---|---:|
| Pending | 522 |
| Successful | 0 |
| Unsuccessful | 0 |
| **Total** | **522** |

### Follow-up data

| Follow-up status | Count |
|---|---:|
| Completed | 2 |
| Pending | 2 |
| Cancelled | 3 |
| **Total** | **7** |

| Follow-up sentiment | Count |
|---|---:|
| Positive | 1 |
| Neutral | 5 |
| Negative | 1 |
| **Total analyzed labels** | **7** |

The evidence audit found that the seven follow-ups are linked to seekers, not members. They must not be relabeled as member engagement evidence merely because numeric IDs might appear comparable.

## 3. PASS/FAIL/UNTESTED evidence checks

### 3.1 Data integrity and operational checks

| Check | Result | Evidence and computation |
|---|---|---|
| SQLite database integrity | **PASS** | `PRAGMA integrity_check` returned `ok`. |
| Member cohort size for current CART dataset | **PASS** | `206` distinct members required; database contains 206. |
| Current reference-label row count | **PASS** | Current v2 contains 206 rows for 206 members. |
| Current engagement reference completeness | **PASS** | 206 reference rows exist; 0 missing member references in the prepared cohort. |
| Global reference label margin | **PASS** | 112 + 63 + 25 + 6 = 206. |
| Reference percentages | **PASS** | 112/206 = 54.37%; 63/206 = 30.58%; 25/206 = 12.14%; 6/206 = 2.91%; rounded total = 100.00%. |
| Clarification exclusion | **PASS** | 6 clarification records are excluded; eligible target records = 206 - 6 = 200. |
| Train/holdout partition total | **PASS** | 160 + 40 = 200 eligible records. |
| Train/holdout disjointness | **PASS** | Stored split uses distinct member IDs; automated tests cover no overlap. |
| One active DGroup Leader per derived DGroup | **PASS, model-derived** | Active leader count = 28 and dashboard DGroup total is defined as the same active leader count. There is no separate DGroup table. |
| NCP/ICP live operational rows | **FAIL for nonzero claims** | All six satellites are stored under MAIN; NCP and ICP counts are zero in the live database. |
| Seeker conversion evidence | **UNTESTED as completed outcome** | 522 seekers are Pending; 0 Successful and 0 Unsuccessful. |
| Recommendation assignment evidence | **UNTESTED** | Recommendation table contains 0 rows and catalogue contains 0 actions. |
| General report activity | **FAIL / incomplete feature** | Reports Dashboard counters are hard-coded/placeholder-style zero values and rows say No reports. |
| General audit-log page | **FAIL / not implemented** | No general Audit Logs page exists; only workflow-specific research/recommendation audit history exists. |

### 3.2 CART implementation checks

| Check | Result | Evidence |
|---|---|---|
| CART model exists in code | **PASS** | `ResearchCartService` implements Gini CART, pruning, imputation, prediction, and decision paths. |
| Current active model | **PASS** | `2026.09.10-research-cart-v3`; dataset `researcher-provisional-206-v2-r1`; active model run is v2. |
| Training-only model selection | **PASS** | `heldout_used_for_selection = false`; five stratified folds operate inside the training partition. |
| Held-out set retained after selection | **PASS** | 40 held-out records are evaluated after model selection and final training. |
| Approved predictor whitelist | **PASS** | Seven predictors are supplied; identity, demographics, satellite identity, sentiment, labels, and existing predictions are excluded. |
| Human-verified reference labels | **FAIL** | Current dataset status is Provisional; verified count is 0 of 206. |
| Predictive validity for actual engagement | **FAIL / not established** | Labels are researcher-entered provisional assignments and are not evidence-derived human outcomes. |
| CART accuracy above majority baseline | **FAIL** | 52.50% - 55.00% = **-2.50 percentage points**. |
| Failed holdout predictions | **PASS** | 0 failed predictions out of 40. |
| XAI path persistence | **PASS** | Engagement results store decision paths, used features, model version, generated time, and explanation metadata. |
| XAI calibration claim | **FAIL if claimed** | Leaf confidence is a training-class proportion, not a calibrated probability. |

## 4. Engagement reference distribution and computations

### Table 9. Current provisional reference-label distribution

| Engagement reference label | Frequency | Computation | Percentage |
|---|---:|---:|---:|
| Active | 112 | 112 / 206 x 100 | 54.37% |
| Moderately Active | 63 | 63 / 206 x 100 | 30.58% |
| Possible Need for Follow-Up | 25 | 25 / 206 x 100 | 12.14% |
| Needs Clarification / Excluded | 6 | 6 / 206 x 100 | 2.91% |
| **Total** | **206** | **112 + 63 + 25 + 6** | **100.00%** |

Only the first three classes are eligible CART targets. Therefore:

- Eligible target records: $112 + 63 + 25 = 200$.
- Training records: $200 x 0.80 = 160$.
- Held-out records: $200 x 0.20 = 40$.
- Training class counts: Active 90, Moderately Active 50, Possible Need for Follow-Up 20.
- Held-out class counts: Active 22, Moderately Active 13, Possible Need for Follow-Up 5.

### Current model-selection values

| Item | Value |
|---|---|
| Model | `2026.09.10-research-cart-v3` |
| Dataset | `researcher-provisional-206-v2-r1` |
| Seed | 42 |
| Cross-validation folds | 5 stratified training-only folds |
| Selected max depth | 3 |
| Selected minimum samples split | 10 |
| Selected minimum samples leaf | 3 |
| Selected class weight | None |
| Selected CCP alpha | 0.0072916666666666755 |
| CV accuracy | 58.125% |
| CV macro F1 | 28.653% |
| Training accuracy | 58.750% |
| Training macro F1 | 29.352% |

### Table 10/11. Held-out CART performance

| Metric | Value |
|---|---:|
| Held-out sample count | 40 |
| Correct predictions | 21 |
| Failed predictions | 0 |
| Accuracy | 52.50% |
| Macro precision | 26.85% |
| Macro recall | 32.87% |
| Macro F1 | 26.91% |
| Weighted precision | 38.68% |
| Weighted recall | 52.50% |
| Weighted F1 | 41.75% |
| Majority-class baseline | 55.00% |
| CART minus baseline | **-2.50 percentage points** |

### Confusion matrix

| Actual / predicted | Active | Moderately Active | Possible Need for Follow-Up |
|---|---:|---:|---:|
| Active | 20 | 2 | 0 |
| Moderately Active | 12 | 1 | 0 |
| Possible Need for Follow-Up | 4 | 1 | 0 |
| **Total** | **36** | **4** | **0** |

### Per-class metrics

| Class | Precision | Recall | F1 | Support |
|---|---:|---:|---:|---:|
| Active | 55.56% | 90.91% | 68.97% | 22 |
| Moderately Active | 25.00% | 7.69% | 11.76% | 13 |
| Possible Need for Follow-Up | 0.00% | 0.00% | 0.00% | 5 |

The model predicted no held-out record as Possible Need for Follow-Up. This is a material limitation and must be discussed explicitly.

### Feature-audit evidence

| Predictor | Missing | Missing % | Observed values/quality |
|---|---:|---:|---|
| Recent completed follow-ups in 3-month window | 0/200 | 0.00% | Constant zero for all 200; no recorded completed member follow-up in the frozen window. |
| Months since refresh | 199/200 | 99.50% | One observed value, 3; excessive missingness and effectively constant among observed values. |
| Active disciples | 5/200 | 2.50% | 169 of 195 observed values are zero; zero-heavy evidence. |
| Weekly D-Groups led | 198/200 | 99.00% | Only two observed values: 0 and 2. |
| D-Group Leader role | 0/200 | 0.00% | 174 nonleaders and 26 leaders in the eligible frozen records; role is only a limited engagement proxy. |
| Years since joined | 198/200 | 99.00% | Only two observed values, both zero. |
| Ministry involvement recorded | 198/200 | 99.00% | Only two observed values, one zero and one one. |

This feature audit shows that the model has limited evidence coverage. The existence of a computed metric does not establish construct validity or operational readiness.

## 5. Sentiment-analysis evidence

### Engine implementation

The implemented engine is `App\Services\SentimentAnalysisService`, model name **Explainable Multilingual Sentiment Lexicon**, version `2026.08.29-feedback-v1`. It is a multilingual lexicon/rule-based method using weighted English and Filipino cues, negation handling, net-score thresholds, and heuristic confidence. It is not a transformer, BERT, XLM-R, generative AI system, or locally trained classifier.

### Live feedback counts

| Evidence | Count |
|---|---:|
| Stored feedback submissions | 5 |
| Granted consent | 3 |
| Denied consent | 2 |
| Unknown consent | 0 |
| Stored Positive system predictions | 5 |
| Stored legacy Positive human labels | 2 |
| Stored analysis units | 11 |
| Final human sentiment references | 2 |
| Strict eligible research candidates | 0 |
| Strict included evaluation records | 0 |
| Sentiment evaluation runs | 0 |

The two legacy Positive human labels are retained but do not establish a complete independent research cohort. They have no completed strict language/scope/independence evidence in the current eligibility service.

### Strict sentiment evaluation result

| Table/result | Value | Status |
|---|---:|---|
| Table 11 eligible human-label total | 0 | **UNTESTED** |
| Table 12 eligible quote-approved examples | 0 | **UNTESTED** |
| Table 18 confusion-matrix N | 0 | **UNTESTED** |
| Table 19 accuracy | Not computed | **UNTESTED** |
| Table 19 macro precision/recall/F1 | Not computed | **UNTESTED** |
| Table 20 English N | 0 | **UNTESTED** |
| Table 20 Filipino N | 0 | **UNTESTED** |
| Table 20 Filipino-English N | 0 | **UNTESTED** |
| Table consistency validations | True | **PASS as empty-set arithmetic only** |
| Evaluation readiness | False | **FAIL** |

Do not write “0% sentiment accuracy.” The correct statement is: **No eligible evaluation cohort is available; sentiment performance is untested.**

### Why the sentiment cohort is currently empty

The current strict evidence service requires documented Granted consent, meaningful text, finalized System Feedback scope, non-test status, a valid approved period, human-reviewed language, final human sentiment, reviewer provenance, and documented independent/blinded review. The live analysis units do not satisfy the complete set. The current approved period is 3 September 2026 through 10 September 2026, but the evidence service reports that the approval reviewer is not validly present in the current user inventory, so the period is not fully documented for the strict thesis cohort.

Denied-consent submissions remain stored but are excluded. This is correct and must not be bypassed.

## 6. System-evaluation survey evidence

### Instrument actually implemented

The feedback form stores five required open-ended text responses:

1. Ease of using the developed system.
2. Helpfulness of member, seeker, and follow-up workflows.
3. Clarity of engagement classification and explanation results.
4. Problems, confusion, or missing features.
5. Overall satisfaction.

The current form does not store numeric 1–5 Likert responses.

### Current survey inventory

| Evidence | Count |
|---|---:|
| Feedback submissions | 5 |
| Distinct respondents | 3 |
| Granted-consent submissions | 3 |
| Denied-consent submissions | 2 |
| Numeric Likert responses | 0 |
| Eligible Likert N | 0 |
| Human thematic codes | 0 |
| Potential non-parent consented leaf units | 8 |
| Split parents excluded | 1 |
| Denied/unconsented leaf units excluded | 2 |
| Consented leaf units pending/excluded review | 8 |

### Table 22 status

| Category | Eligible N | Weighted mean | Interpretation | Result |
|---|---:|---:|---|---|
| Ease of Use | 0 | Not computed | UNTESTED | INSUFFICIENT DATA |
| Usefulness | 0 | Not computed | UNTESTED | INSUFFICIENT DATA |
| Accessibility | 0 | Not computed | UNTESTED | INSUFFICIENT DATA |
| Reliability | 0 | Not computed | UNTESTED | INSUFFICIENT DATA |
| Overall Satisfaction | 0 | Not computed | UNTESTED | INSUFFICIENT DATA |

The requested formula, if actual numeric responses are later collected, is:

$$\text{Weighted mean} = \frac{\sum (\text{rating} \times \text{frequency})}{\sum \text{frequency}}$$

No ratings may be inferred from words, sentiment labels, sentiment confidence, or textual numbers.

## 7. Recommendation, audit, export, and permissions evidence

### Recommendations

- Recommendation rows: 0.
- Recommendation catalogue actions: 0.
- Recommendation outcome frequencies: none.
- Correct Chapter 3 status: **UNTESTED operationally**. The module exists in code, but no live recommendation case is available for a results table.

### Audits

| Audit area | Stored audit events |
|---|---:|
| Engagement research | 9 rows: dataset creation, superseding, split, train, and evaluate events |
| Sentiment research | 15 rows: feedback-unit creation, consent metadata correction, period approval, existing-label recognition, and split event |
| General audit log page | Not implemented |

### Role permissions

- Admin research endpoints require authenticated `admin` role and service authorization.
- Satellite routes require authenticated satellite/district access matching the satellite slug.
- Manage actions additionally require a satellite management role and matching district assignment.
- Non-admin users are blocked from engagement research and sentiment research administration by tests and middleware/service authorization.
- Feedback submission is available to authenticated users; consent changes are limited to the respondent or an authorized administrator with evidence.

### Exports

The code implements CSV exports for users, satellites, members, seekers, reports, and recommendation actions. It implements XLSX exports for engagement and sentiment evaluation. The current live database contains no completed sentiment evaluation run and no recommendation rows to export as findings.

## 8. Thesis-ready discussion paragraphs

### 8.1 Database and system implementation

The live SQLite database was inspected using read-only queries and returned an `ok` integrity result. It contained 206 member records, 522 seeker records, six satellite records, seven follow-up records, five system-feedback submissions, and 206 stored engagement results. The member data included 28 active DGroup Leaders and 149 active DGroup Members. Because the implementation represents DGroups through active leader member records rather than a separate DGroup table, the dashboard DGroup total is derived from the 28 active leader records. This relationship is an implementation rule and should not be described as an independently measured count of a separate DGroup entity.

### 8.2 Engagement reference data and CART evaluation

The current engagement reference dataset contained 206 researcher-entered provisional records: 112 Active, 63 Moderately Active, 25 Possible Need for Follow-Up, and six Needs Clarification. The six clarification records were excluded from CART targets, leaving 200 eligible records. A stratified 80/20 split produced 160 training records and 40 held-out records. The selected PHP CART model used a maximum depth of 3, minimum split size of 10, minimum leaf size of 3, no class weighting, and a cost-complexity pruning alpha of 0.0072916666666666755. The held-out accuracy was 52.50%, while the majority-class baseline was 55.00%, producing a difference of -2.50 percentage points. Thus, the model did not outperform the baseline in the current provisional evaluation.

### 8.3 Limitations of the engagement results

The engagement result is a workflow evaluation and not independent validation of actual member engagement. All current reference records are provisional and researcher-entered, with zero human-verified records. The feature audit also showed substantial missingness: months since refresh, weekly D-Groups led, years since joined, and ministry involvement were each 99% missing, while recent completed member follow-ups were constant at zero in the frozen observation window. The model predicted no held-out record as Possible Need for Follow-Up. These findings require the model to remain marked MODEL REVIEW REQUIRED and prevent a claim that the CART system is ready for unsupervised ministry decisions.

### 8.4 Sentiment-analysis findings

The deployed sentiment component is an Explainable Multilingual Sentiment Lexicon using fixed lexical and rule-based processing. It is not a transformer or generative AI model. The database contained five feedback submissions, of which three had Granted consent and two had Denied consent. The strict thesis eligibility service found zero records meeting all requirements for consent, scope, date, language, human reference, reviewer provenance, and independent review. Therefore, sentiment accuracy, precision, recall, F1, confusion-matrix counts, and language-specific performance are UNTESTED. Denied-consent records were correctly excluded and must not be included to increase the sample.

### 8.5 System evaluation instrument

The current system-evaluation form stores five open-ended responses rather than numeric Likert ratings. Although the live database contains five submissions from three distinct respondents, it contains zero numeric ratings and no approved numeric item-to-category mapping. Weighted means and interpretations for Ease of Use, Usefulness, Accessibility, Reliability, and Overall Satisfaction are therefore UNTESTED. The open-ended responses may support a future qualitative coding study only after documented consent, scope review, human thematic coding, coder provenance, and adjudication are completed.

## 9. Exact screenshots to capture

Use de-identified records and do not display passwords, OTPs, email addresses, phone numbers, raw consent notes, or identifying feedback text in thesis screenshots.

| Figure | Route/page | Capture exactly |
|---:|---|---|
| 1 | `/login` | Login email step; do not show credentials. |
| 2 | `/admin/dashboard` | Four cards, active DGroup Leader/DGroup totals, and MAIN/NCP/ICP breakdown. |
| 3 | `/admin/satellites` | Satellite list, hub values, leader/member/seeker counters, and CSV export. |
| 4 | `/admin/members` | Member breakdown cards and satellite rows. |
| 5 | `/admin/seekers` | Seeker table, Pending status, district controls, and synchronization/export controls. |
| 6 | `/satellites/pasig/members` | Member table filtered to Dgroup Leader; hide personal identifiers. |
| 7 | `/satellites/pasig/seekers` | DGroup Leader assignment selector and seeker status controls. |
| 8 | `/satellites/pasig/follow-ups` | Follow-up status/method filters, sentiment summary, and trend panel. |
| 9 | `/satellites/pasig/engagement` | Classification cards and member result list. |
| 10 | `/satellites/pasig/engagement/{member}` | Classification, model/version, factors used, human-review notice, and expanded XAI path. |
| 11 | `/satellites/pasig/recommendations` | Use only if a real recommendation exists; otherwise capture the empty state and label it. |
| 12 | `/feedback/create` | Five open-ended questions and consent checkbox; do not show response text. |
| 13 | `/feedback` | Own feedback table with consent status and sentiment explanation; redact text. |
| 14 | `/admin/analytics/engagement/review` | Provisional reference status, 206 records, zero verified, and review controls. |
| 15 | `/admin/analytics/engagement/dataset` | 112/63/25/6 distribution, 160/40 split, model parameters, metrics, and MODEL REVIEW REQUIRED notice. |
| 16 | `/admin/analytics/engagement/records/{reference}` | Recorded evidence snapshot, human verification form, XAI path, and audit history. |
| 17 | `/admin/analytics/sentiment` | Engine notice, consent/inclusion counts, warnings, and UNTESTED state. |
| 18 | `/admin/analytics/sentiment/research/review` | Feedback unit list showing consent, scope, language, human label, inclusion, and prediction visibility. |
| 19 | `/admin/analytics/sentiment/research/units/{unit}` | Consent evidence and blind human-review form; redact original feedback. |
| 20 | `/admin/analytics/sentiment/research/dataset` | Empty/UNTESTED Table 11, 18, 19, and 20 state. |
| 21 | `/admin/reports` | Current No reports/zero placeholder state, clearly labelled as incomplete. |
| 22 | Any authenticated page | Sidebar roles and Sign Out control. |

## 10. Remaining legitimate data to collect

### Required before claiming validated engagement prediction

1. Actual engagement reference outcomes reviewed by authorized human reviewers.
2. Evidence and reasons for every reference decision.
3. Reviewers, timestamps, and verification provenance.
4. Reliable member activity history within a defined observation window.
5. Completed member follow-ups, attendance/activity records, and other approved indicators with consistent coverage.
6. A new versioned dataset after the evidence is collected.
7. Recomputed CART training, cross-validation, holdout metrics, confusion matrix, and XAI outputs.
8. External or temporally separated validation if the thesis claims generalization.

### Required before reporting sentiment performance

1. Actual documented Granted consent for included submissions.
2. A valid approved evaluation period with a valid authorized approver.
3. Human scope and non-test review for each candidate unit.
4. Human-reviewed language group: English, Filipino, or Filipino-English.
5. Independent final human sentiment labels: Positive, Neutral, or Negative.
6. Reviewer identity, timestamp, independence attestation, and blinded/re-review status.
7. A current fixed-engine prediction for every included unit.
8. Quote approval and manual de-identification for any example quotation.
9. Sufficient records to support each reported language/class comparison.

### Required before reporting numeric system-evaluation results

1. The approved numeric questionnaire or original instrument.
2. Actual numeric responses, not numbers inferred from text.
3. The item-to-category mapping for Ease of Use, Usefulness, Accessibility, Reliability, and Overall Satisfaction.
4. Eligibility and missing-response rules.
5. Consent and respondent-count rules.
6. The approved weighting method.
7. Computed distributions, weighted means, rounding, and interpretation intervals.

### Optional qualitative evidence

If the thesis includes thematic analysis of open-ended feedback, collect a human codebook, coded units, coder identifiers, independent coding records, adjudication decisions, and consent-safe quotations. Do not use sentiment labels as thematic codes.

## 11. Recommended Chapter 3 wording corrections

Replace any wording that says:

- “The CART model accurately predicts member engagement” with “The current provisional CART workflow produced a 52.50% held-out accuracy, below the 55.00% majority baseline; model review is required.”
- “The model was validated by human labels” with “The current reference labels are researcher-entered and provisional; human verification count is zero.”
- “Sentiment accuracy was 0%” with “Sentiment performance was untested because the strict eligible cohort size was zero.”
- “The system evaluation achieved Very Satisfactory/Excellent” with “Numeric satisfaction interpretation was untested because the current instrument stores open-ended text and no numeric ratings.”
- “There are 28 DGroup database records” with “There are 28 active DGroup Leader member records, and the current dashboard derives 28 DGroups from the one-active-leader/one-derived-DGroup rule; no standalone DGroup table exists.”
- “NCP and ICP contain the reported live totals” with “The current live database contains six MAIN satellites and no NCP or ICP satellite rows.”

## 12. Evidence files and reproducibility references

- [Engagement reference dataset documentation](engagement-reference-dataset.md)
- [System-feedback research documentation](system-feedback-research.md)
- [Complete user manual](user-manual.md)
- Live database: `database/database.sqlite`
- Historical engagement exports and temporary thesis integrity scripts in `outputs/` were removed during repository cleanup. They are not runtime dependencies. This document records historical findings; regenerate current paper evidence using `php artisan research:thesis-evidence --format=md` or `--format=json`. The thesis page and HTTP exports have been removed. Separately run `php scripts/verify-thesis-evidence.php` when current functional verification is needed; the export command never runs tests or writes research data.
- Engagement evidence service: `app/Services/ThesisSentimentEvidenceService.php` for strict sentiment evidence
- Survey evidence service: `app/Services/ThesisSurveyEvidenceService.php` for read-only survey integrity

## Final thesis status

| Chapter 3 component | Status |
|---|---|
| System architecture and implemented workflow description | **PASS** |
| Live database integrity evidence | **PASS** |
| Member/DGroup operational counts | **PASS with implementation caveat** |
| Engagement reference distribution arithmetic | **PASS** |
| CART reproducibility and partition checks | **PASS** |
| CART performance above baseline | **FAIL** |
| Human-verified engagement reference dataset | **FAIL / not complete** |
| XAI storage and decision-path explanation | **PASS** |
| Recommendation outcome findings | **UNTESTED** |
| Sentiment consent exclusion rule | **PASS** |
| Sentiment evaluation readiness | **FAIL** |
| Sentiment accuracy/precision/recall/F1 | **UNTESTED** |
| Numeric system-evaluation means | **UNTESTED** |
| General report activity | **FAIL / placeholder** |
| Thesis-ready conclusion of deployment readiness | **FAIL** |

The evidence supports a transparent description of the implemented system and a provisional CART workflow. It does not support claims of validated predictive performance, completed sentiment evaluation, numeric satisfaction ratings, or deployment-ready autonomous decision-making.
