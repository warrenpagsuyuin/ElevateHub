# System-feedback sentiment research

The entry point is Admin → Analytics → Sentiment Analysis (`/admin/analytics/sentiment`). Human Feedback Review and Evaluation Dataset share one eligibility service. Run `php artisan sentiment:prepare-evaluation --export` to import new existing feedback and export the available tables. This command cannot create research consent, human labels, language labels, or an approved period.

## Initial audit, 2026-09-10

The existing table is `feedback`. Its original columns were: id, member_id, event_id, message, sentiment, created_at, updated_at, evaluator_user_id, question_1, question_2, question_3, question_4, question_5, feedback_payload, sentiment_score, sentiment_confidence, sentiment_model, sentiment_model_version, sentiment_metrics, sentiment_analyzed_at, human_sentiment_label, human_labeled_by_id, human_labeled_at.

There were two stored records with actual textual system feedback, submitted September 3 and September 6, 2026. Both had Positive predictions and separately stored Positive legacy human labels with reviewer fields. Consent was absent. Human language grouping, scope/eligibility decisions, approved evaluation dates and research partitions were absent. Lexical language cues in model metadata are not reviewed language groups. The old analytics page calculated metrics directly from legacy labels and predictions, without research eligibility checks, and exposed predictions alongside initial labeling controls. Consequently, those historical labels are retained but not automatically treated as independent research references.

The actual engine is `App\Services\SentimentAnalysisService`: **Explainable Multilingual Sentiment Lexicon**, version `2026.08.29-feedback-v1`. It matches weighted positive/negative English and Filipino cues, skips some negated positive cues, uses a net evidence threshold of 0.75, and reports heuristic confidence. It does not execute BERT, XLM-R, a transformer, local classifier training, or an external inference API. The lexicon includes follow-up-oriented terms as well as system-feedback terms; this is a model/domain limitation, not evidence of a locally trained multilingual classifier. The lexical rules were not changed for this evaluation task.

## Storage and consent

Before the additive migration, SQLite `VACUUM INTO` created `storage/app/private/backups/before-sentiment-research-20260910.sqlite`; integrity was checked. Existing feedback and engagement data are preserved. Apply only `database/migrations/2026_09_10_000001_add_system_feedback_research.php`; no live reset is needed. The repository's undated `create_feedback_table.php` runs after dated migrations on fresh installs, so it also declares the consent columns. This preserves both fresh-install and existing-database migration paths.

Consent fields added to `feedback`: research_consent_status (Unknown/Granted/Denied), research_consented_at, research_consent_recorded_by and research_consent_evidence. The existing form now offers optional unchecked research consent; refusing it does not prevent ordinary feedback submission. Respondents can grant or withdraw consent through their own My Feedback page. Admins can record an actual externally documented decision with evidence and attestation; this is not authorization to manufacture consent. Legacy records start Unknown.

`feedback_analysis_units` stores research-safe F001-style codes, exact text, parent feedback/unit IDs, normalized text hashes, scope, reviewed language, separately stored automatic language signal, independent human reference/provenance, model prediction/provenance, inclusion reasons, partition and an optimistic review revision. One complete response is the default unit. The original source feedback and legacy labels are not overwritten. A reviewer can split an unfinalized response into separate verbatim spans that preserve all wording in order; the parent remains stored and excluded. Children require separate reviews. Correlated units from the same respondent are not independent respondents; distinguish analysis-unit counts from response counts in the thesis.

`sentiment_research_settings` stores actual approved dates and approval evidence. No dates are inferred from submission timestamps. `sentiment_research_audits` retains consent, scope, language/reference and split decisions. `sentiment_evaluation_runs` retains completed dataset/report snapshots with a signature. Historical runs are not presented as current after consent or eligibility changes; current exports recompute eligibility.

## Human review

Only admins can administer research review/evaluation. Initial research pages do not render system predictions, confidence, automatic language signals or legacy sentiment labels. A reviewer must attest independence and choose Positive/Neutral/Negative and English/Filipino/Filipino-English. The source respondent and the known legacy labeler cannot submit the initial independent research review for that same feedback, because those workflows already exposed predictions. Use another authorized reviewer who has not consulted the prediction. Corrections to finalized reviews require a reason and retain before/after history; they must reflect evidence, not an attempt to increase accuracy.

