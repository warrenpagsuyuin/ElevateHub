# Movement analytics: Phases 1–5

## October 5 overview revision (current dashboard behavior)

The dashboard now presents available evidence through `MovementAnalyticsService::dashboard()` and its internal `MovementOverview` projection. The older strict `report()` contract remains the checkpoint-close/reconciliation authority. The historical phase notes below describe that strict contract; the overview differs as follows.

- Auto selects per metric: closed system/correction evidence, imported or explicitly resolved reports, current live records, then missing. An unresolved conflict is visible rather than automatically resolved by fallback. Reported only excludes live fallback; Live only selects current records for displayed KPIs/rankings. Full data retains explicitly dated historical columns alongside separate displayed-value/source columns; the live-only chart shows live bars alone.
- Current DMembers counts current `Active` member rows, including leaders. DLeaders counts those marked `Dgroup Leader`. These current fields are not proof of historical leader decisions. DGroups uses actual current dated group registrations; incomplete `weekly_dgroups_led` self-reports are not substituted for distinct groups. Campus remains leader-reported only, and ICP remains N/A. No current count writes a snapshot or enters a historical series.
- Partial aggregates sum available child values and show contributing leaf coverage, with missing leaf names. A parent-only report remains visible as a reported assertion with zero supporting leaf coverage; it is never added to its own children. Frozen system/correction parent assertions remain authoritative. Complete child/parent disagreement stays flagged. All-missing values stay null.
- Comparisons include only leaf units with unconflicted reported evidence in both periods. Live records are excluded from historical change. Each change exposes the compared/applicable denominator, and null/zero baselines remain unavailable. Current counts can therefore be populated while historical change remains a dash.
- Group charts retain checkpoint gaps and show a separate dated Live bar. Unit rows show source/coverage and `Live only` status. Quality issues are grouped with conflicts/anomalies first, five initial entries per type, and expandable remaining items. Forecasts still require three historical measurements; readiness lists real units and missing checkpoint counts.
- Pivot and CSV include per-period value/state/source/as-of/quality/coverage plus displayed values and comparison coverage. They share one response. The operational seeker, engagement and follow-up panels retain their existing counts; the rate label now says `Joined-as-member rate`.

### Conflict resolution and safety

Admins can expand the Visayas resolution panel, inspect both alternatives, select one (nothing is preselected), and supply a reason and evidence reference. The authenticated CSRF-protected POST records an append-only row in `movement_conflict_resolutions`, including actor/time/chosen values. Existing source snapshots and original conflict records are preserved. Duplicate resolution is rejected; closed checkpoints must use their audited correction workflow. Resolution evidence participates in checkpoint review fingerprints and cache invalidation. SQLite triggers prevent updating/deleting audit rows.

Install the new table on another checkout with:

```bash
php artisan migrate --path=database/migrations/2026_10_05_100000_create_movement_conflict_resolutions.php
```

### Local sample preview

Set `APP_ENV=local` and `MOVEMENT_ANALYTICS_SAMPLE=true`, then open `/admin/analytics?sample=1` (a preview link appears when enabled). The response generates synthetic counts in memory and shows a persistent SAMPLE DATA banner. Real operational panels, remarks, change logs, conflicts and closing actions are excluded. CSV export is rejected server-side. No sample rows are inserted. The feature is refused outside local, even if the flag is enabled. Real requests remain unchanged when `sample` is absent. The browser renderer's `--sample` flag enables preview generation only inside that local verification process.

### Actual evidence and remaining inputs

As inspected October 5: 206 registered members; 177 currently Active including 28 leaders; Main Ortigas has 89 Active and Pasig 88. Those are 2 of 29 reporting leaves (MAIN: 2 of 8), not complete movement coverage. All six satellites map via configuration; none is unmapped. The other four mapped satellites are Anonas, Eastwood, The Hub and Ubelt. No additional mappings were guessed.

There are no historical snapshots/import batches, no DGroup registrations and no reported Campus totals. The Visayas alternatives remain unresolved in the real database. Supply the actual MAIN/NCP/ICP CSVs to run:

