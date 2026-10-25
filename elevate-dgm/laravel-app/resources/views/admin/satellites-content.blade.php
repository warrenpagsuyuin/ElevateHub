<x-status-tabs :current="$archivedView ? 'archived' : 'active'" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $statusTabCounts['active'], 'url' => route('admin.satellites', array_merge(request()->except(['view','page']), ['view' => 'active']))],
            ['key' => 'archived', 'label' => 'Archived', 'count' => $statusTabCounts['archived'], 'url' => route('admin.satellites', array_merge(request()->except('page'), ['view' => 'archived']))],
        ]" />
        <div id="satellite-toast" class="notice" role="status" hidden></div>
        @php
            $satelliteCards = [
                'Total Satellites' => [
                    'value' => $totalSatellites,
                    'url' => route('admin.satellites', array_filter(['month' => $selectedDashboardMonth !== 'Live' ? $selectedDashboardMonth : null, 'year' => $selectedDashboardMonth !== 'Live' ? $selectedDashboardYear : null])),
                ],
                'Active Leaders' => [
                    'value' => $activeLeaders,
                    'url' => route('admin.satellites', array_merge(request()->except('page'), ['sort' => 'leaders', 'direction' => 'desc'])),
                ],
                'Total Members' => [
                    'value' => $activeMembers,
                    'url' => route('admin.satellites', array_merge(request()->except('page'), ['sort' => 'members', 'direction' => 'desc'])),
                ],
                'Open Reports' => [
                    'value' => $openReports,
                    'url' => route('admin.analytics'),
                ],
            ];
        @endphp
        <div class="stats metric-card-grid">
            @foreach ($satelliteCards as $label => $card)
                @php
                    $value = $card['value'];
                @endphp
                <a href="{{ $card['url'] }}" class="card metric-card elevate-stat-card stat-card-link satellites-stat-card" aria-label="{{ $label }}, {{ $value === null ? 'No data for '.$selectedDashboardMonth.' '.$selectedDashboardYear : number_format($value).', '.$periodLabel }}"><div class="info"><span class="label stat-card-label">{{ $label }}</span><div class="stat-card-value-row"><span class="value stat-card-value">{{ $value === null ? '—' : number_format($value) }}</span><span class="card-chevron" aria-hidden="true">›</span></div>@if($value === null)<span class="period-unavailable">No data for {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }}</span>@endif</div></a>
            @endforeach
        </div>

        @php
            $sortUrl = fn (string $column) => route('admin.satellites', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
        @endphp

        <form method="GET" action="{{ route('admin.satellites') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="month" value="{{ $selectedDashboardMonth }}"><input type="hidden" name="year" value="{{ $selectedDashboardYear }}">
            <input type="hidden" name="view" value="{{ $archivedView ? 'archived' : 'active' }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            @if ($filters['satellite'] !== '')
                <input type="hidden" name="satellite" value="{{ $filters['satellite'] }}">
            @endif
            <div class="data-table-field search">
                <label for="satellites-search">Search</label>
                <div class="data-table-search">
                    <input id="satellites-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search satellite, hub, or assigned user" data-debounced-submit>
                    <button type="button" class="data-table-search-clear" data-search-clear aria-label="Clear search">&times;</button>
                </div>
            </div>
            <div class="data-table-field compact">
                <label for="satellites-hub">Hub</label>
                <select id="satellites-hub" name="hub" data-auto-submit>
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub }}" @selected($filters['hub'] === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field compact">
                <label for="satellites-assignment">Assignment</label>
                <select id="satellites-assignment" name="assignment" data-auto-submit>
                    <option value="">Any</option>
                    <option value="assigned" @selected($filters['assignment'] === 'assigned')>Assigned</option>
                    <option value="unassigned" @selected($filters['assignment'] === 'unassigned')>Unassigned</option>
                </select>
            </div>
            <div class="data-table-actions">
                <a class="data-table-link-button dark-action" href="{{ route('admin.satellites.export', request()->query()) }}" aria-label="Export all {{ $satellites->total() }} matching satellites to CSV">Export CSV</a>
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.satellites', request()->except(['search','hub','assignment','page'])) }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="table-card elevate-table-card">
            <h2 class="elevate-table-heading">{{ $archivedView ? 'Archived Satellites' : 'Satellite Summary' }}</h2>
            <div class="data-table-scroll">
            <table id="admin-satellites-table" data-enhanced-table data-table-id="admin-satellites">
                <thead>
                    <tr>
                        <th data-column="satellite" data-label="Satellite" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by satellite. {{ $sortLabel('name') }}">Satellite <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('name') }}</span></a>
                        </th>
                        <th data-column="hub" data-label="Hub" aria-sort="{{ $sort === 'hub' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('hub') }}" aria-label="Sort by hub. {{ $sortLabel('hub') }}">Hub <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('hub') }}</span></a>
                        </th>
                        <th data-column="leaders" data-label="Leaders" aria-sort="{{ $sort === 'leaders' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('leaders') }}" aria-label="Sort by leaders. {{ $sortLabel('leaders') }}">Leaders <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('leaders') }}</span></a>
                        </th>
                        <th data-column="members" data-label="Members" aria-sort="{{ $sort === 'members' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('members') }}" aria-label="Sort by members. {{ $sortLabel('members') }}">Members <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('members') }}</span></a>
                        </th>
                        <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($satellites as $satellite)
                        @php
                            $assignedNames = $satellite->assignedUsers->pluck('name')->filter()->values();
                            $assignedCount = $satellite->assignedUsers->count();
                            $hubUpper = strtoupper((string) $satellite->hub);
                            $hubClass = match ($hubUpper) {
                                'MAIN' => 'hub-main',
                                'NCP' => 'hub-ncp',
                                'ICP' => 'hub-icp',
                                default => '',
                            };
                            $rowValues = $periodValues['rows'][$satellite->id] ?? null;
                        @endphp
                        <tr>
                            <td>
                                @if ($archivedView)
                                    <span class="satellite-name">{{ $satellite->display_name }}</span>
                                    <div>Archived on {{ $satellite->archived_at->timezone('Asia/Manila')->format('M d, Y H:i').' PHT' }}</div>
                                @else
                                    <a href="{{ route('satellites.members', $satellite) }}" class="satellite-name">{{ $satellite->display_name }}</a>
                                @endif
                                <div class="muted">
                                    {{ $assignedCount }} assigned {{ \Illuminate\Support\Str::plural('user', $assignedCount) }}
                                    @if ($assignedNames->isNotEmpty())
                                        · {{ $assignedNames->take(2)->implode(', ') }}{{ $assignedNames->count() > 2 ? ' +'.($assignedNames->count() - 2) : '' }}
                                    @elseif (! $archivedView)
                                        <span class="unassigned-pill">Needs Coordinator</span>
                                    @endif
                                </div>
                            </td>
                            <td><span class="hub-badge {{ $hubClass }}">{{ $satellite->hub ?: 'Unassigned' }}</span></td>
                            <td aria-label="Leaders, {{ $isLiveDashboard ? $satellite->leaders_count : ($rowValues['leaders'] === null ? 'No data for '.$selectedDashboardMonth.' '.$selectedDashboardYear : $rowValues['leaders']) }}, {{ $periodLabel }}">
                                @if (! $archivedView && ($isLiveDashboard || $rowValues['leaders'] !== null))
                                    <a href="{{ route('satellites.members', [$satellite, 'dgroup_role' => 'Dgroup Leader']) }}" class="satellite-count-link">{{ $isLiveDashboard ? $satellite->leaders_count : $rowValues['leaders'] }}</a>
                                @else
                                    {{ $isLiveDashboard ? $satellite->leaders_count : ($rowValues['leaders'] === null ? 'No data for '.$selectedDashboardMonth.' '.$selectedDashboardYear : $rowValues['leaders']) }}
                                @endif
                            </td>
                            <td aria-label="Members, {{ $isLiveDashboard ? $satellite->members_count : ($rowValues['members'] === null ? 'No data for '.$selectedDashboardMonth.' '.$selectedDashboardYear : $rowValues['members']) }}, {{ $periodLabel }}">
                                @if (! $archivedView && ($isLiveDashboard || $rowValues['members'] !== null))
                                    <a href="{{ route('satellites.members', $satellite) }}" class="satellite-count-link">{{ $isLiveDashboard ? $satellite->members_count : $rowValues['members'] }}</a>
                                @else
                                    {{ $isLiveDashboard ? $satellite->members_count : ($rowValues['members'] === null ? 'No data for '.$selectedDashboardMonth.' '.$selectedDashboardYear : $rowValues['members']) }}
                                @endif
                            </td>
                            <td class="data-table-action-col">
                                <div class="data-table-row-menu">
                                    <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $satellite->display_name }}"><span aria-hidden="true">&#8942;</span></button>
                                    <div class="data-table-menu" role="menu">
                                        @if ($archivedView)
                                        <a href="{{ route('admin.satellites.archived-details', $satellite->slug) }}" role="menuitem">View details</a>
                                        <form method="POST" action="{{ route('admin.satellites.restore', $satellite->slug) }}" data-satellite-action data-confirm-message="Restore {{ $satellite->display_name }}? Its pages and counts will return to the dashboard.">
                                            @csrf
                                            <button type="submit" role="menuitem">Restore</button>
                                        </form>
                                        @else
                                        <a href="{{ route('satellites.members', $satellite) }}" role="menuitem">Open satellite directory</a>
                                        <a href="{{ route('admin.members', ['search' => $satellite->display_name]) }}" role="menuitem">View members</a>
                                        <a href="{{ route('admin.seekers', ['district' => $satellite->display_name]) }}" role="menuitem">View seekers</a>
                                        <a href="{{ route('satellites.engagement.index', $satellite) }}" role="menuitem">View engagement</a>
                                        <a href="{{ route('satellites.follow-ups.index', $satellite) }}" role="menuitem">View follow-ups</a>
                                        <a href="{{ route('admin.users', ['district' => $satellite->display_name]) }}" role="menuitem">View assigned users</a>
                                        <a class="dark-action" href="{{ route('admin.satellites.export', array_merge(request()->only(['month','year','view']), ['search' => $satellite->display_name])) }}" role="menuitem">Export satellite row</a>
                                        <form method="POST" action="{{ route('admin.satellites.destroy', $satellite) }}" data-satellite-action data-confirm-message="Archive {{ $satellite->display_name }}? It will be hidden from the dashboard and its history will be kept. @if ($satellite->warning_members_count || $satellite->warning_seekers_count || $satellite->assignedUsers->count() || $satellite->assigned_user_id) This satellite still has {{ $satellite->warning_members_count }} members, {{ $satellite->warning_seekers_count }} seekers, and {{ $satellite->assignedUsers->pluck('id')->push($satellite->assigned_user_id)->filter()->unique()->count() }} assigned users. Their satellite access will be locked. @endif">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="danger" role="menuitem">Archive satellite</button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                @if (array_filter($filters))
                                    <div class="data-table-empty-card">
                                        <strong>No matches found.</strong>
                                        <span>No satellites match the current search or filters.</span>
                                        <a class="data-table-link-button" href="{{ route('admin.satellites', request()->except(['search','hub','assignment','page'])) }}">Clear</a>
                                    </div>
                                @else
                                    <div class="data-table-empty-card">
                                        <strong>No satellites yet.</strong>
                                        <span>Create a satellite dashboard to start tracking leaders, members, and reports.</span>
                                        <button type="button" class="data-table-button btn-primary" data-open-satellite-modal>Create satellite</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $satellites, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
        </section>
