<?php

namespace App\Http\Controllers;

use App\Models\EngagementResult;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use App\Services\EngagementClassificationService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AdminSatelliteController extends Controller
{
    private const HUBS = ['NCP', 'ICP', 'MAIN'];

    private const TABLE_PAGE_SIZE = 10;

    private const PAGE_SIZES = [10, 25, 50, 100];

    private const DASHBOARD_MONTHS = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    private const SORTS = [
        'created' => 'created_at',
        'name' => 'name',
        'hub' => 'hub',
        'leaders' => 'leaders_count',
        'members' => 'members_count',
    ];

    private const MEMBER_SORTS = [
        'name' => 'full_name',
        'status' => 'status',
        'role' => 'dgroup_role',
        'school' => 'campus_school',
        'archived_at' => 'archived_at',
    ];

    private const MEMBER_IMPORT_HEADERS = [
        'Status',
        'Dgroup Role',
        'Last Name',
        'First Name',
        'Full Name',
        'Gender',
        'Email Address',
        'Contact Number',
        'Facebook Link',
        'Birthdate',
        'Age',
        'District',
        'Area',
        'Campus/School',
        'Name of DLeader',
        'No. of Active Disciples',
        'Year Joined',
        'Ministry Involvement',
        'How many Dgroups do you lead each week?',
        'Generation',
        'Refresh Date',
        'Year',
        'Month',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);
        $counts = Satellite::dashboardCounts();
        $tabCounts = Satellite::withoutGlobalScope('active')
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) as archived_count')
            ->first();
        $satellites = $this->filteredSatellitesQuery($filters, $request->query('view') === 'archived')
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
        $assignableUsers = User::where('role', '!=', 'admin')
            ->orderBy('name')
            ->get();

        return view('admin.satellites', [
            'archivedView' => $request->query('view') === 'archived',
            'statusTabCounts' => [
                'active' => (int) ($tabCounts->active_count ?? 0),
                'archived' => (int) ($tabCounts->archived_count ?? 0),
            ],
            'satellites' => $satellites,
            'hubs' => self::HUBS,
            'assignableUsers' => $assignableUsers,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'totalSatellites' => $counts['totalSatellites'],
            'activeLeaders' => $counts['activeLeaders'],
            'activeMembers' => $counts['activeMembers'],
            'openReports' => $counts['openReports'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:satellites,name'],
            'hub' => ['required', 'in:'.implode(',', self::HUBS)],
            'initial_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $satellite = Satellite::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'hub' => $validated['hub'],
            'created_by_id' => Auth::id(),
        ]);

        if (! empty($validated['initial_user_id'])) {
            User::whereKey($validated['initial_user_id'])
                ->update(['district' => $satellite->name]);
        }

        return redirect()
            ->route('admin.satellites')
            ->with('status', "{$satellite->name} satellite page created.");
    }

    public function destroy(Request $request, Satellite $satellite): RedirectResponse|JsonResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('archive', $satellite);
        $satellite->archive($request->user());
        return $request->expectsJson()
            ? response()->json(['message' => 'Satellite archived', 'restore_url' => route('admin.satellites.restore', $satellite->slug)])
            : redirect()->route('admin.satellites')->with('status', 'Satellite archived');
    }

    public function restore(Request $request, string $archivedSatellite): RedirectResponse|JsonResponse
    {
        $satellite = Satellite::archived()->where('slug', $archivedSatellite)->firstOrFail();
        \Illuminate\Support\Facades\Gate::authorize('restore', $satellite);
        $satellite->restore();
        return $request->expectsJson() ? response()->json(['message' => 'Satellite restored'])
            : redirect()->route('admin.satellites')->with('status', 'Satellite restored');
    }

    public function archivedDetails(string $archivedSatellite): View
    {
        $satellite = Satellite::archived()->where('slug', $archivedSatellite)->firstOrFail();
        \Illuminate\Support\Facades\Gate::authorize('restore', $satellite);
        return view('admin.satellite-archive-details', ['satellite' => $satellite]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $filename = 'satellites-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredSatellitesQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Satellite', 'Hub', 'Assigned Users', 'Leaders', 'Members', 'Seekers', 'New Members']);

            $query->chunk(500, function ($satellites) use ($handle): void {
                foreach ($satellites as $satellite) {
                    fputcsv($handle, [
                        $satellite->display_name,
                        $satellite->hub,
                        $satellite->assignedUsers->pluck('name')->implode(', ') ?: 'Unassigned',
                        $satellite->leaders_count,
                        $satellite->members_count,
                        $satellite->seekers_count,
                        $satellite->new_members_count,
                    ]);
                }
            });
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportMembers(Satellite $satellite)
    {
        $members = $satellite->members()->orderBy('id');
        $filename = $satellite->slug.'-members-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($members): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Member', 'Dgroup Role', 'Status', 'Gender', 'Email', 'Contact Number', 'Campus/School', 'District', 'Area']);

            $members->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $member) {
                    fputcsv($handle, [
                        $member->display_name,
                        $member->display_role,
                        $member->status,
                        $member->gender,
                        $member->display_email,
                        $member->display_contact,
                        $member->campus_school,
                        $member->district,
                        $member->area,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function dashboard(Satellite $satellite): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canAccessSatellite($satellite), 403);

        return view('satellites.dashboard', [
            'satellite' => $satellite,
            'canManageSatellite' => $user->canManageSatellite($satellite),
            'dashboardMonths' => self::DASHBOARD_MONTHS,
            'selectedDashboardMonth' => now()->format('F'),
            'satelliteSummaryCards' => $this->satelliteSummaryCards($satellite),
            'engagementSummary' => $this->engagementSummary($satellite),
            'seekerSummary' => $this->seekerSummary($satellite),
            'recentActivity' => $this->recentActivity($satellite),
        ]);
    }

    public function members(Request $request, Satellite $satellite): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canViewSatelliteMembers($satellite), 403);

        $satellite->load('assignedUsers');
        $filters = $this->memberFilters($request);
        $archivedView = $filters['view'] === 'archived';
        [$sort, $direction] = $this->memberSort($request);
        $perPage = $this->perPage($request);
        $allMembersQuery = $archivedView ? $satellite->archivedMembers() : $satellite->members();
        $memberTabCounts = $satellite->allMembers()
            ->selectRaw('SUM(CASE WHEN members.archived_at IS NULL AND (members.status IS NULL OR members.status != ?) THEN 1 ELSE 0 END) as active_count', [Member::STATUS_INACTIVE])
            ->selectRaw('SUM(CASE WHEN members.archived_at IS NOT NULL THEN 1 ELSE 0 END) as archived_count')
            ->first();
        $members = $this->filteredSatelliteMembersQuery($satellite, $filters, $archivedView)
            ->with(['engagementResult', 'leader'])
            ->orderBy(self::MEMBER_SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
        $filterOptions = $this->memberFilterOptions($satellite, $archivedView);
        $leaderOptions = $satellite->members()
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'name']);
        $archivedLeaderNames = $satellite->archivedMembers()
            ->where('dgroup_role', Member::ROLE_LEADER)
            ->get()
            ->flatMap(fn (Member $leader): array => [$leader->display_name, $leader->name])
            ->unique()
            ->values()
            ->all();

        return view('satellites.members', [
            'satellite' => $satellite,
            'members' => $members,
            'membersShown' => $members->total(),
            'membersTotal' => (clone $allMembersQuery)->count(),
            'activeMembersTotal' => (int) ($memberTabCounts->active_count ?? 0),
            'archivedMembersTotal' => (int) ($memberTabCounts->archived_count ?? 0),
            'archivedView' => $archivedView,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'statuses' => Member::STATUSES,
            'dgroupRoles' => Member::DGROUP_ROLES,
            'genders' => Member::GENDERS,
            'leaderOptions' => $leaderOptions,
            'archivedLeaderNames' => $archivedLeaderNames,
            'filterOptions' => $filterOptions,
            'hasActiveFilters' => $this->hasActiveMemberFilters($filters),
            'importPreview' => session('memberImportPreview'),
            'canManageSatellite' => $user->canManageSatellite($satellite),
            'canUpdateMembers' => $user->canUpdateSatelliteMembers($satellite),
        ]);
    }

    public function storeMember(Request $request, Satellite $satellite): RedirectResponse
    {
        $this->authorizeSatelliteMemberManagement($satellite);

        $member = Member::create($this->validatedMemberPayload($request, $satellite));
        return redirect()
            ->route('satellites.members', $satellite)
            ->with('status', "{$member->display_name} was added successfully.");
    }

    public function updateMember(Request $request, Satellite $satellite, Member $member): RedirectResponse|JsonResponse
    {
        $this->authorizeSatelliteMemberUpdate($satellite, $member);

        $member->update($this->validatedMemberPayload($request, $satellite, $member));
        $member->refresh();
        $archived = $member->archived_at !== null;
        $message = $archived
            ? 'Member archived.'
            : ($request->input('view') === 'archived' ? 'Member restored to the active list.' : "{$member->display_name} was updated successfully.");

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'archived' => $archived,
                'counts' => [
                    'active' => $satellite->members()->count(),
                    'archived' => $satellite->archivedMembers()->count(),
                ],
                'member' => $member->formPayload(),
            ]);
        }

        return redirect()
            ->route('satellites.members', $archived ? [$satellite, 'view' => 'archived'] : $satellite)
            ->with('status', $message);
    }

    public function showMember(Request $request, Satellite $satellite, Member $member): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canViewSatelliteMembers($satellite), 403);
        abort_unless((int) $member->satellite_id === (int) $satellite->id, 404);

        if ($member->archived_at !== null && $request->query('view') !== 'archived') {
            abort(404);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'member' => $member->detailPayload(),
                'form' => $member->formPayload(),
                'archived' => $member->archived_at !== null,
            ]);
        }

        return redirect()->route('satellites.members', array_filter([
            'satellite' => $satellite,
            'view' => $member->archived_at !== null ? 'archived' : null,
            'search' => $member->display_name,
        ]));
    }

    public function restoreMember(Satellite $satellite, Member $member): RedirectResponse
    {
        $this->authorizeSatelliteMemberUpdate($satellite, $member);
        abort_unless($member->archived_at !== null, 404);

        $member->update(['status' => Member::STATUS_ACTIVE]);

        return redirect()
            ->route('satellites.members', $satellite)
            ->with('status', "{$member->display_name} restored to the active list.");
    }

    public function destroyMember(Satellite $satellite, Member $member): RedirectResponse
    {
        $this->authorizeSatelliteMemberManagement($satellite, $member);

        $name = $member->display_name;
        $member->delete();

        return redirect()
            ->route('satellites.members', $satellite)
            ->with('status', "{$name} was deleted successfully.");
    }

    public function previewMemberImport(Request $request, Satellite $satellite): RedirectResponse
    {
        $this->authorizeSatelliteMemberManagement($satellite);

        $validator = Validator::make($request->all(), [
            'member_sheet' => [
                'required',
                'file',
                'max:5120',
                function ($attribute, $value, $fail): void {
                    $extension = $value instanceof UploadedFile
                        ? strtolower($value->getClientOriginalExtension())
                        : '';

                    if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
                        $fail('Only CSV and XLSX member sheets can be imported. Save legacy .xls files as .xlsx or .csv first.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'memberImport')
                ->withInput()
                ->with('openImportModal', true);
        }

        try {
            $rows = $this->memberRowsFromUploadedFile($request->file('member_sheet'));
            $preview = $this->memberImportPreview($satellite, $rows);
        } catch (RuntimeException $exception) {
            return back()
                ->with('error', get_class($exception).': '.$exception->getMessage())
                ->with('openImportModal', true);
        }

        session([$this->memberImportSessionKey($satellite) => $preview['validRows']]);

        return redirect()
            ->route('satellites.members', $satellite)
            ->with('memberImportPreview', $preview)
            ->with('openImportModal', true);
    }

    public function importMembers(Satellite $satellite): RedirectResponse
    {
        $this->authorizeSatelliteMemberManagement($satellite);

        $rows = session($this->memberImportSessionKey($satellite), []);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('satellites.members', $satellite)
                ->with('error', 'Upload and validate a member sheet before importing.');
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($satellite, $rows, &$imported, &$skipped): void {
            foreach ($rows as $row) {
                if ($this->memberDuplicateExists($satellite, $row)) {
                    $skipped++;

                    continue;
                }

                Member::create($this->memberPayloadFromImportRow($satellite, $row));
                $imported++;
            }
        });

        session()->forget($this->memberImportSessionKey($satellite));
        $message = $skipped > 0
            ? "{$imported} imported, {$skipped} skipped."
            : "{$imported} members imported successfully.";

        return redirect()
            ->route('satellites.members', $satellite)
            ->with('status', $message);
    }

    public function memberImportTemplate(Satellite $satellite)
    {
        $this->authorizeSatelliteMemberManagement($satellite);

        $filename = Str::slug($satellite->display_name).'-members-import-template.csv';

        return response()->streamDownload(function () use ($satellite): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::MEMBER_IMPORT_HEADERS);
            fputcsv($handle, [
                Member::STATUS_ACTIVE,
                Member::ROLE_MEMBER,
                'Fernandez',
                'Liza',
                'Liza Fernandez',
                'Female',
                'liza@example.com',
                '09171234567',
                'https://facebook.com/liza',
                '2006-08-28',
                '20',
                $satellite->display_name,
                'Area 1',
                $satellite->display_name.' Campus',
                'Leader Name',
                '3',
                now()->year,
                'Elevate',
                '0',
                'Gen 1',
                now()->toDateString(),
                now()->year,
                now()->format('F'),
            ]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function authorizeSatelliteMemberManagement(Satellite $satellite, ?Member $member = null): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canManageSatellite($satellite), 403);

        if ($member !== null) {
            abort_unless((int) $member->satellite_id === (int) $satellite->id, 404);
        }
    }

    private function authorizeSatelliteMemberUpdate(Satellite $satellite, Member $member): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canUpdateSatelliteMembers($satellite), 403);
        abort_unless((int) $member->satellite_id === (int) $satellite->id, 403);
    }

    private function validatedMemberPayload(Request $request, Satellite $satellite, ?Member $member = null): array
    {
        $validator = Validator::make($request->all(), $this->memberValidationRules($satellite, $member), [
            'email_address.email' => 'Please enter a valid email address.',
            'email_address.unique' => 'A member with this email already exists in this satellite.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'status.required' => 'Status is required.',
            'dgroup_role.required' => 'Dgroup role is required.',
            'district.required' => 'District is required.',
            'campus_school.required' => 'Campus/school is required.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Please check the highlighted member fields.',
                    'errors' => $validator->errors(),
                ], 422));
            }

            throw new HttpResponseException(
                back()
                    ->withErrors($validator, 'memberForm')
                    ->withInput()
                    ->with('openMemberFormModal', true)
                    ->with('memberFormAction', $member === null
                        ? route('satellites.members.store', $satellite)
                        : route('satellites.members.update', [$satellite, $member]))
                    ->with('memberFormMode', $member === null ? 'create' : 'edit')
            );
        }

        $validated = $validator->validated();
        if (array_key_exists('leader_member_id', $validated)) {
            $validated['dgroup_leader_name'] = empty($validated['leader_member_id']) && $member && ! $member->leader_member_id ? $member->dgroup_leader_name : null;
        }

        return $this->memberPayloadFromValidated($satellite, $validated);
    }

    private function memberValidationRules(Satellite $satellite, ?Member $member = null): array
    {
        $emailRule = Rule::unique('members', 'email_address')
            ->where(fn ($query) => $query->where('satellite_id', $satellite->id));

        if ($member !== null) {
            $emailRule->ignore($member->id);
        }

        return [
            'status' => ['required', Rule::in(Member::STATUSES)],
            'dgroup_role' => ['required', Rule::in(Member::DGROUP_ROLES)],
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'full_name' => ['nullable', 'string', 'max:240'],
            'gender' => ['nullable', Rule::in(Member::GENDERS)],
            'email_address' => ['nullable', 'email', 'max:255', $emailRule],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'facebook_link' => ['nullable', 'url', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'district' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'campus_school' => ['required', 'string', 'max:180'],
            'leader_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where(fn ($q) => $q->where('satellite_id', $satellite->id)->where('dgroup_role', Member::ROLE_LEADER)->whereNull('archived_at')), Rule::notIn([$member?->id])],
            'dgroup_leader_name' => ['nullable', 'string', 'max:180'],
            'active_disciples' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'year_joined' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'ministry_involvement' => ['nullable', 'string', 'max:180'],
            'weekly_dgroups_led' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'generation' => ['nullable', 'string', 'max:120'],
            'refresh_date' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'month' => ['nullable', Rule::in(self::DASHBOARD_MONTHS)],
        ];
    }

    private function memberPayloadFromValidated(Satellite $satellite, array $validated): array
    {
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));
        $fullName = trim((string) ($validated['full_name'] ?? ''));
        $fullName = $fullName !== '' ? $fullName : trim("{$firstName} {$lastName}");
        $status = Member::normalizeStatus($validated['status'] ?? null);
        $dgroupRole = Member::normalizeDgroupRole($validated['dgroup_role'] ?? null);
        $email = $this->nullableString($validated['email_address'] ?? null);
        $contactNumber = $this->nullableString($validated['contact_number'] ?? null);
        $age = $validated['age'] ?? null;

        if (($age === null || $age === '') && ! empty($validated['birthdate'])) {
            $age = $this->ageFromBirthdate((string) $validated['birthdate']);
        }

        return [
            'satellite_id' => $satellite->id,
            'status' => $status,
            'dgroup_role' => $dgroupRole,
            ...($dgroupRole === Member::ROLE_LEADER ? ['leader_member_id' => null] : (array_key_exists('leader_member_id', $validated) ? ['leader_member_id' => $validated['leader_member_id']] : [])),
            'last_name' => $lastName,
            'first_name' => $firstName,
            'full_name' => $fullName,
            'name' => $fullName,
            'gender' => $this->nullableString($validated['gender'] ?? null),
            'email_address' => $email,
            'email' => $email,
            'contact_number' => $contactNumber,
            'phone' => $contactNumber,
            'facebook_link' => $this->nullableString($validated['facebook_link'] ?? null),
            'birthdate' => $this->nullableString($validated['birthdate'] ?? null),
            'age' => $age === null || $age === '' ? null : (int) $age,
            'district' => $this->nullableString($validated['district'] ?? null) ?: $satellite->display_name,
            'area' => $this->nullableString($validated['area'] ?? null),
            'campus_school' => $this->nullableString($validated['campus_school'] ?? null),
            'dgroup_leader_name' => $dgroupRole === Member::ROLE_LEADER ? null : $this->nullableString($validated['dgroup_leader_name'] ?? null),
            'active_disciples' => $this->nullableInteger($validated['active_disciples'] ?? null),
            'year_joined' => $this->nullableInteger($validated['year_joined'] ?? null),
            'ministry_involvement' => $this->nullableString($validated['ministry_involvement'] ?? null),
            'weekly_dgroups_led' => $this->nullableInteger($validated['weekly_dgroups_led'] ?? null),
            'generation' => $this->nullableString($validated['generation'] ?? null),
            'refresh_date' => $this->nullableString($validated['refresh_date'] ?? null),
            'year' => $this->nullableInteger($validated['year'] ?? null),
            'month' => $this->nullableString($validated['month'] ?? null),
            'role' => $dgroupRole === Member::ROLE_LEADER ? 'leader' : 'member',
        ];
    }

    private function memberFilters(Request $request): array
    {
        return [
            'view' => $request->query('view') === 'archived' ? 'archived' : 'active',
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
            'dgroup_role' => (string) $request->query('dgroup_role', ''),
            'campus_school' => trim((string) $request->query('campus_school', '')),
            'district' => trim((string) $request->query('district', '')),
            'area' => trim((string) $request->query('area', '')),
        ];
    }

    private function hasActiveMemberFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key === 'view') {
                continue;
            }

            if ((string) $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function filteredSatelliteMembersQuery(Satellite $satellite, array $filters, bool $archivedView = false)
    {
        return ($archivedView ? $satellite->allMembers()->archived() : $satellite->members())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('campus_school', 'like', "%{$search}%")
                        ->orWhere('dgroup_leader_name', 'like', "%{$search}%")
                        ->orWhere('email_address', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', Member::normalizeStatus($filters['status'])))
            ->when($filters['dgroup_role'] !== '', fn ($query) => $query->where('dgroup_role', Member::normalizeDgroupRole($filters['dgroup_role'])))
            ->when($filters['campus_school'] !== '', fn ($query) => $query->where('campus_school', $filters['campus_school']))
            ->when($filters['district'] !== '', fn ($query) => $query->where('district', $filters['district']))
            ->when($filters['area'] !== '', fn ($query) => $query->where('area', $filters['area']));
    }

    private function memberFilterOptions(Satellite $satellite, bool $archivedView = false): array
    {
        return [
            'campuses' => $this->distinctMemberValues($satellite, 'campus_school', $archivedView),
            'districts' => $this->distinctMemberValues($satellite, 'district', $archivedView),
            'areas' => $this->distinctMemberValues($satellite, 'area', $archivedView),
        ];
    }

    private function distinctMemberValues(Satellite $satellite, string $column, bool $archivedView = false): array
    {
        return ($archivedView ? $satellite->archivedMembers() : $satellite->members())
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->pluck($column)
            ->unique()
            ->values()
            ->all();
    }

    private function memberSort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');
        $direction = (string) $request->query('direction', 'asc');

        return [
            array_key_exists($sort, self::MEMBER_SORTS) ? $sort : 'name',
            $direction === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        $value = $this->nullableString($value);

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function ageFromBirthdate(string $birthdate): ?int
    {
        try {
            return (int) Carbon::parse($birthdate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function memberRowsFromUploadedFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('The uploaded member sheet could not be read.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The uploaded member sheet could not be read.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->memberRowsFromCsv($contents);
        }

        if ($extension === 'xlsx') {
            try {
                return $this->memberRowsFromValues($this->valuesFromXlsx($contents));
            } catch (RuntimeException $exception) {
                throw new RuntimeException('Only CSV and XLSX member sheets can be imported. '.$exception->getMessage(), previous: $exception);
            }
        }

        throw new RuntimeException('Only CSV and XLSX member sheets can be imported.');
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function memberRowsFromCsv(string $contents): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV could not be opened.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $values = [];

        while (($row = fgetcsv($handle)) !== false) {
            $values[] = $row;
        }

        fclose($handle);

        return $this->memberRowsFromValues($values);
    }

    /**
     * @param  array<int, array<int, string|null>>  $values
     * @return array<int, array<string, string|null>>
     */
    private function memberRowsFromValues(array $values): array
    {
        $headers = null;
        $rows = [];

        foreach ($values as $columns) {
            if ($this->importColumnsAreEmpty($columns)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(
                    fn ($header) => $this->normalizeImportHeader((string) $header),
                    $columns
                );

                continue;
            }

            $row = $this->rowFromImportColumns($headers, $columns);

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        if ($headers === null) {
            throw new RuntimeException('The member sheet does not contain a header row.');
        }

        if ($rows === []) {
            throw new RuntimeException('The member sheet does not contain any importable member rows.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $columns
     * @return array<string, string|null>
     */
    private function rowFromImportColumns(array $headers, array $columns): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $key = $this->importKeyForHeader($header);

            if ($key === null) {
                continue;
            }

            $row[$key] = $this->nullableString($columns[$index] ?? null);
        }

        return $row;
    }

    /**
     * @param  array<int, string|null>  $columns
     */
    private function importColumnsAreEmpty(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($this->nullableString($column) !== null) {
                return false;
            }
        }

        return true;
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return Str::of($header)
            ->lower()
            ->replace(['/', '\\', '-', '_', '.', '#', '?', '(', ')', ':'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function importKeyForHeader(string $header): ?string
    {
        return match ($header) {
            'status' => 'status',
            'dgroup role', 'd group role', 'dgroup roles', 'role' => 'dgroup_role',
            'last name', 'lastname', 'surname' => 'last_name',
            'first name', 'firstname', 'given name' => 'first_name',
            'full name', 'name' => 'full_name',
            'gender', 'sex' => 'gender',
            'email', 'email address', 'email add' => 'email_address',
            'contact', 'contact number', 'phone', 'phone number', 'mobile', 'mobile number' => 'contact_number',
            'facebook', 'facebook link', 'fb', 'fb link' => 'facebook_link',
            'birthdate', 'birthday', 'birth date', 'date of birth' => 'birthdate',
            'age' => 'age',
            'district' => 'district',
            'area', 'landmark area', 'landmark' => 'area',
            'campus', 'school', 'campus school', 'campus or school' => 'campus_school',
            'name of dleader', 'dleader', 'd leader', 'name of d leader', 'name of dgroup leader', 'dgroup leader', 'd group leader' => 'dgroup_leader_name',
            'no of active disciples', 'number of active disciples', 'active disciples', 'active disciple count' => 'active_disciples',
            'year joined', 'joined year' => 'year_joined',
            'ministry involvement', 'ministry' => 'ministry_involvement',
            'how many dgroups do you lead each week', 'weekly dgroups led', 'dgroups led weekly', 'dgroups led', 'dgroup count' => 'weekly_dgroups_led',
            'generation', 'gen' => 'generation',
            'refresh date', 'updated date' => 'refresh_date',
            'year' => 'year',
            'month' => 'month',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array<string, mixed>
     */
    private function memberImportPreview(Satellite $satellite, array $rows): array
    {
        $preview = [
            'totalRows' => count($rows),
            'validCount' => 0,
            'issueCount' => 0,
            'duplicateCount' => 0,
            'validRows' => [],
            'rows' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $messages = $this->validateImportRow($row);
            $duplicate = $messages === [] && $this->memberDuplicateExists($satellite, $row);

            if ($duplicate) {
                $messages[] = 'Duplicate member in this satellite.';
            }

            if ($messages === []) {
                $preview['validRows'][] = $row;
                $preview['validCount']++;
                $state = 'ready';
            } elseif ($duplicate) {
                $preview['duplicateCount']++;
                $state = 'duplicate';
            } else {
                $preview['issueCount']++;
                $state = 'issue';
            }

            if (count($preview['rows']) < 12) {
                $preview['rows'][] = [
                    'number' => $rowNumber,
                    'name' => $this->fullNameFromImportRow($row) ?: '-',
                    'status' => $this->nullableString($row['status'] ?? null) ?: '-',
                    'role' => $this->nullableString($row['dgroup_role'] ?? null) ?: '-',
                    'school' => $this->nullableString($row['campus_school'] ?? null) ?: '-',
                    'state' => $state,
                    'messages' => $messages,
                ];
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<int, string>
     */
    private function validateImportRow(array $row): array
    {
        $errors = [];

        if ($this->fullNameFromImportRow($row) === '') {
            $errors[] = 'Full name or first and last name is required.';
        }

        if ($this->normalizeImportStatus($row['status'] ?? null) === null) {
            $errors[] = 'Status must be Active, New, or Inactive.';
        }

        if ($this->normalizeImportDgroupRole($row['dgroup_role'] ?? null) === null) {
            $errors[] = 'Dgroup Role must be Member or Dgroup Leader.';
        }

        if ($this->nullableString($row['campus_school'] ?? null) === null) {
            $errors[] = 'Campus/School is required.';
        }

        $email = $this->nullableString($row['email_address'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email address is invalid.';
        }

        $facebook = $this->nullableString($row['facebook_link'] ?? null);

        if ($facebook !== null && filter_var($facebook, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Facebook link must be a valid URL.';
        }

        foreach (['age', 'active_disciples', 'year_joined', 'weekly_dgroups_led', 'year'] as $key) {
            $value = $this->nullableString($row[$key] ?? null);

            if ($value !== null && ! ctype_digit($value)) {
                $errors[] = Str::of($key)->replace('_', ' ')->title().' must be a whole number.';
            }
        }

        foreach (['birthdate', 'refresh_date'] as $key) {
            $value = $this->nullableString($row[$key] ?? null);

            if ($value !== null && $this->normalizeImportDate($value) === null) {
                $errors[] = Str::of($key)->replace('_', ' ')->title().' must be a valid date.';
            }
        }

        if (($this->nullableString($row['month'] ?? null) !== null) && $this->normalizeImportMonth($row['month'] ?? null) === null) {
            $errors[] = 'Month must be a valid month name.';
        }

        return $errors;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function memberPayloadFromImportRow(Satellite $satellite, array $row): array
    {
        $fullName = $this->fullNameFromImportRow($row);
        [$firstName, $lastName] = $this->nameParts($fullName);

        return $this->memberPayloadFromValidated($satellite, [
            'status' => $this->normalizeImportStatus($row['status'] ?? null) ?? Member::STATUS_ACTIVE,
            'dgroup_role' => $this->normalizeImportDgroupRole($row['dgroup_role'] ?? null) ?? Member::ROLE_MEMBER,
            'last_name' => $this->nullableString($row['last_name'] ?? null) ?? $lastName ?? '',
            'first_name' => $this->nullableString($row['first_name'] ?? null) ?? $firstName ?? $fullName,
            'full_name' => $fullName,
            'gender' => $this->normalizeImportGender($row['gender'] ?? null),
            'email_address' => $this->nullableString($row['email_address'] ?? null),
            'contact_number' => $this->nullableString($row['contact_number'] ?? null),
            'facebook_link' => $this->nullableString($row['facebook_link'] ?? null),
            'birthdate' => $this->normalizeImportDate($row['birthdate'] ?? null),
            'age' => $this->nullableString($row['age'] ?? null),
            'district' => $this->nullableString($row['district'] ?? null) ?? $satellite->display_name,
            'area' => $this->nullableString($row['area'] ?? null),
            'campus_school' => $this->nullableString($row['campus_school'] ?? null),
            'dgroup_leader_name' => $this->nullableString($row['dgroup_leader_name'] ?? null),
            'active_disciples' => $this->nullableString($row['active_disciples'] ?? null),
            'year_joined' => $this->nullableString($row['year_joined'] ?? null),
            'ministry_involvement' => $this->nullableString($row['ministry_involvement'] ?? null),
            'weekly_dgroups_led' => $this->nullableString($row['weekly_dgroups_led'] ?? null),
            'generation' => $this->nullableString($row['generation'] ?? null),
            'refresh_date' => $this->normalizeImportDate($row['refresh_date'] ?? null),
            'year' => $this->nullableString($row['year'] ?? null),
            'month' => $this->normalizeImportMonth($row['month'] ?? null),
        ]);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function memberDuplicateExists(Satellite $satellite, array $row): bool
    {
        $email = $this->nullableString($row['email_address'] ?? null);
        $fullName = $this->fullNameFromImportRow($row);
        $campus = $this->nullableString($row['campus_school'] ?? null);

        if ($email !== null) {
            return $satellite->members()
                ->where(function ($query) use ($email): void {
                    $query->where('email_address', $email)
                        ->orWhere('email', $email);
                })
                ->exists();
        }

        if ($fullName === '') {
            return false;
        }

        return $satellite->members()
            ->where(function ($query) use ($fullName): void {
                $query->where('full_name', $fullName)
                    ->orWhere('name', $fullName);
            })
            ->when($campus !== null, fn ($query) => $query->where('campus_school', $campus))
            ->exists();
    }

    private function memberImportSessionKey(Satellite $satellite): string
    {
        return 'satellite_'.$satellite->id.'_member_import_rows';
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function fullNameFromImportRow(array $row): string
    {
        $fullName = $this->nullableString($row['full_name'] ?? null);

        if ($fullName !== null) {
            return $fullName;
        }

        return trim(implode(' ', array_filter([
            $this->nullableString($row['first_name'] ?? null),
            $this->nullableString($row['last_name'] ?? null),
        ])));
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function nameParts(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($parts === []) {
            return [null, null];
        }

        if (count($parts) === 1) {
            return [$parts[0], null];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }

    private function normalizeImportStatus(?string $status): ?string
    {
        $status = $this->nullableString($status);

        if ($status === null) {
            return null;
        }

        $status = Str::of($status)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        return in_array($status, Member::STATUSES, true) ? $status : null;
    }

    private function normalizeImportDgroupRole(?string $role): ?string
    {
        $role = $this->nullableString($role);

        if ($role === null) {
            return null;
        }

        $role = Str::of($role)->lower();

        if ($role->contains('leader')) {
            return Member::ROLE_LEADER;
        }

        if ($role->contains('member')) {
            return Member::ROLE_MEMBER;
        }

        return null;
    }

    private function normalizeImportGender(?string $gender): ?string
    {
        $gender = $this->nullableString($gender);

        if ($gender === null) {
            return null;
        }

        $gender = Str::of($gender)->lower()->title()->toString();

        return in_array($gender, Member::GENDERS, true) ? $gender : null;
    }

    private function normalizeImportMonth(?string $month): ?string
    {
        $month = $this->nullableString($month);

        if ($month === null) {
            return null;
        }

        $month = Str::of($month)->lower()->title()->toString();

        return in_array($month, self::DASHBOARD_MONTHS, true) ? $month : null;
    }

    private function normalizeImportDate(mixed $date): ?string
    {
        $date = $this->nullableString($date);

        if ($date === null) {
            return null;
        }

        if (is_numeric($date)) {
            $serial = (int) $date;

            if ($serial > 0 && $serial < 100000) {
                return Carbon::create(1899, 12, 30)->addDays($serial)->toDateString();
            }
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function valuesFromXlsx(string $xlsx): array
    {
        $entries = $this->zipEntries($xlsx);
        $sharedStrings = $this->sharedStrings($entries['xl/sharedStrings.xml'] ?? null);
        $worksheetPath = $this->firstWorksheetPath($entries);
        $worksheetXml = $entries[$worksheetPath] ?? null;

        if ($worksheetXml === null) {
            throw new RuntimeException('The Office file does not contain a readable worksheet.');
        }

        $worksheet = $this->loadXml($worksheetXml);
        $sheetData = $this->firstXmlChild($worksheet, 'sheetData');

        if ($sheetData === null) {
            throw new RuntimeException('The Office worksheet XML could not be parsed.');
        }

        $values = [];

        foreach ($this->xmlChildrenNamed($sheetData, 'row') as $row) {
            $rowValues = [];

            foreach ($this->xmlChildrenNamed($row, 'c') as $cell) {
                $cellReference = (string) ($cell['r'] ?? '');
                $columnIndex = $this->columnIndexFromCellReference($cellReference);
                $rowValues[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($rowValues === []) {
                continue;
            }

            ksort($rowValues);
            $lastColumnIndex = max(array_keys($rowValues));
            $normalizedRow = [];

            for ($columnIndex = 0; $columnIndex <= $lastColumnIndex; $columnIndex++) {
                $normalizedRow[] = $rowValues[$columnIndex] ?? null;
            }

            $values[] = $normalizedRow;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function firstWorksheetPath(array $entries): string
    {
        $workbookXml = $entries['xl/workbook.xml'] ?? null;
        $relsXml = $entries['xl/_rels/workbook.xml.rels'] ?? null;

        if ($workbookXml !== null && $relsXml !== null) {
            $workbook = $this->loadXml($workbookXml);
            $rels = $this->loadXml($relsXml);
            $sheets = $this->firstXmlChild($workbook, 'sheets');
            $sheet = $sheets === null ? null : $this->firstXmlChild($sheets, 'sheet');

            if ($sheet !== null) {
                $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($attributes['id'] ?? '');

                foreach ($this->xmlChildrenNamed($rels, 'Relationship') as $relationship) {
                    if ((string) ($relationship['Id'] ?? '') === $relationshipId) {
                        $target = ltrim((string) ($relationship['Target'] ?? ''), '/');

                        return str_starts_with($target, 'xl/')
                            ? $target
                            : 'xl/'.$target;
                    }
                }
            }
        }

        foreach (array_keys($entries) as $name) {
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        throw new RuntimeException('The Office file does not contain worksheet XML.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }

        $sharedStrings = $this->loadXml($xml);

        $values = [];

        foreach ($this->xmlChildrenNamed($sharedStrings, 'si') as $item) {
            $values[] = $this->stringItemText($item);
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            $inlineString = $this->firstXmlChild($cell, 'is');

            return $inlineString === null ? '' : $this->stringItemText($inlineString);
        }

        $valueNode = $this->firstXmlChild($cell, 'v');
        $value = $valueNode === null ? '' : (string) $valueNode;

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function loadXml(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($element === false) {
            throw new RuntimeException('The Office worksheet XML could not be parsed.');
        }

        return $element;
    }

    /**
     * @return array<int, \SimpleXMLElement>
     */
    private function xmlChildrenNamed(\SimpleXMLElement $element, string $name): array
    {
        $xpathChildren = $element->xpath('./*[local-name() = "'.$name.'"]');

        if (is_array($xpathChildren) && $xpathChildren !== []) {
            return $xpathChildren;
        }

        $children = [];
        $namespaces = array_unique(array_filter(array_merge(
            $element->getDocNamespaces(true),
            $element->getNamespaces(true)
        )));

        foreach ($namespaces as $namespace) {
            foreach ($element->children($namespace) as $childName => $child) {
                if ($childName === $name) {
                    $children[] = $child;
                }
            }
        }

        if ($children !== []) {
            return $children;
        }

        foreach ($element->children() as $childName => $child) {
            if ($childName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function firstXmlChild(\SimpleXMLElement $element, string $name): ?\SimpleXMLElement
    {
        return $this->xmlChildrenNamed($element, $name)[0] ?? null;
    }

    private function stringItemText(\SimpleXMLElement $item): string
    {
        $text = '';

        foreach ($this->xmlChildrenNamed($item, 't') as $textNode) {
            $text .= (string) $textNode;
        }

        foreach ($this->xmlChildrenNamed($item, 'r') as $run) {
            foreach ($this->xmlChildrenNamed($run, 't') as $textNode) {
                $text .= (string) $textNode;
            }
        }

        return $text;
    }

    private function columnIndexFromCellReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(string $zip): array
    {
        $endOfCentralDirectory = strrpos($zip, "PK\x05\x06");

        if ($endOfCentralDirectory === false) {
            throw new RuntimeException('The Office file is not a valid XLSX zip archive.');
        }

        $entryCount = $this->littleEndianUInt16($zip, $endOfCentralDirectory + 10);
        $centralDirectoryOffset = $this->littleEndianUInt32($zip, $endOfCentralDirectory + 16);
        $offset = $centralDirectoryOffset;
        $entries = [];

        for ($i = 0; $i < $entryCount; $i++) {
            if (substr($zip, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $compressionMethod = $this->littleEndianUInt16($zip, $offset + 10);
            $compressedSize = $this->littleEndianUInt32($zip, $offset + 20);
            $fileNameLength = $this->littleEndianUInt16($zip, $offset + 28);
            $extraLength = $this->littleEndianUInt16($zip, $offset + 30);
            $commentLength = $this->littleEndianUInt16($zip, $offset + 32);
            $localHeaderOffset = $this->littleEndianUInt32($zip, $offset + 42);
            $fileName = substr($zip, $offset + 46, $fileNameLength);

            $localFileNameLength = $this->littleEndianUInt16($zip, $localHeaderOffset + 26);
            $localExtraLength = $this->littleEndianUInt16($zip, $localHeaderOffset + 28);
            $dataOffset = $localHeaderOffset + 30 + $localFileNameLength + $localExtraLength;
            $compressedData = substr($zip, $dataOffset, $compressedSize);

            $entries[$fileName] = match ($compressionMethod) {
                0 => $compressedData,
                8 => gzinflate($compressedData),
                default => throw new RuntimeException("Unsupported XLSX compression method {$compressionMethod}."),
            };

            $offset += 46 + $fileNameLength + $extraLength + $commentLength;
        }

        return $entries;
    }

    private function littleEndianUInt16(string $bytes, int $offset): int
    {
        return unpack('v', substr($bytes, $offset, 2))[1];
    }

    private function littleEndianUInt32(string $bytes, int $offset): int
    {
        return unpack('V', substr($bytes, $offset, 4))[1];
    }

    private function satelliteSummaryCards(Satellite $satellite): array
    {
        $leaders = (int) $satellite->leaders_count;
        $members = (int) $satellite->members_count;
        $seekers = (int) $satellite->seekers_count;
        $newMembers = (int) $satellite->new_members_count;

        return [
            [
                'label' => 'DGROUP MEMBERS',
                'total' => $members,
            ],
            [
                'label' => 'DGROUP LEADERS',
                'total' => $leaders,
            ],
            [
                'label' => 'SEEKERS',
                'total' => $seekers,
            ],
            [
                'label' => 'NEW MEMBERS',
                'total' => $newMembers,
            ],
        ];
    }

    private function engagementSummary(Satellite $satellite): array
    {
        $classifiedCounts = EngagementResult::query()
            ->whereHas('member', fn ($query) => $query->where('satellite_id', $satellite->id))
            ->selectRaw('classification, COUNT(*) as total')
            ->groupBy('classification')
            ->pluck('total', 'classification');
        $memberCount = $satellite->members()->count();
        $classifiedTotal = (int) $classifiedCounts->sum();

        return [
            [
                'label' => 'Active',
                'value' => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_ACTIVE] ?? 0) + ($classifiedCounts['High'] ?? 0)),
            ],
            [
                'label' => 'Moderately Active',
                'value' => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE] ?? 0) + ($classifiedCounts['Medium'] ?? 0)),
            ],
            [
                'label' => 'Possible Need for Follow-Up',
                'value' => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP] ?? 0) + ($classifiedCounts['Low'] ?? 0)),
            ],
            [
                'label' => 'Insufficient Information / Not Yet Classified',
                'value' => (int) (($classifiedCounts[EngagementClassificationService::CLASSIFICATION_INSUFFICIENT] ?? 0) + ($classifiedCounts['Inactive'] ?? 0) + max(0, $memberCount - $classifiedTotal)),
            ],
        ];
    }

    private function seekerSummary(Satellite $satellite): array
    {
        $baseQuery = $satellite->seekers();
        $pending = (clone $baseQuery)->whereNull('converted_member_id')->whereNull('archived_at')->where('status', Seeker::STATUS_PENDING)->count();
        $underReview = (clone $baseQuery)->whereNull('converted_member_id')->whereNull('archived_at')->where('status', '!=', Seeker::STATUS_PENDING)->count();
        $converted = (clone $baseQuery)->whereNotNull('converted_member_id')->count();

        return [
            ['label' => 'Pending', 'value' => $pending],
            ['label' => 'Under Review', 'value' => $underReview],
            ['label' => 'Converted', 'value' => $converted],
        ];
    }

    private function recentActivity(Satellite $satellite): array
    {
        return FollowUp::query()
            ->where('satellite_id', $satellite->id)
            ->with(['member', 'seeker'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (FollowUp $followUp): array {
                $target = $followUp->member?->display_name
                    ?? $followUp->seeker?->name
                    ?? 'Unassigned target';

                return [
                    'target' => $target,
                    'status' => $followUp->status,
                    'method' => $followUp->method,
                    'date' => $followUp->follow_up_date?->format('M d, Y') ?? $followUp->created_at?->format('M d, Y'),
                ];
            })
            ->all();
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'satellite' => trim((string) $request->query('satellite', '')),
            'hub' => (string) $request->query('hub', ''),
            'assignment' => (string) $request->query('assignment', ''),
        ];
    }

    private function filteredSatellitesQuery(array $filters, bool $archived = false)
    {
        return ($archived ? Satellite::archived() : Satellite::active())
            ->with('assignedUsers')->withCount(['members as warning_members_count', 'seekers as warning_seekers_count' => fn ($q) => $q->whereNull('archived_at')->whereNull('converted_member_id')])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('hub', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('assignedUsers', function ($query) use ($filters): void {
                            $query->where('name', 'like', '%'.$filters['search'].'%')
                                ->orWhere('email', 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->when($filters['hub'] !== '', fn ($query) => $query->where('hub', $filters['hub']))
            ->when($filters['satellite'] !== '', fn ($query) => $query->where('slug', $filters['satellite']))
            ->when($filters['assignment'] === 'assigned', fn ($query) => $query->whereHas('assignedUsers'))
            ->when($filters['assignment'] === 'unassigned', fn ($query) => $query->doesntHave('assignedUsers'));
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'created');
        $direction = (string) $request->query('direction', 'desc');

        return [
            array_key_exists($sort, self::SORTS) ? $sort : 'created',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'satellite';
        $slug = $baseSlug;
        $counter = 2;

        while (Satellite::withoutGlobalScope('active')->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