```bash
php artisan analytics:import --main="MAIN Data.csv" --ncp="NCP Data.csv" --icp="ICP Data.csv" --dry-run
```

Review that report before authorizing application with `--actor=<admin-id>` and without `--dry-run`. Blank/null, N/A and conflict rules remain unchanged. No import or resolution was applied as part of the overview redesign. September remains open.

Verification includes current-only fallback, partial coverage, like-for-like cohorts, zero/missing baselines, import priority, N/A, unchanged snapshots, sample restrictions, resolution authorization/CSRF and CSV equality. Browser checks now use `php tests/Browser/render-movement.php --sample` before `node tests/Browser/movement-analytics.mjs`. Private screenshots include `overview-before.png`, `after-desktop.png`, `after-tablet.png` and `sample-preview.png` under `storage/framework/testing/movement-ui/`.

Verification results: full regression run passed 245 tests / 3,694 assertions; subsequent focused checks passed after the final presentation refinements, including all 10 overview tests / 61 assertions. Fixtures reproduce the December/June totals in the overview while correctly leaving supporting leaf coverage unknown. Production build passed. Automated desktop/tablet, keyboard, expanded conflict-form accessibility, sample isolation and checkpoint workflow browser checks passed. Current local HTTP timings were 68 ms for the page, 334 ms for uncached aggregate JSON (23 queries), and 30 ms for cached JSON (2 queries). Original-data hashes matched across all 11 protected tables; the real database still has zero snapshots, imports and conflict resolutions.

Remaining limitations: current membership depends on current status/role accuracy; no historical membership is inferred. Parent-only sheet totals cannot establish unit-level coverage or like-for-like changes. Historical CSVs and Campus/DGroup evidence are still required for those numbers and for quarter close. The existing SQLite-only audit guard portability and local cache deployment limits still apply.

The movement dashboard uses the shared analytics service and includes a reviewed quarter-close workflow. Existing member/seeker rows remain unchanged. No historical count is seeded, and no checkpoint is automatically closed because its date has passed. September 2026 still needs verified evidence before its first real close.

## Confirmed definitions

- DMembers includes DGroup leaders.
- Placed means a new member joined, not just a district/leader assignment.
- New Leader eligibility requires at least three members under that person; reactivations do not count as new leaders. Future calculations must use dated group memberships/qualification evidence, not infer historical eligibility from current role strings.
- Campus is leader-reported totals only. It is not computed from school names. ICP Campus is not applicable.
- Active status is a leader decision recorded in `member_status_history`, never a model prediction or activity rule. The existing member status column is legacy data and is not overwritten or automatically accepted as a verified decision.

All definitions, organization keys, checkpoint dates, mapping aliases and quality thresholds live in `config/movement_analytics.php`. The owner confirmed that Stable initially means exactly 0% change. No threshold is fitted to the supplied examples.

## Setup

```bash
php artisan migrate --path=database/migrations/2026_10_02_100000_create_movement_analytics_foundation.php
php artisan analytics:setup --dry-run
php artisan analytics:setup
```

Setup seeds Elevate, the MAIN/NCP/ICP groups, their units/sub-units, and five open checkpoints (Dec 2025; Mar/Jun/Sep/Dec 2026). It maps existing satellites only by the configured slug-to-unit mapping and lists unmapped satellites. It does not create placeholder satellites or counts. Setup is repeatable and never reopens checkpoints. Existing checkpoint definitions or satellite mappings cannot silently change on rerun.

New tables: `org_units`, `org_unit_satellite_mappings`, `checkpoints`, `unit_snapshots`, `analytics_imports`, `analytics_import_rows`, `analytics_data_issues`, `member_status_history`, `analytics_dgroups`, `analytics_dgroup_memberships`, `analytics_member_assignments`, `analytics_leadership_events`, `analytics_seeker_placements`.

The additional dated registries provide future live-count evidence. They start empty: existing names, creation timestamps and current statuses are not invented historical group memberships or placements. Legacy member/seeker IDs are referenced by value without changing those tables or installing cascading deletion behavior.

## CSV import

Export each source tab separately as UTF-8 CSV. Preserve the original files for provenance. One invocation should include all available tabs so parent totals can be reconciled across groups.

