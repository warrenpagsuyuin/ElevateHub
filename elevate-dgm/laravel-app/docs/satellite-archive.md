# Satellite archiving

Implemented October 13, 2026. Confirmed decisions: allow archiving with a population warning and lock access; keep historical research and sentiment results.

## Changes

- The migration `zzzzz_2026_10_13_100000_add_satellite_archive.php` adds indexed `archived_at` and nullable `archived_by`. Applied locally; other pending migrations were not run against local data.
- `Satellite::archive()` and `restore()` centralize lifecycle changes and invalidate movement caches. Instance and bulk Eloquent deletion archive instead. An active global scope delegates to reusable `active()`; `archived()` explicitly removes it. Raw SQL must not be used to delete satellites.
- `SatellitePolicy` enforces admin archive/restore permissions. The existing DELETE route now archives; new POST restore and GET read-only details routes use explicit archived lookup.
- Active route binding, lists, exports, sidebar relationships and satellite pickers exclude archives. Names and slugs remain reserved.
- The confirmation includes actual active member, seeker and assigned-user counts. Success fades the row and reloads current cards/pagination, then displays a toast with Undo. Failures retain the row and display a readable error. Menus use the browser top layer and viewport positioning, including upward placement.
- Archived rows show the archive date, Restore and read-only View details. No delete/edit action appears there.

## Dependencies and effects

| Dependency | Effect |
| --- | --- |
| Users and assignments | Preserved. OTP sign-in is rejected for users assigned to an archived satellite. Existing sessions receive **404** for satellite URLs. Restore reopens access. |
| Members and seekers | No deletion, reassignment or status change during archive/restore. Archived satellite names and leaders disappear from active pickers. |
| Follow-ups | All history remains linked. Open Reports now counts Pending follow-ups of active satellites, replacing the hard-coded 12. |
| Engagement results and model runs | Retained. Current engagement widgets exclude archived satellites; historical model relationships remain readable. |
| Org mappings and snapshots | Retained. Active movement projections exclude archived units and rebuild affected parent totals from remaining evidence. Aggregates mixing active and archived satellites cannot safely be split: active projections show unavailable evidence rather than fabricate a subtraction. Original report evidence remains intact. |
| Research and sentiment | Eligibility, labels, predictions and results remain unchanged. Pages and generated report text disclose historical inclusion; attributable feedback gets an archived badge. Historical follow-up sentiment includes archived records, including within group filters. Feedback attribution uses the existing evaluator/district association; records lacking that association cannot receive an inferred satellite label. |
| Counts and cache | Satellites, Member Breakdown and Analytics current satellite cards share `Satellite::dashboardCounts()`. Archive/restore invalidate analytics cache. Dated checkpoint metrics retain separate definitions and coverage. |

## Main files

- Model, policy and migration: `app/Models/Satellite.php`, `app/Policies/SatellitePolicy.php`, migration above.
- Controllers: AdminSatelliteController, AdminMemberController, AdminAnalyticsController, AdminSeekerController, DashboardDirectoryController and OtpLoginController; satellite middleware and `routes/web.php`.
- Services: DashboardDirectoryService, SatelliteArchiveVisibility, MovementOverview, MovementDashboardData and MovementAnalyticsService. Research changes add labels without changing evaluation math.
- Views: admin/satellites, admin/satellite-archive-details, analytics/research, and shared table scripts. No new dependencies.
- Tests: SatelliteArchiveTest, updated RemoveSatelliteTest, and isolated browser fixtures under tests/Browser.

## Verification

- Focused regression: **109 passed, 1,017 assertions**.
- Archive coverage includes linked-record preservation, pickers, counts/exports, restore, authorization, 404 access, OTP denial, historical analytics retention, and bulk Eloquent deletion.
- Full suite: **318 passed, 2 failed**. DashboardDirectoryTest expects 13 members but gets 12; this also fails with the new archive query restriction removed. SystemFeedbackSentimentTest expects admin feedback access to be forbidden, while the existing auth-only feedback routes return 200. These unrelated behaviors were left unchanged.
- Production asset build, Blade compilation and shared table JavaScript syntax checks passed.
- Headless Edge checks passed: menu positioning, warning, Cancel, 403/404/500 row retention, Archive, refreshed cards, Undo, archived actions and Restore confirmation. These use isolated rendered fixtures and mocked HTTP responses; feature tests separately cover backend requests.

Repeat checks:

    php artisan test --compact --filter="SatelliteArchiveTest|RemoveSatelliteTest"
    php tests/Browser/render-satellite-archive.php
    node tests/Browser/satellite-archive.mjs

Browser fixtures use an in-memory database. No real satellite was archived during implementation.

## Manual acceptance

1. As admin, open `/admin/satellites`, locate Plp, and open its menu near the bottom of the table. Verify the entire menu remains visible.
2. Choose Archive satellite. Verify the archive message and population warning. Cancel first and confirm nothing changes.
3. Confirm Archive. Verify the row fades, cards update and the toast offers Undo. Check Members, Analytics current satellite totals, CSV and user/seeker satellite pickers.
4. With a previously assigned user session, open `/satellites/plp/dashboard` and `/satellites/plp/members`; expect 404. Attempt OTP login; expect the archived message. The account remains in admin Users.
5. Use Undo and verify data, counts and access return. Archive again, open Archived, inspect the date and read-only details, then Restore with confirmation.
6. Verify follow-ups, engagement, research and checkpoint history remain. Non-admin archive/restore requests must return 403. A failed request must keep the row and display an error.

No archive behavior decisions remain open. The two existing test failures are separate follow-up issues.

