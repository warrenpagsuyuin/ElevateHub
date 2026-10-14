# Dashboard directories

Implemented October 9, 2026. The dashboard uses live records; historical movement analytics retains its separate definitions.

## Verified local figures

| Scope | Members | Leaders | DGroups | Campuses |
|---|---:|---:|---:|---:|
| Overall / MAIN | 178 | 28 | 27 | 33 |
| Pasig | 91 | 14 | 13 | 32 |
| Main Ortigas | 87 | 14 | 14 | 32 |
| NCP / ICP | 0 | 0 | 0 | 0 |

Anonas, UBELT, THE HUB and EASTWOOD each have zero records in these metrics. Campus totals are distinct across satellites, not sums: Pasig and Main Ortigas share 31 non-placeholder values. PLP and Pamantasan ng Lungsod ng Pasig remain separate. No spelling variants merge under the approved normalization in the current data. Two N/A entries are excluded.

Members include all Member-role records: 149 Active, 23 Inactive and 6 New. The member schema has no archive flag; inactive is not treated as archived.

Leader distribution (assigned Member-role rows in the same satellite):

| Scope | 0 | 1-2 | 3-4 | 5+ |
|---|---:|---:|---:|---:|
| Overall | 0 | 1 | 4 | 23 |
| Pasig | 0 | 1 | 2 | 11 |
| Main Ortigas | 0 | 0 | 2 | 12 |

## Definitions and API

- Members: dgroup_role = Member. Leaders: Member::ROLE_LEADER, whose existing stored value is Dgroup Leader.
- DGroups: leaders with at least config('dashboard_directory.DGROUP_MIN_MEMBERS') linked Member-role rows, default 3. No registered-group/district counts or active_disciples estimates.
- Campuses: members.campus_school from district records, trim/lowercase/collapse whitespace, excluding config('dashboard_directory.CAMPUS_PLACEHOLDERS'). This includes campus values on leader records. An alias-to-canonical table is a future option only.
- DashboardDirectoryService::query(type, category?, satellite?) supplies both card and directory counts; withAssignmentCount adds the same-satellite assignment count. campuses(), summary(), and distribution() complete the shared API.
- GET /admin/dashboard/{directory}, named admin.dashboard.directory; directory is dgroup-members, dgroup-leaders, dgroups, or campuses. Same auth/admin middleware as the dashboard.
- Query parameters: category=main|ncp|icp, satellite=id, search, sort, direction=asc|desc, per_page=10|25|50|100, page. Search and sorting preserve filters and pagination links.
- There is no independent DGroup name for this definition. Lists identify the DGroup by its leader and show district, campus, category, contact and registration date where applicable.

## Leader migration and approved backfill

The nullable indexed members.leader_member_id references members.id with ON DELETE SET NULL. The zz_ migration prefix deliberately sorts after this repository's legacy create_members_table.php and update_members_for_satellite_management.php migrations on fresh installs.

Applied the approved 175 exact pairs only. Original columns and timestamps were fingerprint-verified unchanged. IDs 102, 103 and 104 remain NULL with their original John Leader text pending an explicit selection. The private approved manifest and baseline are in storage/app/private/dashboard-directory/. Do not publish that directory or commit the manifest.

The reconciliation command previews by default:

    php artisan members:reconcile-leaders storage/app/private/dashboard-directory/approved-leader-links.json

Application requires --apply --approved-hash=SHA256_FROM_REVIEW. It locks and revalidates the approved records, refuses changed/ambiguous matches, rolls back the whole batch on failure and is idempotent. Do not regenerate or apply additional matches without reviewing them.

The member create/edit dropdown submits leader IDs from the same district. Validation rejects non-leaders, cross-district links and self-links; selected names are derived server-side. Leader renames synchronize linked text. Reassign a leader's members before changing that leader's role or district. Deleting a leader nulls links and preserves historical text. New legacy import/conversion records resolve only unique exact same-district names; unresolved names remain unlinked. Existing unresolved records are not automatically matched on ordinary edits.

