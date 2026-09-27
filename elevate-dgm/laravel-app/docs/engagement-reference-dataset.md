# Provisional engagement reference dataset

## Current correction: 2026-09-10

The active dataset is now `researcher-provisional-206-v2-r1`; model `2026.09.10-research-cart-v3`. The original ID-order allocation in `ReferenceDatasetService::initialize()` has been replaced by `provisionalAssignments()`: proportional satellite quotas with exact global margins, followed by a seed-42 SHA-256 shuffle inside each satellite. IDs serve only as stable shuffle keys; neither their numeric order nor the satellite determines an evidence-based engagement outcome. These assignments are development labels unrelated to measured engagement.

Pasig now has 57 Active, 32 Moderately Active, 13 Possible Need for Follow-Up, and 3 Needs Clarification. MAIN ORTIGAS has 55, 31, 12, and 3 respectively. Global totals remain 112/63/25/6. The v1 records, frozen evidence and model history are retained; the old model is inactive. The pre-correction backup is `storage/app/private/backups/before-provisional-v2-20260910.sqlite`. No new schema migration was needed. The preparation command is unchanged and reuses the current version on repeat runs.

V2 uses five training-only folds across 350 combinations of depth (1–6 or unlimited), split minimum (2/4/6/8/10), leaf minimum (1–5), and class weight (None/balanced). Each configuration generates actual weakest-link pruning paths within CV training folds; their alpha union supplies pruning candidates. This yielded 3,164 evaluated configurations. Balanced weights are fitted per training fold, applied to Gini, split scoring, leaf predictions, and pruning risk. Macro F1 is primary and accuracy secondary. Final training pruning paths, feature audits and every CV candidate are persisted and exported. Holdout information is excluded from all model selection.

Selected depth 3, split minimum 10, leaf minimum 3, class weight None, alpha 0.0072916666666666755. CV accuracy 58.125%, CV macro F1 28.65349%; held-out accuracy 52.50%, macro F1 26.91007%, baseline 55.00%. **MODEL REVIEW REQUIRED**. The before/after figures use different reference assignments and different holdout memberships; they are workflow comparisons, not a controlled estimate of model improvement. No further selection was performed after viewing the v2 holdout.

The remaining sections document the original v1 implementation and historical results. Their v1 assignment, grid and counts by satellite are superseded by the correction above. Historical exports in the workspace-root `outputs/` folder were removed during repository cleanup because they are not runtime dependencies. Generate current evaluation evidence through the admin engagement workflow; historical exports are no longer bundled with the application.

The admin workflow is at `/admin/analytics/engagement`. Navigate through **Admin → Analytics → Engagement Classification**, then **Human Engagement Review** or **Evaluation Dataset**. Only the existing `admin` role can read or administer these endpoints. Satellite accounts and member accounts cannot access reference administration.

The initial dataset is explicitly **Researcher Entered / Provisional**. It assigns the existing 206 members in `members.id ASC` order: 112 Active, 63 Moderately Active, 25 Possible Need for Follow-Up, and six Needs Clarification. These are researcher-specified allocations, not labels inferred from evidence and not independently human-verified findings. Predictions remain in `engagement_results`; references remain in `engagement_reference_labels`.

## Preparation and recovery

The live SQLite database was backed up with SQLite `VACUUM INTO` before the additive migration. The snapshot is `storage/app/private/backups/before-provisional-reference-20260909.sqlite`. Its integrity check passed; it contains 206 members and zero reference labels. Do not reset the database or run `migrate:fresh` against application data.

Apply only the new migration when other pending work is unrelated:

```text
php artisan migrate --path=database/migrations/2026_09_09_000001_add_provisional_engagement_research.php --force
php artisan engagement:prepare-reference --train --export
```

Initialization refuses a cohort other than 206 unique existing members and refuses to overwrite preexisting labels. Repeating it after successful initialization preserves verified decisions and audit history. Repeated training of the same unchanged dataset reuses the recorded model run.