Scope must explicitly confirm meaningful system feedback, not a developer/test comment. Exclusions require reasons. Text, documented consent, scope and approved submission dates determine inclusion; prediction correctness never affects it. Exact duplicates use normalized whitespace/case hashes and retain the first otherwise eligible unit by code. Pending and Needs Clarification outcomes cannot enter metrics. No automatic human sentiment or language labels are generated.

## Evaluation and tables

This fixed engine is evaluated directly against stored human references (blinding reported separately); no training split is invented. All three sentiment classes remain in macro averages. For a nonempty evaluation set, zero-denominator precision/recall/F1 is zero, with explicit missing-support/no-prediction flags. For n=0, metrics are null and displayed UNTESTED. Language groups use the same rules and show a descriptive caution for n<30; this is not a validated sample-size threshold.

Table 11 counts included finalized human-reviewed units by reviewed language and sentiment. Table 12 chooses the shortest eligible quote-approved original unit per language, breaking ties by code, regardless of prediction correctness. A reviewer must confirm that quoted text contains no unnecessary identifiers. Table 18 is the confusion matrix. Table 19 computes class, macro and weighted metrics from it. Table 20 groups exactly the same evaluation records by language. The XLSX contains the six requested sheets and omits respondent identity fields. Inline-string cells prevent spreadsheet formula injection. The internal JSON run snapshot stays in private application storage.

Missing/failed predictions remain visible in missing-prediction counts, and prevent saving an Evaluation Ready run. A progress report can show descriptive metrics on the available-prediction subset, explicitly warning that it is incomplete. A completed run requires every included unit to have valid consent, scope, text, dates, a final human reference, language and a current prediction. A missing class prompts the three-class coverage warning; absent languages remain UNTESTED. No artificial feedback, language examples or class balancing is generated.

## Routes

- GET `/admin/analytics/sentiment`: research overview; the prior prediction-visible initial-label UI is retired.
- GET `/admin/analytics/sentiment/research/review` and `/dataset`: review/progress and the five tables.
- GET `/admin/analytics/sentiment/research/units/{unit}`: blind initial review.
- PATCH `/admin/analytics/sentiment/research/units/{unit}`: independent review with revision check.
- POST `/admin/analytics/sentiment/research/units/{unit}/split`: verbatim analysis-unit split.
- POST `/admin/analytics/sentiment/research/period`: approved dates with evidence/attestation.
- POST `/admin/analytics/sentiment/research/sync`: import new stored responses.
- POST `/admin/analytics/sentiment/research/evaluate`: predict final reviewed units and save a complete evaluation.
- GET `/admin/analytics/sentiment/research/export`: current six-sheet XLSX.
- PATCH `/feedback/{feedback}/research-consent`: respondent-owned or documented admin consent decision.

The old reference-label PATCH route now directs admins to the independent review workflow without altering legacy labels. The old analyze POST route uses the eligibility-aware evaluation service. Follow-up sentiment routes remain separate.

## Existing-reference correction ? 2026-09-10

Run `php artisan sentiment:prepare-evaluation --recognize-existing --predict --export` to recognize existing attributed human labels without replacing their values, reviewer IDs or original timestamps. Repeating recognition is idempotent. Missing blinding is reported as Not documented and does not exclude a reference. Parent labels are never copied to split children.

F001 and F002 retain their saved Positive labels as Final / Human Reviewer. F004?F009 currently have no stored child labels or human language reviews, including audit history. F003 remains a split parent; F010/F011 have Denied consent. The user confirmed the approved period as September 3?10. Scope and language review metadata are also incomplete. There are 2 recognized human references, 0 eligible records and 0 evaluated records. Supply the actual missing review records and period approval evidence; do not invent labels, languages or dates.

Prediction eligibility does not require a prior prediction; this avoids preventing the initial fixed-engine run. Table 11 counts eligible references, while the total human-labeled counter includes stored Final references even when other eligibility conditions are missing.
