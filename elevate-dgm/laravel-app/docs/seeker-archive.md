# Seeker archive

Approved statuses: Pending is active; Successful and Unsuccessful are final. The final list lives in config/seekers.php. Status is not proof of membership conversion: placement still requires a valid converted_member_id.

## Usage

At /admin/seekers, Active hides archived rows. Archive (?view=archive) shows the archive date, actor, reason and read-only details. Search, district, school, status, archive dates, sorting and pagination stay in the URL. Filter district options are scoped to the selected tab; assignment options retain available districts.

- A final status change archives and appends an audit row in the same transaction.
- Archive from the row menu requires a reason. Select rows for bulk Archive or Restore (up to 100 per request).
- Restore requires either a non-final status or explicit ?Keep final status, stay active? when the current status is final. Optional notes are retained in history. A non-final status update also restores an archived record.
- Session Undo restores the record without changing its current status. Tokens are bound to the current actor/session, consumed once, and checked against the latest archive event. A newer restore/archive invalidates an old Undo.
- Export CSV exports the selected tab and filters with archive columns. Historical CSV (all records), or the export endpoint without a view parameter, includes active and archived records. Formula-like text is escaped.
- Admins can manage all seekers. Leaders can archive/restore only seekers in their assigned district; their district Seekers page also exposes an Archive tab. Other district management roles cannot indirectly archive through final-status changes or conversion.

## Storage and preservation

The existing indexed archived_at column is reused. Migration 2026_10_08_100000 adds nullable archived_by and archive_reason plus seeker_archive_log. Existing archive dates are preserved; unknown actors/reasons are shown as Not recorded. No fabricated legacy audit entries are added. The audit model rejects updates/deletes and application services only append. Database administrators with direct SQL access remain responsible for audit retention; this is not a tamper-proof external ledger. Audit actor foreign keys restrict deletion of users referenced by history.

No global archive scope or SoftDeletes is installed. Foreign keys, follow-ups, classifications and placement links remain intact. Status, archive metadata and log writes share a transaction; locked-row reads, consistent bulk ordering and retries protect repeat/concurrent updates. SQLite uses database-level write serialization rather than row locks. Assignment, conversion and new-follow-up creation recheck state inside their transactions.

Analytics, cumulative per-unit counts, historical exports, engagement and follow-up sentiment include archives. Active district work queues exclude archived and already-converted seekers. Pending/under-review operational summaries remain active-only. Restoring a converted seeker does not put them back in the conversion queue; they remain visible in the admin Active tab.

Google Sheet sync leaves archived rows untouched. An unchanged final status on an explicitly restored record stays active. An actual status change or a new final-status import uses the archive workflow. Sync is invoked as an authenticated admin.

## Backfill (separate approval required)

Run this after deploying the migration to preview unarchived final-status records:

~~~sh
php artisan seekers:archive-backfill
~~~

The default run only reads records and displays candidate IDs, names, statuses and count in the local terminal. After reviewing and obtaining explicit approval, an admin may run:

~~~sh
php artisan seekers:archive-backfill --apply --actor=ADMIN_USER_ID
~~~

Apply rechecks each locked record, skips existing archives, preserves status/assignment/placement, and appends audit events. Re-running is idempotent. Do not use migration or deployment hooks to apply a backfill automatically.

Local verification found 514 active and 8 archived seekers (522 total), including Aaron Alcantara already archived. Dry run: 0 candidates; no apply was run. Existing seeker fields and all follow-up records were fingerprinted before/after the migration and matched.

The old analytics query excluded those eight archives. Correcting the definition increases the current pipeline denominator once from 514 to 522; six linked members remain six, so the overall joining rate changes from 1.17% to 1.15%. Subsequent archive/restore operations do not change the denominator or placement numerator. No other metric formulas changed.

## Verification

~~~sh
php artisan test --filter=SeekerArchiveTest
php artisan test --compact
npm.cmd run build -- --configLoader native
php tests/Browser/render-seeker-archive.php --baseline
php tests/Browser/render-seeker-archive.php
node tests/Browser/seeker-archive.mjs
~~~

Browser rendering is local-only and read-only. HTML, screenshots, record fingerprints and accessibility results are private under storage/framework/testing/seeker-archive, not public/. The before screenshot uses the previously captured seekers-layout.html when available. Browser interaction is intercepted; mutations and authorization are exercised by feature tests against an isolated SQLite database. Do not publish the private screenshots or rendered HTML containing seeker details.

Remaining decisions: none for the approved status mapping. Backfill apply remains separately gated if future dry runs identify candidates.

### Completed verification (2026-10-09)

- Full suite: 263 tests passed, 3,848 assertions.
- Archive-specific coverage: 15 tests passed, including conversion idempotency and audit-failure rollback. After the final staff status-display adjustment, the affected controller/follow-up/archive tests were rerun successfully.
- Production build passed using Vite's native config loader (the default bundled loader encountered a Windows sandbox parent-directory restriction).
- Browser checks passed for Active/Archive separation, row/bulk actions, dialogs, Escape/focus return and 1440/1024/768/390-pixel layouts. Active, Archive and restore-dialog WCAG AA automated scans reported no violations in the tested regions.
- Private screenshots: before-desktop.png, active-desktop.png, archive-desktop.png, restore-dialog.png and archive-390.png in storage/framework/testing/seeker-archive/.
- Existing seeker/follow-up fingerprints matched after migration and validation; live pipeline verified at 522 total, 514 active, 8 archived, 6 joined, 1.15%. Backfill dry run found zero candidates; apply was not run.