`--export` writes `storage/app/private/engagement-research/evaluation.xlsx` and `report.json`. The admin export route always computes the current summary from the database. The XLSX has Reference Label Summary, Dataset Split, Performance, Reference Records, Satellite Distribution, and CV Search sheets. Text is serialized as inline string cells, so researcher notes cannot become executable spreadsheet formulas.

## Reference and verification lifecycle

The original `label`, `source`, and `notes` columns remain compatible. Added canonical columns include `reference_label`, `review_status`, `reference_source`, `verification_status`, `reason`, reviewer/timestamp, observation window, dataset version, partition, a frozen feature snapshot, and an optimistic review revision. Clarification rows have a **null canonical reference_label**, review status Needs Clarification, and partition Excluded.

Each initial record is Provisional with no reviewer and no review timestamp. “Final” review status means the label is an eligible modeling target; it does not mean human verification.

An authenticated admin must enter actual evidence and attest to reviewing it before a record becomes Human Verified / Verified. Changes record both previous and new states in `engagement_research_audits`. Stale review forms are rejected. Each verification increments the dataset revision, recalculates the split, invalidates the active model, and retains previous model and reference audit history. The updated revision needs a new evaluation.

“Human Verified Final” is guarded on the server: every cohort record must have verified source/status, a reviewer, timestamp, and reason. Verified clarification outcomes may remain excluded. A subsequent correction reopens the dataset as Provisional. Existing model runs keep their original evaluation status; evaluate again after finalization.

## Reproducible evaluation