```bash
php artisan analytics:import --main="MAIN Data.csv" --ncp="NCP Data.csv" --icp="ICP Data.csv" --dry-run
php artisan analytics:import --main="MAIN Data.csv" --ncp="NCP Data.csv" --icp="ICP Data.csv" --actor=ADMIN_USER_ID
```

`--actor` must identify an existing admin for a real import; dry runs do not need an actor and execute only SELECT queries. The source CSVs have not yet been supplied, so their actual header layout still needs validation. Unsupported layouts fail rather than guessing at columns or skipping data.

Supported layouts:

1. Long form: `unit,checkpoint,dleaders,dgroups,dmembers,campus,new_leaders,reported_at,remarks`. One row per unit/checkpoint. `reported_at` is optional `YYYY-MM-DD`; missing reporting dates stay null.
2. Flattened wide form: `unit,Dec 2025 DLeaders,Dec 2025 DGroups,Dec 2025 DMembers,...,Jun 2026 DLeaders,...`. Missing metric columns stay not reported. Period keys such as `2025-12` also work.
3. Two-row wide headers: checkpoint labels in the top row and metric names beneath them. A merged checkpoint heading may have blank cells to its right. An optional first banner containing only the tab name is accepted.

Use a unit name when unambiguous or its configured key (for example `main.pasig`, `ncp.mindanao.ne`). `Total` means the current tab's group total; `Elevate` explicitly means the organization total. Checkpoints accept their configured key, label or as-of date in long form. Metric aliases include DL/DG/DM. Grouped counts such as `"2,751"` must be CSV-quoted.

Cell meanings are preserved:

| Cell | Stored count | Metric state |
|---|---|---|
| Blank | null | not_reported |
| `0` | 0 | reported |
| `-` | null | not_applicable |
| Conflicting submissions | null | conflict |

ICP Campus is not applicable even if its cell is blank. Numeric ICP Campus, including zero, is rejected. Negative/fractional counts, invalid dates, unknown units/checkpoints, malformed rows and duplicate/ambiguous headers reject the entire batch.

Identical tab content plus configuration is idempotent, including renamed files. Changed values are retained as separate original import rows and flagged for explicit resolution, never silently replacing a previously reported value. Original rows and import manifests are append-only. File hashes, tab, line, actor and import time provide provenance.

Parent reconciliation compares reported parents with immediate children (never parent plus child double-counting). It skips non-applicable Campus children. Missing/conflicted children produce incomplete-coverage issues; complete children with different totals produce explicit mismatch issues. Neither result is silently corrected.

Exit codes: `0` clean/no-change, `1` validation failure with no import, `2` valid data imported (or previewed) with unresolved quality issues. Review output before automating retries.

The known Visayas Dec 2025 conflict (68/68/358 versus 106/106/472) is stored as an unresolved issue even before source files arrive. These owner-supplied alternatives are evidence of disagreement, not seeded counts. Importing either or both keeps the affected official values null/conflicted. Phase 3 will display these issues in the Data Quality panel.

## Leader status history

Authenticated `POST /analytics/members/{member}/status` accepts `status`, `effective_at`, `reason`, and `expected_version` (0 for the first decision). It requires the leader role and access to the member's satellite. Admin/staff/member roles cannot set Active. The web middleware supplies CSRF protection. The first old status is null because an existing default is not leader attestation. Subsequent records retain old/new status, actor, effective time, recorded time and monotonically increasing version. Stale submissions and reverse/future effective times are rejected.

The endpoint appends to the new ledger only. Member records, seeker records, engagement labels and sentiment outputs stay untouched. No production decisions or leader accounts are created by setup or import.

## Immutability and remaining gates

Closed snapshots cannot be updated or deleted, and closed checkpoints reject new snapshots and reopening. SQLite database triggers enforce this even for direct SQL; Eloquent also guards snapshot/checkpoint writes. Append-only triggers protect import rows, batches and status/leadership/placement events. Equivalent database enforcement must be provided before deploying this migration on a different database engine.

Phase 4 adds quarter close and audited correction revisions. September 30, 2026 must not be closed from today's mutable records without verified as-of evidence. Other checkpoints remain open for genuine source imports.