## Performance and cache

Member/leader/group lists paginate in SQL and eager-load leader/satellite relationships. Campus distinct raw name/satellite pairs are normalized on the server and then paginated; this keeps Unicode normalization consistent with card counts. No persistent cache is used, so changes appear on refresh without invalidation problems. Concurrent changes between clicking a card and opening the list can naturally change a live count.

The FK index is applied. At substantially larger volume, profile composite indexes on members(dgroup_role, satellite_id), members(leader_member_id, dgroup_role, satellite_id), and satellites(hub). Do not add overlapping indexes without checking query plans. Campus normalization may eventually warrant a maintained canonical column/table; it is not implemented now. Contains-search does not benefit from ordinary B-tree indexes.

## Changed/created files

| Files | Purpose |
|---|---|
| app/Http/Controllers/AdminDashboardController.php | Live summary cards |
| app/Http/Controllers/DashboardDirectoryController.php | Validated searchable directories |
| app/Services/DashboardDirectoryService.php | Shared count/list definitions |
| config/dashboard_directory.php | Threshold, placeholders, categories |
| resources/views/admin/dashboard.blade.php | Accessible card links and styling |
| resources/views/admin/dashboard-directory/{card,index,sort-heading}.blade.php | Shared cards and directories |
| routes/web.php | Admin-only directory route |
| app/Models/Member.php | Leader relations and transactional assignment validation |
| app/Services/LeaderAssignmentService.php | Assignment rules and name synchronization |
| app/Http/Controllers/AdminSatelliteController.php | Leader options and input validation |
| resources/views/satellites/members.blade.php | Leader-ID dropdown |
| database/migrations/zz_2026_10_09_100000_add_leader_member_id_to_members_table.php | Nullable indexed self-reference |
| app/Services/LeaderReconciliationService.php | Approved atomic exact-link backfill |
| app/Console/Commands/ReconcileMemberLeaders.php | Preview/apply command |
| tests/Feature/DashboardDirectoryTest.php | Scope/count, filters, access, linkage, reconciliation tests |
| tests/Feature/AdminDashboardRoutesTest.php | Updated live-count expectations |
| tests/Browser/render-dashboard-directory.php | Local read-only render and preservation check |
| tests/Browser/dashboard-directory.mjs | Keyboard, responsive and accessibility checks |
| docs/dashboard-directory.md, CHANGELOG.md | Documentation |

## Manual verification

1. Sign in as admin and open /admin/dashboard. Verify 28 / 27 / 178 / 33 against current data.
2. Click each total and MAIN/NCP/ICP segment. Verify the list count matches; empty member scopes show No NCP members yet or No ICP members yet.
3. Search, sort column headings, change page size and follow Next/Previous. Bookmark the filtered URL, reload and use Back to dashboard.
4. On Campuses select Pasig or Main Ortigas: each shows 32. Reset district: 33 overall.
5. On DGroups check the displayed assignment distribution and threshold.
6. In a disposable/test dataset, choose a leader in the member form. Confirm the name derives from that selection, a leader rename propagates, and cross-district/non-leader assignments fail.
7. Verify keyboard focus/Enter on card links and horizontal table scrolling at phone widths.

Automated checks run against SQLite :memory:, never the local data. Browser checks use privately rendered read-only HTML; authenticated routing and data filtering are covered by feature tests. Live preservation evidence is storage/framework/testing/dashboard-directory/verification.json.

## Verification results

- Full existing suite plus initial directory tests: 268 passed, 3905 assertions (345.28 seconds).
- Final focused dashboard/directory/form checks after adding the edit-payload regression: 45 passed, 448 assertions.
- Browser checks passed at 1440, 768 and 390 pixels; keyboard activation, pagination display, NCP empty state and scoped WCAG AA scan passed.
- route:list confirms web, auth and role:admin on both dashboard routes.
- Original member-field fingerprint unchanged; 175 linked records and exactly three unlinked Member-role records remain.