- Eligible initial cohort: 200; clarification is not a target class.
- Holdout allocation: 20% by largest remainder with each class retained in both partitions. Seed 42 controls SHA-256 ordering of member IDs within class. This is reproducible but is not scikit-learn's random-number sequence.
- Initial training: Active 90, Moderately Active 50, Possible Need for Follow-Up 20; total 160.
- Initial held-out test: Active 22, Moderately Active 13, Possible Need for Follow-Up 5; total 40.
- Predictors: recent recorded completed follow-ups, months since refresh, active disciples, weekly D-groups led, D-group leader role, years since joining, and recorded ministry involvement. IDs are used only for partition bookkeeping and never passed to the tree as predictors. Identity, demographics, source, feedback, sentiment, member status, reference labels, and prior predictions are excluded.
- Observation dates and approved numeric features are frozen on preparation. Missing/invalid/future-dated indicators remain null. Empty ministry information is missing; explicit None/No/N/A/Not involved is zero. A zero follow-up count means no completed follow-up records in the observation window, not proof of no engagement.
- Numeric medians are fitted separately within each CV training fold, then on the final training partition. Entirely missing training columns use zero. No held-out values fit imputation.
- CART uses Gini splits and minimizes the subtree cost `R(T) + ccp_alpha × leaf_count` through bottom-up pruning of the grown tree. See the [decision-tree pruning definition](https://scikit-learn.org/stable/modules/tree.html#minimal-cost-complexity-pruning).
- Five stratified training-only folds select mean macro F1 over 36 combinations: max_depth 1/3/5; min_samples_split 2/8; min_samples_leaf 1/4; ccp_alpha 0/0.005/0.02. Ties prefer fewer leaves, lower depth, larger leaf/split minimums, then larger alpha. If fewer than two folds are possible, fixed documented defaults are used without test tuning.
- Held-out predictions are generated after all selection and training. This follows the [separation of training preprocessing from test data](https://scikit-learn.org/stable/common_pitfalls.html#data-leakage).
- Accuracy includes failed predictions in its denominator. The confusion matrix retains all three target classes; failures are also reported separately. Zero-denominator precision, recall, and F1 return zero. Majority baseline is learned from training counts.
- XAI traverses the saved tree, showing the recorded value, any training-fitted replacement, threshold, branch, node IDs, and final leaf. Leaf confidence is a training-class proportion, not a calibrated probability.

## Initial measured results, 2026-09-09

Dataset `researcher-provisional-206-v1-r1`; model `2026.09.09-research-cart-v2`.

Selected parameters: max_depth 1, min_samples_split 8, min_samples_leaf 4, ccp_alpha 0.005.

Training accuracy 57.50%; training macro F1 32.74%. Mean CV accuracy 53.75%; mean CV macro F1 27.65%. Held-out accuracy **47.50%**, versus majority baseline **55.00%**. There were zero failed classifications. The model did not beat the baseline. These scores assess researcher-assigned provisional labels, not agreement with independent human judgments.

| Actual / predicted | Active | Moderately Active | Possible Need for Follow-Up |
|---|---:|---:|---:|
| Active | 18 | 4 | 0 |
| Moderately Active | 12 | 1 | 0 |
| Possible Need for Follow-Up | 5 | 0 | 0 |

## Implementation file inventory

Added:

- `app/Console/Commands/PrepareEngagementReference.php`
- `app/Http/Controllers/EngagementResearchController.php`
- `app/Services/ResearchCartService.php`
- `app/Services/ReferenceDatasetService.php`
- `app/Services/EngagementEvaluationService.php`
- `app/Services/EngagementEvaluationExportService.php`
- `app/Models/EngagementReferenceDataset.php`
- `app/Models/EngagementResearchAudit.php`
- `database/migrations/2026_09_09_000001_add_provisional_engagement_research.php`
- `resources/views/admin/engagement-research/_styles.blade.php`
- `resources/views/admin/engagement-research/index.blade.php`
- `resources/views/admin/engagement-research/show.blade.php`
- `tests/Unit/ResearchCartServiceTest.php`
- `tests/Feature/EngagementResearchTest.php`
- `docs/engagement-reference-dataset.md`

Updated:

- `app/Models/EngagementReferenceLabel.php`
- `app/Models/EngagementModelRun.php`
- `app/Services/EngagementClassificationService.php`
- `app/Http/Controllers/RecommendationController.php`
- `resources/views/satellites/engagement/show.blade.php`
- `resources/views/admin/analytics.blade.php`
- `routes/web.php`
- `tests/Feature/AdminDashboardRoutesTest.php`

## Route inventory

All routes use authentication and `role:admin`, plus controller/service authorization:

| Method | Path after `/admin/analytics/engagement` | Purpose |
|---|---|---|
| GET | `/` or `/review` | Reference counts and paginated review table |
| GET | `/dataset` | Split, model selection, and performance |
| GET | `/records/{reference}` | Evidence, actual XAI, and audit history |
| PATCH | `/records/{reference}/verify` | Attested human verification |
| POST | `/train` | Train/evaluate current revision |
| POST | `/finalize` | Guarded final verification flag |
| GET | `/export` | Current XLSX dataset and evaluation export |

Audit events: reference_dataset.created, reference_label.updated, reference_label.verified, dataset.split, cart.trained, cart.evaluated, reference_dataset.finalized, recommendation.approved, recommendation.modified, recommendation.rejected. Recommendation review is an explicit authorized action. Training or classification does not contact members, assign disciplinary actions, or change member status.

## Validation and limits

The full suite passed: **102 tests, 913 assertions**. New tests cover exact reference counts, eligibility, split reproducibility and overlap, training-only preprocessing/model selection, pruning, metric formulas and failed predictions, XAI traversal, authorization, attestation, optimistic review revisions, audit retention, finalization, recommendation auditing, and XLSX contents. Browser render checks passed for the admin review/dataset/detail pages at desktop and mobile sizes. The XLSX ZIP package and all XML parts were validated.

Most live records lack weekly D-group counts, ministry information, joining year, and refresh date; no follow-up records existed at preparation. Results therefore reflect substantial missing evidence. The supplied member-ID allocation also concentrates all 105 Pasig records in Active; per-satellite figures are reported as stored, not evidence-derived. The initial dataset remains 206 Provisional and zero Human Verified. Frozen predictor snapshots are not refreshed when member records change; a separately versioned dataset should be prepared for a new observation period. The preexisting pending many-users-per-satellite migration was intentionally not applied.