The supplied Dec 2025/Jun 2026 totals exist only in automated fixtures. They are never loaded into the application database. Full source CSVs, population of dated operational registries, leader accounts/attestation and reconciliation are still needed for production reporting.

## Phase 2: shared analytics contract

`MovementAnalyticsService::report(checkpointKey, compare: 'baseline', group: null, mode: 'auto')` is the public read-only entry point. The internal `MovementLiveCounts` reader supplies dated registry evidence; it is not a second widget API. There are no writes, model training, threshold fitting or generated snapshot saves in either class.

Admin-only HTTP access (session authentication):

```text
GET /admin/analytics/movement-data?checkpoint=2026-06&compare=baseline&mode=reported
GET /admin/analytics/movement-data?checkpoint=2026-09&compare=previous&group=MAIN
```

The endpoint validates checkpoint/group/mode/comparison and returns private, non-cacheable JSON. The dashboard and compatibility URL `/api/analytics-data` now share this contract; the old hardcoded 2024/2025 arrays were removed. Both data URLs require a configured `checkpoint` parameter.

- `auto`: selected open checkpoint uses dated system evidence; other checkpoints use snapshots for comparisons/history. A future open checkpoint has unknown system counts, not projections of today's population.
- `reported`: open checkpoints read attributed legacy imports. This supports reviewing the historical spreadsheet while operational registries are incomplete.
- `live`: computes dated registry evidence for every open checkpoint in the response.
- Closed checkpoints always read saved snapshots, preferring `system` to `legacy_import`; they never substitute mutable live counts.

Each unit has `metrics` (strict child roll-ups), `recorded` (its direct selected-source assertion), `reported` (its imported assertion), `reported_comparisons`, `ratios`, `status`, and `system_vs_last_reported`. `periods` holds the same three evidence layers across checkpoints for charts/pivots. Filters also scope the period data. Unit metadata contains hierarchy keys/names but no member names, contacts, or individual ledger rows.

Roll-ups sum immediate children once, recursively. Any applicable missing child makes the total not reported; a conflicting child makes it conflict. Non-applicable Campus children are skipped; an entirely non-applicable set remains N/A. A supplied group total cannot prove missing leaf counts. Consequently the provided sheet totals and their exact comparisons are preserved under `reported`, while unreconciled strict totals stay unknown. Fixtures confirm that the supplied MAIN/NCP/ICP assertions sum to 2,751/2,848/12,771/300 in December and 3,124/3,257/13,857/319 in June without manufacturing leaf evidence. Complete synthetic leaf fixtures separately prove strict roll-ups and parent mismatch checks.

Variance is current minus base; percent is variance/base × 100, rounded to two decimals for presentation. Both return null when either value is unknown/conflicted/N/A or the base is zero, as requested. The UI must render null as a dash. Previous means the immediately preceding configured checkpoint, never the last nonempty report. Ratios require known numerator/denominator and a nonzero denominator. A last-reported absolute `difference` is also available even when its reported base is zero; its percentage comparison remains null.

Classification uses unrounded percentages: exactly zero is Stable; positive/negative changes are Growing/Declining; absolute change strictly above 40% is Anomaly. A configurable `status_metric` (DMembers initially) determines directional unit status. Any metric conflict/large-change anomaly or out-of-range members/group ratio takes precedence as unit Anomaly. Missing current evidence or an unavailable comparison yields Not reported, with the known current count still retained separately.

Quality checks include incomplete children, parent mismatches, unresolved conflicts, missing closed snapshots, carried-forward values, members/group outside 2–8, changes above 40%, equal DGroups/DLeaders, unmapped satellites, and missing gender. Carried-forward requires every applicable metric to be reported and unchanged; matching nulls never qualify. Unresolved imported conflicts remain visible across checkpoint selection. Missing gender is explicitly current-record quality, not an invented historical fact.

### Live evidence and boundaries

