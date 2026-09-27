<?php

namespace App\Http\Controllers;

use App\Models\Satellite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private const ROLES = ['leader', 'staff', 'volunteer', 'member', 'satellite'];
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];
    private const SORTS = [
        'name' => 'name',
        'email' => 'email',
        'role' => 'role',
        'district' => 'district',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $perPage = $this->perPage($request);

        $users = $this->filteredUsersQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $districts = $this->availableDistricts();

        return view('admin.users', [
            'users' => $users,
            'filters' => $filters,
            'roles' => self::ROLES,
            'districts' => $districts,
            'sort' => $sort,
            'direction' => $direction,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
            'totalUsers' => User::count(),
            'activeMembers' => User::where('role', 'member')->count(),
            'totalLeaders' => User::where('role', 'leader')->count(),
            'otherUsers' => User::whereNotIn('role', ['member', 'leader'])->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'district' => ['nullable', Rule::in($this->availableDistricts())],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'district' => $validated['district'] ?? null,
            'password' => Hash::make('password'),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        return view('admin.user-account', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($this->role($user) === 'admin', 404);

        $validated = $request->validate([
            'district' => ['nullable', Rule::in($this->availableDistricts())],
        ]);

        $user->update([
            'district' => $validated['district'] ?? null,
        ]);

        return redirect()
            ->route('admin.users')
            ->with('status', 'User updated successfully.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_action' => ['required', 'in:assign_district'],
            'selection_scope' => ['required', 'in:ids,matching'],
            'ids' => ['array'],
            'ids.*' => ['integer', 'exists:users,id'],
            'district' => ['nullable', Rule::in($this->availableDistricts())],
            'return_to' => ['nullable', 'string'],
        ]);

        $query = $validated['selection_scope'] === 'matching'
            ? $this->filteredUsersQuery($this->filtersFromArray((array) $request->input('filters', [])))
            : User::query()
                ->where('role', '!=', 'admin')
                ->whereKey($validated['ids'] ?? []);

        $users = $query->get();

        if ($users->isEmpty()) {
            return redirect()
                ->to($request->input('return_to', route('admin.users')))
                ->with('status', 'Select at least one user before running a bulk action.');
        }

        $district = $validated['district'] ?? null;

        User::whereKey($users->modelKeys())->update(['district' => $district]);

        return redirect()
            ->to($request->input('return_to', route('admin.users')))
            ->with('status', $district === null ? 'District assignments cleared successfully.' : 'District assigned successfully.');
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        [$sort, $direction] = $this->sort($request);
        $filename = 'users-'.now()->format('Ymd-His').'.csv';

        $query = $this->filteredUsersQuery($filters)
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Role', 'District']);

            $query->chunk(500, function ($users) use ($handle): void {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        str_replace('_', ' ', $user->role ?: 'member'),
                        $user->district ?? 'Unassigned',
                    ]);
                }
            });
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return $this->filtersFromArray($request->query());
    }

    private function filtersFromArray(array $source): array
    {
        return [
            'search' => trim((string) ($source['search'] ?? '')),
            'role' => (string) ($source['role'] ?? ''),
            'district' => (string) ($source['district'] ?? ''),
        ];
    }

    private function filteredUsersQuery(array $filters)
    {
        return User::query()
            ->where('role', '!=', 'admin')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['role'] !== '', fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['district'] !== '', function ($query) use ($filters): void {
                if ($filters['district'] === 'unassigned') {
                    $query->whereNull('district');
                    return;
                }

                $query->where('district', $filters['district']);
            });
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');
        $direction = (string) $request->query('direction', 'asc');

        return [
            array_key_exists($sort, self::SORTS) ? $sort : 'name',
            $direction === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }

    private function role(User $user): string
    {
        return str_replace([' ', '-'], '_', strtolower((string) ($user->role ?: 'member')));
    }

    private function availableDistricts()
    {
        return Satellite::orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
    }
}