- DMembers: distinct members with a dated assignment to the unit and the latest effective leader-authored Active decision on or before the checkpoint day's end. Leaders are included through the same assignments. Missing status decisions/leader assignments block complete counts; overlapping assignments produce a conflict.
- DGroups: dated group registrations active on the as-of date. DLeaders: unique leaders of those groups with an as-of Active decision. Leading two groups counts once as a leader. A missing leader decision blocks that metric.
- New Leaders: first dated qualification with at least three distinct concurrent members under a leader, across that leader's groups, excluding the leader themself. Qualification also requires appointment evidence. Count the first qualification after the previous checkpoint and through the selected checkpoint, assigned to the appointment's unit. A later reactivation never adds another new leader or removes the original qualification. Missing qualification evidence stays unknown; the baseline has no defined event interval.
- Campus: same-checkpoint imported leader-reported counts only; no inferred campus registry or carry-forward. ICP remains N/A.
- All dated assignment/group/membership intervals use inclusive `effective_from`, exclusive `effective_to`. Status/events use their effective timestamp. Mutable legacy member status, name strings and model engagement predictions are never substituted for dated evidence.

Counts describe the dated registry population. Empty registries do not attest that a unit has zero people/groups; they return not reported with a coverage issue. Once a registry has dated evidence, a genuinely empty active population can return zero. Complete operational registration and history are still required before a real quarter close. The service reads records in batches (tested at no more than 12 SELECTs), and metadata reports actual evidence update times rather than the current render time. Dashboard caching and performance measurements are described in Phase 5 below.

## Phase 3: the one-page dashboard

`/admin/analytics` uses `MovementAnalyticsService::dashboard()` for every widget and export. The page contains URL-backed checkpoint/comparison/group/source filters, verdict and KPI cards, grouped bars, ranked unit bars with checkpoint sparklines, unit drawers, prioritized attention items, current seeker/engagement summaries, follow-up sentiment, guarded forecasts, and a collapsed full-data pivot. Eight ranked units appear initially; Show all reveals the rest. Native dialog behavior supports keyboard focus, Escape and return focus. Local HTML/CSS/SVG charts require no third-party chart CDN.

Filters apply on submission. `metric` and `sort` are also preserved in the URL; browser back/forward restores the view. Failed requests hide stale content and disable exports until retry succeeds. No checkpoint setup produces a dedicated empty state.

The JSON endpoint now adds `activity`, `forecasts`, `pivot`, and per-unit satellites, remarks and import history. Add `format=csv` to download exactly the pivot's columns/rows for the selected scope. Null cells export empty with explicit adjacent data-state columns; the pivot displays a dash. Formula-like text is escaped for spreadsheet safety; negative numeric changes retain their numeric sign. CSV excludes raw member/seeker records, contacts and follow-up notes.

Current operational panels are explicitly separate from checkpoint movement counts:

- Seeker totals include active and archived records, with an Active/Archived split. Archiving and restoring leave total seekers and the joining rate unchanged. Placed requires a valid `converted_member_id` pointing to an existing member, regardless of the mutable Pending/Successful label; an assignment alone does not count. Broken conversion links are unknown and suppress the placement rate. Counts by unit use the current satellite mapping. Historical joining trends use first dated conversion events only, and remain unknown before dated evidence exists.
- Engagement uses each member's current stored classification (including unclassified members) and exposes counts by unit. It does not recompute predictions or change leader-decided Active status.
- Sentiment aggregates existing analyzed member/seeker follow-ups only. It counts records, not unique people, and does not combine system feedback or reveal notes.

Forecasts require at least three measured checkpoints, including the selected checkpoint, plus a configured next checkpoint. Linear least squares uses configured checkpoint positions so gaps do not collapse time. The displayed range is the predicted count plus/minus the larger of maximum fitted residual, mean absolute checkpoint change, and one count, floored at zero. This is an illustrative scenario band, not a calibrated confidence interval. The June backtest uses only December/March to predict June and compares absolute error with carrying March forward. Future observations never enter that fit; no accuracy claim appears without actual June evidence.

### Verification and browser artifacts

```bash
php artisan test --compact
php tests/Browser/render-movement.php
node tests/Browser/movement-analytics.mjs
```

The browser harness requires the existing local admin, configured analytics structure, installed `@playwright/test`/`axe-core`, and local Microsoft Edge. Rendering queries the local database without mutating it, then tests the rendered Blade page with intercepted aggregate responses. PHP feature tests separately cover actual endpoint authorization, filtering, SELECT-only behavior and every CSV/pivot cell. Browser tests cover URL/history, expanded rankings, drawers and keyboard focus, metric tabs, empty/error/retry states, tablet overflow, an automated WCAG AA scan, and a clearly labeled synthetic populated chart case. Synthetic chart values never enter the database.

Artifacts remain private in `storage/framework/testing/movement-ui/`: before/after desktop captures, tablet capture, unit drawer, synthetic chart capture and accessibility results. Pre-change captures are preserved when available. Automated accessibility checks do not substitute for the broader Phase 5 accessibility/performance review. Operational history and actual source CSVs remain the main data gaps; the Visayas conflict is still unresolved.

## Phase 4: checkpoint close and corrections

The owner confirmed this separation of duties: **leaders confirm assigned units; admins finalize the movement**. Admins cannot submit unit attestations. Leaders cannot finalize a checkpoint or record organization-wide corrections. A leader can confirm a leaf unit only when every satellite mapped to that unit falls within their existing satellite permission. Units without a mapped satellite/assigned leader need those assignments before confirmation.

On the admin dashboard, use **Review checkpoint close**. Leaders use **Checkpoint review** in their sidebar (`/analytics/checkpoints`). The review shows counts, missing confirmations, reconciliation issues and the data-quality report. Choose dated system records or imported as-of reports explicitly; missing system history never falls back silently to an import.

1. Populate verified as-of source records/imports. Required close metrics are DLeaders, DGroups, DMembers and applicable Campus. New Leaders can remain explicitly unknown because its snapshot field is nullable. ICP Campus stays N/A.
2. Each assigned leader reviews and confirms their unit, entering a meaningful reason and dated evidence reference. Confirmations are tied to the reviewed counts; changed counts require a fresh confirmation.
3. An admin reviews the complete movement, acknowledges quality issues, enters a close reason/evidence reference and types the exact checkpoint key. Missing counts/confirmations, conflicts, parent mismatches and overlapping assignments block finalization. The checkpoint day must have ended in Asia/Manila.
4. A transaction rechecks the review fingerprint and writes all materialized snapshots, the closure audit and the closed status together. The fingerprint includes source records and hierarchy/mapping state. A failed write rolls back the entire close. Duplicate close submissions cannot create another closure.

New tables only: `checkpoint_attestations`, `checkpoint_closures`, `checkpoint_revisions`. SQLite triggers reject UPDATE/DELETE of their audit rows. The original member/seeker tables are not modified. Existing checkpoint/snapshot immutability guards remain in force. Equivalent database guards are required before deploying to a different database engine.

Materialized closing snapshots use the `system` source slot, including when materialized from imported evidence; `checkpoint_closures.basis` and each snapshot's `close_basis` quality entry preserve the actual basis. Original imported snapshots and raw import rows remain untouched. A closed parent retains its frozen aggregate if new units are added later.

After close, an admin can record a leaf-unit correction from the same review dialog. A reason, evidence reference and expected revision number are required. Corrections append before/after values, actor and time; they never update the original snapshot or reopen the checkpoint. The shared service reads the latest version and recalculates parent totals within the original closed scope. Units added after close cannot be inserted into its past correction scope. Concurrent/stale version submissions are rejected. The unit drawer's change log includes closure and correction provenance, and the dashboard labels revised counts with their revision number.

All review/mutation routes require authentication and the leader/admin role; service checks further enforce the separation of duties and assigned-unit scope. Mutations use the standard web CSRF middleware. Tests cover CSRF enforcement, permissions, missing evidence, explicit confirmation, stale tokens, duplicate close, rollback, immutable originals, correction versions and frozen aggregate scope.

### Actual September close status

The local review found **29 reporting units with missing required counts, zero unit confirmations and zero leader accounts**. No real checkpoint was closed and no correction was fabricated. To complete the first real September close, supply the verified September as-of reports or dated operational records, provision legitimate leader accounts and their unit/satellite assignments, and obtain their confirmations. Existing current member counts cannot establish historical September evidence by themselves.

```bash
php artisan migrate --path=database/migrations/2026_10_02_110000_create_checkpoint_close_audit.php
php artisan test --compact
php tests/Browser/render-movement.php
node tests/Browser/checkpoint-close.mjs
```

The browser harness tests the real blocked review and uses intercepted synthetic responses for successful-close/correction interactions. It sends no real mutation requests. `storage/framework/testing/movement-ui/checkpoint-close-blocked.png` captures the actual blocked review.

## Phase 5: quality and performance

`MovementAnalyticsService::dashboard()` now caches its complete aggregate response, so widgets and CSV still use exactly the same contract. The cache is private Laravel file storage (an isolated array store in tests), never browser/public storage or database cache rows. Its key includes database identity, all filters, analytics configuration, the Manila date and an invalidation generation. The lifetime is configurable at `movement_analytics.cache.seconds` (60 seconds; zero disables caching).

Successful application database writes invalidate the generation, including query-builder bulk imports. Commit and rollback events invalidate it again to prevent a concurrent request's pre-commit result from surviving commit. Reads inside a transaction bypass the cache entirely. `report()` and all checkpoint-close reviews remain uncached, and finalization rechecks its evidence inside the transaction. Ordinary unrelated writes also invalidate the cache conservatively. No existing source table or metric arithmetic was changed.

Direct writes by external database clients cannot emit Laravel events; they become visible within the 60-second lifetime, or immediately after `php artisan cache:clear --store=file`. Multiple application hosts must share this private cache filesystem for immediate cross-host invalidation, or disable this cache. This is a local/single-host implementation, not a distributed cache deployment.

Dashboard requests now time out after 20 seconds with a retry message. Stale content and export links stay disabled while loading or after a failure; newer filter requests abort older ones. Checkpoint save requests disable their source selector, inputs and buttons until the server review refreshes, and expose the busy state to assistive technology.

Validation on 2026-10-03:

- Full suite: **238 tests passed**. Additional targeted checks passed after extending CSRF assertions to status changes, unit attestations, finalization and corrections; missing tokens return 419 on all four routes.
- Browser checks: whole-page automated WCAG 2 A/AA and 2.1 AA scans found zero violations on actual empty evidence and synthetic populated charts; the checkpoint dialog also passed. Keyboard submission, Escape/focus restoration, URL history, loading, retry, disabled export and 768/820px tablet overflow checks passed. Automated scans do not establish complete WCAG conformance or replace screen-reader testing.
- Actual local HTTP middleware/controller benchmark: page 126 ms / 3 queries; cold JSON 352 ms / 24 queries; warm JSON 32 ms / 2 queries. Framework bootstrap was measured separately at 466 ms. A previous run measured cold/warm JSON at 276/16 ms.
- Loopback browser readiness with compiled assets and intercepted actual JSON: 621 ms on the first navigation, then 126/143 ms. These isolate browser rendering from the real endpoint timings; they are not a live authenticated end-to-end network benchmark. An artificial `.test` hostname added about two seconds before document activation in local Edge, so performance checks use the app's loopback origin.

Reproduce the checks from the Laravel directory:

```bash
php artisan test --compact
npm run build
php tests/Browser/render-movement.php
node tests/Browser/movement-analytics.mjs
node tests/Browser/checkpoint-close.mjs
php tests/Browser/benchmark-movement.php
node tests/Browser/benchmark-movement.mjs
```

The renderer explicitly selects compiled Vite assets without modifying the developer's hot file. Benchmark requests use an in-memory admin identity and array sessions; source records are read only. The browser intercepts all requests, including synthetic saves. Screenshots and JSON timing/accessibility artifacts are kept under private `storage/framework/testing/movement-ui/`.

### Remaining operational gaps

The September close still needs verified as-of counts and legitimate assigned-leader confirmations. Source CSVs are still missing and the Visayas baseline conflict remains unresolved. Historical counts and forecast accuracy cannot be established from current membership alone. SQLite audit guards need equivalent implementation before switching database engines. Verify real authenticated end-to-end latency and screen-reader behavior on the intended deployment before treating the local quality measurements as deployment guarantees.
