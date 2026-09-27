<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminDashboardRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_views_render_from_laravel_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $routes = [
            '/admin/dashboard' => 'Dashboard',
            '/admin/satellites' => 'Satellites Dashboard',
            '/admin/members' => 'Members Dashboard',
            '/admin/seekers' => 'Seekers Dashboard',
            '/admin/users' => 'Users Dashboard',
            '/admin/reports' => 'Reports Dashboard',
            '/admin/analytics' => 'Analytics Dashboard',
        ];

        foreach ($routes as $uri => $heading) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk()
                ->assertSee($heading);
        }
    }

    public function test_legacy_dashboard_php_urls_redirect_to_laravel_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $legacyRoutes = [
            '/admin/AdminDashboard.php' => '/admin/dashboard',
            '/admin/SatellitesDashboard.php' => '/admin/satellites',
            '/admin/MembersDashboard.php' => '/admin/members',
            '/admin/SeekersDashboard.php' => '/admin/seekers',
            '/admin/UsersDashboard.php' => '/admin/users',
            '/admin/ReportsDashboard.php' => '/admin/reports',
            '/admin/AnalyticsDashboard.php' => '/admin/analytics',
        ];

        foreach ($legacyRoutes as $legacyUri => $newUri) {
            $this->actingAs($admin)
                ->get($legacyUri)
                ->assertRedirect($newUri);
        }
    }

    public function test_admin_can_create_a_satellite_dashboard_with_an_initial_district_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);

        $this->actingAs($admin)
            ->post(route('admin.satellites.store'), [
                'name' => 'Santolan',
                'hub' => 'NCP',
                'initial_user_id' => $assignedUser->id,
            ])
            ->assertRedirect(route('admin.satellites'));

        $this->assertDatabaseHas('satellites', [
            'name' => 'Santolan',
            'slug' => 'santolan',
            'hub' => 'NCP',
            'assigned_user_id' => null,
            'created_by_id' => $admin->id,
        ]);
        $this->assertSame('Santolan', $assignedUser->refresh()->district);

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('Santolan')
            ->assertSee('1 assigned user');
    }

    public function test_satellite_form_uses_fixed_hub_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Warren Pagsuyuin', 'role' => 'member']);

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('NCP')
            ->assertSee('ICP')
            ->assertSee('MAIN')
            ->assertDontSee('Warren Pagsuyuin - member');
    }

    public function test_satellites_dashboard_only_shows_created_satellites(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['name' => 'Warren Pagsuyuin', 'role' => 'satellite']);

        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('Pasig')
            ->assertSee('1 assigned user')
            ->assertSee('Total Satellites')
            ->assertDontSee('Rosario')
            ->assertDontSee('Maybunga')
            ->assertDontSee('Kapasigan')
            ->assertDontSee('Manggahan')
            ->assertDontSee('Pinagbuhatan')
            ->assertDontSee('Biringan');
    }

    public function test_admin_analytics_page_uses_plotly_api_backed_shell(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Analytics Dashboard')
            ->assertSee(route('admin.analytics.data'), false)
            ->assertSee('plotly-2.35.2.min.js')
            ->assertSee('analytics-sections')
            ->assertDontSee('Site Growth')
            ->assertDontSee('Attendance')
            ->assertDontSee('Membership Growth')
            ->assertSee('Engagement Classification')
            ->assertSee(route('admin.engagement.index'), false)
            ->assertDontSee('Top Performing Satellites');
    }

    public function test_admin_analytics_data_endpoint_returns_metric_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.analytics.data'))
            ->assertOk()
            ->assertJsonStructure([
                'MAIN' => [
                    'DLEADERS' => ['labels', '2024', '2025'],
                    'DGROUPS' => ['labels', '2024', '2025'],
                    'DMEMBERS' => ['labels', '2024', '2025'],
                ],
                'NCP' => [
                    'DLEADERS' => ['labels', '2024', '2025'],
                    'DGROUPS' => ['labels', '2024', '2025'],
                    'DMEMBERS' => ['labels', '2024', '2025'],
                ],
                'ICP' => [
                    'DLEADERS' => ['labels', '2024', '2025'],
                    'DGROUPS' => ['labels', '2024', '2025'],
                    'DMEMBERS' => ['labels', '2024', '2025'],
                ],
            ]);

        $analyticsData = $response->json();

        $this->assertSame('The Hub', $analyticsData['MAIN']['DLEADERS']['labels'][0]);
        $this->assertSame(77, $analyticsData['MAIN']['DLEADERS']['2024'][0]);
        $this->assertSame(67, $analyticsData['MAIN']['DLEADERS']['2025'][0]);
    }

    public function test_admin_dashboard_shows_movement_wide_summary_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $mainSatellite = Satellite::create([
            'name' => 'Main Hub',
            'slug' => 'main-hub',
            'hub' => 'MAIN',
            'leaders_count' => 3,
            'members_count' => 12,
            'created_by_id' => $admin->id,
        ]);
        $ncpSatellite = Satellite::create([
            'name' => 'NCP Hub',
            'slug' => 'ncp-hub',
            'hub' => 'NCP',
            'leaders_count' => 2,
            'members_count' => 8,
            'created_by_id' => $admin->id,
        ]);
        $icpSatellite = Satellite::create([
            'name' => 'ICP Hub',
            'slug' => 'icp-hub',
            'hub' => 'ICP',
            'leaders_count' => 1,
            'members_count' => 4,
            'created_by_id' => $admin->id,
        ]);

        Member::create(['name' => 'Leader One', 'satellite_id' => $mainSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Dgroup Leader', 'full_name' => 'Leader One', 'email_address' => 'leader1@example.com']);
        Member::create(['name' => 'Leader Two', 'satellite_id' => $mainSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Dgroup Leader', 'full_name' => 'Leader Two', 'email_address' => 'leader2@example.com']);
        Member::create(['name' => 'Member One', 'satellite_id' => $mainSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Member', 'full_name' => 'Member One', 'email_address' => 'member1@example.com']);
        Member::create(['name' => 'Member Two', 'satellite_id' => $mainSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Member', 'full_name' => 'Member Two', 'email_address' => 'member2@example.com']);
        Member::create(['name' => 'Leader Three', 'satellite_id' => $ncpSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Dgroup Leader', 'full_name' => 'Leader Three', 'email_address' => 'leader3@example.com']);
        Member::create(['name' => 'Member Three', 'satellite_id' => $ncpSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Member', 'full_name' => 'Member Three', 'email_address' => 'member3@example.com']);
        Member::create(['name' => 'Leader Four', 'satellite_id' => $icpSatellite->id, 'status' => 'Active', 'dgroup_role' => 'Dgroup Leader', 'full_name' => 'Leader Four', 'email_address' => 'leader4@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee(now()->format('F j, Y'))
            ->assertSee('ELEVATE MOVEMENT WIDE SUMMARY')
            ->assertSee('DGROUP LEADERS')
            ->assertSee('4')
            ->assertSee('DGROUPS')
            ->assertSee('3')
            ->assertSee('DGROUP MEMBERS')
            ->assertSee('24')
            ->assertSee('CAMPUSES')
            ->assertSee('3')
            ->assertSee('MAIN')
            ->assertSee('NCP')
            ->assertSee('ICP')
            ->assertDontSee('Total Satellites')
            ->assertDontSee('Total Members')
            ->assertDontSee('Total Leaders')
            ->assertDontSee('Total Users')
            ->assertDontSee('Satellite Summary');
    }

    public function test_admin_dashboard_counts_active_dgroups_from_leader_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create([
            'name' => 'Main Hub',
            'slug' => 'main-hub',
            'hub' => 'MAIN',
            'leaders_count' => 0,
            'members_count' => 0,
            'created_by_id' => $admin->id,
        ]);

        foreach (range(1, 3) as $index) {
            Member::create([
                'name' => "Leader {$index}",
                'satellite_id' => $satellite->id,
                'status' => 'Active',
                'dgroup_role' => 'Dgroup Leader',
                'full_name' => "Leader {$index}",
                'email_address' => "leader{$index}@example.com",
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('DGROUP LEADERS')
            ->assertSee('3')
            ->assertSee('DGROUPS')
            ->assertSee('3');
    }

    public function test_new_satellite_is_not_auto_assigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Warren Pagsuyuin', 'role' => 'member']);

        $this->actingAs($admin)
            ->post(route('admin.satellites.store'), [
                'name' => 'Anonas',
                'hub' => 'MAIN',
            ])
            ->assertRedirect(route('admin.satellites'));

        $this->assertDatabaseHas('satellites', [
            'name' => 'Anonas',
            'assigned_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('Anonas')
            ->assertSee('0 assigned users')
            ->assertDontSee('Assigned to Warren Pagsuyuin');
    }

    public function test_members_dashboard_uses_created_satellites_not_examples(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'members_count' => 12,
            'new_members_count' => 3,
            'created_by_id' => $admin->id,
        ]);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'members_count' => 8,
            'new_members_count' => 2,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members'))
            ->assertOk()
            ->assertSee('Members Dashboard')
            ->assertSee('Anonas')
            ->assertSee('Pasig')
            ->assertSee('20')
            ->assertSee('5')
            ->assertDontSee('Rosario')
            ->assertDontSee('Maybunga')
            ->assertDontSee('Kapasigan')
            ->assertDontSee('Manggahan')
            ->assertDontSee('Pinagbuhatan')
            ->assertDontSee('Biringan');
    }

    public function test_admin_tables_remove_saved_views_and_keep_independent_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'members_count' => 12,
            'new_members_count' => 3,
            'created_by_id' => $admin->id,
        ]);
        User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@elevate.org',
            'role' => 'leader',
            'district' => $satellite->name,
        ]);
        Seeker::create([
            'name' => 'Ana Reyes',
            'email' => 'ana.seeker@example.com',
            'assigned_district' => $satellite->name,
            'assigned_satellite_slug' => $satellite->slug,
            'status' => Seeker::STATUS_PENDING,
        ]);

        foreach ([route('admin.satellites'), route('admin.members'), route('admin.seekers'), route('admin.users'), route('admin.reports')] as $route) {
            $this->actingAs($admin)
                ->get($route)
                ->assertOk()
                ->assertSee('data-column-visibility', false)
                ->assertSee('Export CSV')
                ->assertSee('aria-haspopup="menu"', false)
                ->assertDontSee('data-saved-views', false)
                ->assertDontSee('Save view')
                ->assertDontSee('Saved Views');
        }
    }

    public function test_members_dashboard_removed_member_range_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'members_count' => 7,
            'new_members_count' => 2,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members'))
            ->assertOk()
            ->assertSee('Search satellites...')
            ->assertSee('View members')
            ->assertSee('Export satellite members')
            ->assertDontSee('Min members')
            ->assertDontSee('Max members');
    }

    public function test_admin_satellite_pages_use_created_satellites_not_examples(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'leaders_count' => 2,
            'members_count' => 12,
            'seekers_count' => 4,
            'new_members_count' => 3,
            'created_by_id' => $admin->id,
        ]);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'leaders_count' => 1,
            'members_count' => 8,
            'seekers_count' => 2,
            'new_members_count' => 2,
            'created_by_id' => $admin->id,
        ]);
        Seeker::create([
            'name' => 'Ana Reyes',
            'email' => 'ana.seeker@example.com',
            'assigned_district' => 'Anonas',
            'assigned_satellite_slug' => 'anonas',
            'status' => 'Pending',
        ]);
        Seeker::create([
            'name' => 'Paolo Cruz',
            'email' => 'paolo.seeker@example.com',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Successful',
        ]);

        foreach ([route('admin.seekers'), route('admin.reports')] as $route) {
            $this->actingAs($admin)
                ->get($route)
                ->assertOk()
                ->assertSee('Anonas')
                ->assertSee('Pasig')
                ->assertDontSee('Rosario')
                ->assertDontSee('Maybunga')
                ->assertDontSee('Kapasigan')
                ->assertDontSee('Manggahan')
                ->assertDontSee('Pinagbuhatan')
                ->assertDontSee('Biringan');
        }

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Anonas')
            ->assertDontSee('Pasig')
            ->assertSee('ELEVATE MOVEMENT WIDE SUMMARY');

        $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertDontSee('Anonas')
            ->assertSee('Analytics Dashboard');
    }

    public function test_satellite_names_display_in_consistent_title_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'MAIN ORTIGAS',
            'slug' => 'main-ortigas',
            'created_by_id' => $admin->id,
        ]);
        Seeker::create([
            'name' => 'Title Case Seeker',
            'email' => 'title.case@example.com',
            'assigned_district' => 'Main Ortigas',
            'assigned_satellite_slug' => 'main-ortigas',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('Main Ortigas')
            ->assertDontSee('MAIN ORTIGAS');

        $this->actingAs($admin)
            ->get(route('admin.seekers'))
            ->assertOk()
            ->assertSee('Main Ortigas')
            ->assertDontSee('MAIN ORTIGAS');
    }

    public function test_only_the_assigned_user_can_open_satellite_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $otherUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Santolan',
            'slug' => 'santolan',
            'leaders_count' => 37,
            'members_count' => 104,
            'seekers_count' => 29,
            'new_members_count' => 46,
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($assignedUser)
            ->get(route('satellites.dashboard', $satellite))
            ->assertOk()
            ->assertSee('Santolan Dashboard')
            ->assertSee('SANTOLAN SATELLITE SUMMARY')
            ->assertSee('DGROUP MEMBERS')
            ->assertSee('DGROUP LEADERS')
            ->assertSee('SEEKERS')
            ->assertSee('NEW MEMBERS')
            ->assertSee('104')
            ->assertSee('37')
            ->assertSee('29')
            ->assertSee('46')
            ->assertSee('month-dropdown', false)
            ->assertSee(now()->format('F'))
            ->assertSee('January')
            ->assertSee('December')
            ->assertDontSee('DGROUP TOTAL')
            ->assertDontSee('summary-breakdown', false);

        $this->actingAs($otherUser)
            ->get(route('satellites.dashboard', $satellite))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('satellites.dashboard', $satellite))
            ->assertForbidden();
    }

    public function test_assigned_satellite_user_can_open_seekers_page_for_their_satellite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'seekers_count' => 1,
            'members_count' => 10,
            'leaders_count' => 2,
            'new_members_count' => 1,
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        Seeker::create([
            'name' => 'Liza Fernandez',
            'email' => 'liza.f@gmail.com',
            'school' => 'Pasig City Science HS',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Pending',
        ]);
        Seeker::create([
            'name' => 'Unassigned Seeker',
            'email' => 'unassigned.seeker@example.com',
            'assigned_district' => null,
            'assigned_satellite_slug' => null,
            'status' => 'Pending',
        ]);

        $this->actingAs($assignedUser)
            ->get(route('satellites.seekers', $satellite))
            ->assertOk()
            ->assertSee('Seekers')
            ->assertSee('Pasig')
            ->assertSee('Liza Fernandez')
            ->assertSee('liza.f@gmail.com')
            ->assertSee('Pending')
            ->assertDontSee('Sync Google Sheet')
            ->assertDontSee('Unassigned Seeker');
    }

    public function test_assigned_satellite_user_can_open_members_page_for_their_satellite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'seekers_count' => 3,
            'members_count' => 7,
            'leaders_count' => 2,
            'new_members_count' => 2,
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $otherSatellite = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'assigned_user_id' => User::factory()->create(['role' => 'satellite'])->id,
            'created_by_id' => $admin->id,
        ]);
        Member::create([
            'satellite_id' => $satellite->id,
            'name' => 'Liza Fernandez',
            'full_name' => 'Liza Fernandez',
            'first_name' => 'Liza',
            'last_name' => 'Fernandez',
            'status' => Member::STATUS_ACTIVE,
            'dgroup_role' => Member::ROLE_MEMBER,
            'role' => 'member',
            'gender' => 'Female',
            'email_address' => 'liza.f@example.com',
            'campus_school' => 'Pasig City Science HS',
            'district' => 'Pasig',
            'area' => 'Kapitolyo',
            'dgroup_leader_name' => 'Warren Pagsuyuin',
            'active_disciples' => 3,
            'year_joined' => 2024,
            'ministry_involvement' => 'Elevate',
            'weekly_dgroups_led' => 0,
            'generation' => 'Gen 1',
            'refresh_date' => '2026-08-28',
            'year' => 2026,
            'month' => 'August',
        ]);
        Member::create([
            'satellite_id' => $satellite->id,
            'name' => 'Warren Pagsuyuin',
            'full_name' => 'Warren Pagsuyuin',
            'first_name' => 'Warren',
            'last_name' => 'Pagsuyuin',
            'status' => Member::STATUS_ACTIVE,
            'dgroup_role' => Member::ROLE_LEADER,
            'role' => 'leader',
            'campus_school' => 'Rizal High School',
            'district' => 'Pasig',
        ]);
        Member::create([
            'satellite_id' => $otherSatellite->id,
            'name' => 'Hidden Anonas Member',
            'full_name' => 'Hidden Anonas Member',
            'first_name' => 'Hidden',
            'last_name' => 'Member',
            'status' => Member::STATUS_ACTIVE,
            'dgroup_role' => Member::ROLE_MEMBER,
            'campus_school' => 'Anonas Campus',
            'district' => 'Anonas',
        ]);

        $this->actingAs($assignedUser)
            ->get(route('satellites.members', $satellite))
            ->assertOk()
            ->assertSee('Members')
            ->assertSee('2 of 2 members')
            ->assertSee('Upload Sheet')
            ->assertSee('Add Member')
            ->assertSee('Search member, leader, campus, email, or contact number')
            ->assertSee('Liza Fernandez')
            ->assertSee('Dgroup Leader')
            ->assertSee('Warren Pagsuyuin')
            ->assertSee('member-details-button', false)
            ->assertSee('data-member-details', false)
            ->assertSee('data-member-edit', false)
            ->assertSee('data-open-member-delete', false)
            ->assertSee('aria-label="View details for Liza Fernandez"', false)
            ->assertSee('data-modal="member-details"', false)
            ->assertSee('Member details')
            ->assertSee('Last Name')
            ->assertSee('First Name')
            ->assertSee('No. of Active Disciples')
            ->assertSee('How many Dgroups do you lead each week?')
            ->assertSee('Refresh Date')
            ->assertDontSee('Hidden Anonas Member');

        $this->actingAs($assignedUser)
            ->get(route('satellites.members', ['satellite' => $satellite, 'search' => 'liza']))
            ->assertOk()
            ->assertSee('1 of 2 members')
            ->assertSee('Liza Fernandez')
            ->assertDontSee('aria-label="View details for Warren Pagsuyuin"', false);
    }

    public function test_assigned_satellite_user_can_create_update_and_delete_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);

        $payload = [
            'status' => Member::STATUS_NEW,
            'dgroup_role' => Member::ROLE_MEMBER,
            'last_name' => 'Fernandez',
            'first_name' => 'Liza',
            'full_name' => 'Liza Fernandez',
            'gender' => 'Female',
            'email_address' => 'liza.fernandez@example.com',
            'contact_number' => '09171234567',
            'facebook_link' => 'https://example.com/liza',
            'birthdate' => '2006-08-28',
            'district' => 'Pasig',
            'area' => 'Kapitolyo',
            'campus_school' => 'Pasig City Science HS',
            'dgroup_leader_name' => 'Warren Pagsuyuin',
            'active_disciples' => 3,
            'year_joined' => 2024,
            'ministry_involvement' => 'Elevate',
            'weekly_dgroups_led' => 0,
            'generation' => 'Gen 1',
            'refresh_date' => '2026-08-28',
            'year' => 2026,
            'month' => 'August',
        ];

        $this->actingAs($assignedUser)
            ->post(route('satellites.members.store', $satellite), $payload)
            ->assertRedirect(route('satellites.members', $satellite));

        $member = Member::where('email_address', 'liza.fernandez@example.com')->firstOrFail();

        $this->assertSame($satellite->id, $member->satellite_id);
        $this->assertSame('Liza Fernandez', $member->full_name);
        $this->assertSame(1, $satellite->refresh()->members_count);
        $this->assertSame(1, $satellite->new_members_count);

        $this->actingAs($assignedUser)
            ->patch(route('satellites.members.update', [$satellite, $member]), array_merge($payload, [
                'status' => Member::STATUS_ACTIVE,
                'dgroup_role' => Member::ROLE_LEADER,
                'dgroup_leader_name' => 'Should be cleared',
                'weekly_dgroups_led' => 2,
            ]))
            ->assertRedirect(route('satellites.members', $satellite));

        $member->refresh();

        $this->assertSame(Member::ROLE_LEADER, $member->dgroup_role);
        $this->assertNull($member->dgroup_leader_name);
        $this->assertSame('leader', $member->role);
        $this->assertSame(1, $satellite->refresh()->leaders_count);
        $this->assertSame(0, $satellite->new_members_count);

        $this->actingAs($assignedUser)
            ->delete(route('satellites.members.destroy', [$satellite, $member]))
            ->assertRedirect(route('satellites.members', $satellite));

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertSame(0, $satellite->refresh()->members_count);
    }

    public function test_satellite_member_management_is_scoped_to_the_assigned_satellite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $otherUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $otherSatellite = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'assigned_user_id' => $otherUser->id,
            'created_by_id' => $admin->id,
        ]);
        $otherMember = Member::create([
            'satellite_id' => $otherSatellite->id,
            'name' => 'Anonas Member',
            'full_name' => 'Anonas Member',
            'first_name' => 'Anonas',
            'last_name' => 'Member',
            'status' => Member::STATUS_ACTIVE,
            'dgroup_role' => Member::ROLE_MEMBER,
            'campus_school' => 'Anonas Campus',
            'district' => 'Anonas',
        ]);

        $this->actingAs($otherUser)
            ->post(route('satellites.members.store', $satellite), [
                'status' => Member::STATUS_ACTIVE,
                'dgroup_role' => Member::ROLE_MEMBER,
                'last_name' => 'Blocked',
                'first_name' => 'User',
                'district' => 'Pasig',
                'campus_school' => 'Pasig Campus',
            ])
            ->assertForbidden();

        $this->actingAs($assignedUser)
            ->patch(route('satellites.members.update', [$satellite, $otherMember]), [
                'status' => Member::STATUS_ACTIVE,
                'dgroup_role' => Member::ROLE_MEMBER,
                'last_name' => 'Blocked',
                'first_name' => 'User',
                'district' => 'Pasig',
                'campus_school' => 'Pasig Campus',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('members', [
            'first_name' => 'User',
            'last_name' => 'Blocked',
            'satellite_id' => $satellite->id,
        ]);
    }

    public function test_assigned_satellite_user_can_preview_and_import_member_sheet(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $csv = implode("\n", [
            'Status,Dgroup Role,Last Name,First Name,Full Name,Gender,Email Address,Contact Number,Facebook Link,Birthdate,Age,District,Area,Campus/School,Name of DLeader,No. of Active Disciples,Year Joined,Ministry Involvement,How many Dgroups do you lead each week?,Generation,Refresh Date,Year,Month',
            'Active,Member,Fernandez,Liza,Liza Fernandez,Female,liza.import@example.com,09171234567,https://example.com/liza,2006-08-28,20,Pasig,Kapitolyo,Pasig City Science HS,Warren Pagsuyuin,3,2024,Elevate,0,Gen 1,2026-08-28,2026,August',
            'New,Dgroup Leader,Dizon,Carlo,Carlo Dizon,Male,carlo.import@example.com,09171230000,https://example.com/carlo,2000-01-01,26,Pasig,Ugong,Rizal High School,,5,2023,Elevate,2,Gen 2,2026-08-28,2026,August',
        ]);

        $this->actingAs($assignedUser)
            ->post(route('satellites.members.import-preview', $satellite), [
                'member_sheet' => UploadedFile::fake()->createWithContent('pasig-members.csv', $csv),
            ])
            ->assertRedirect(route('satellites.members', $satellite))
            ->assertSessionHas('memberImportPreview', function (array $preview): bool {
                return $preview['validCount'] === 2
                    && $preview['issueCount'] === 0
                    && $preview['duplicateCount'] === 0;
            });

        $this->actingAs($assignedUser)
            ->post(route('satellites.members.import', $satellite))
            ->assertRedirect(route('satellites.members', $satellite));

        $this->assertDatabaseHas('members', [
            'satellite_id' => $satellite->id,
            'full_name' => 'Liza Fernandez',
            'email_address' => 'liza.import@example.com',
            'dgroup_leader_name' => 'Warren Pagsuyuin',
        ]);
        $this->assertDatabaseHas('members', [
            'satellite_id' => $satellite->id,
            'full_name' => 'Carlo Dizon',
            'dgroup_role' => Member::ROLE_LEADER,
            'role' => 'leader',
        ]);
        $this->assertSame(2, $satellite->refresh()->members_count);
        $this->assertSame(1, $satellite->leaders_count);
    }

    public function test_assigned_satellite_user_can_preview_namespaced_xlsx_member_sheet(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $xlsx = $this->namespacedXlsxFixture([
            ['Status', 'Dgroup Role', 'Last Name', 'First Name', 'Full Name', 'Gender', 'Email Address', 'Campus/School', 'Name of DLeader', 'Refresh Date', 'Year', 'Month'],
            ['Active', 'Member', 'Reyes', 'Ana', 'Ana Reyes', 'Female', 'ana.xlsx@example.com', 'Pasig City Science HS', 'Warren Pagsuyuin', '46262', '2026', 'August'],
        ]);

        $response = $this->from(route('satellites.members', $satellite))
            ->actingAs($assignedUser)
            ->post(route('satellites.members.import-preview', $satellite), [
                'member_sheet' => UploadedFile::fake()->createWithContent('pasig-members.xlsx', $xlsx),
            ]);


        $response
            ->assertRedirect(route('satellites.members', $satellite))
            ->assertSessionHas('memberImportPreview', function (array $preview): bool {
                return $preview['validCount'] === 1
                    && $preview['issueCount'] === 0
                    && $preview['rows'][0]['name'] === 'Ana Reyes';
            });

        $this->actingAs($assignedUser)
            ->post(route('satellites.members.import', $satellite))
            ->assertRedirect(route('satellites.members', $satellite));

        $this->assertDatabaseHas('members', [
            'satellite_id' => $satellite->id,
            'full_name' => 'Ana Reyes',
            'email_address' => 'ana.xlsx@example.com',
            'refresh_date' => '2026-08-28',
        ]);
    }

    public function test_admin_can_sync_seekers_from_google_sheet_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'created_by_id' => $admin->id,
        ]);

        config(['services.google_sheets.seekers_csv_url' => 'https://example.test/seekers.csv']);
        Http::fake([
            'https://example.test/seekers.csv' => Http::response(
                "Name,Email,School,Assigned District,Status\nRonnie Aquino,ronnie.a@gmail.com,Kapitolyo National HS,Pasig,Successful\n",
                200
            ),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'Ronnie Aquino',
            'email' => 'ronnie.a@gmail.com',
            'school' => 'Kapitolyo National HS',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Successful',
        ]);
        $this->assertSame(1, Satellite::where('slug', 'pasig')->firstOrFail()->seekers_count);
    }

    public function test_google_sheet_sync_accepts_first_last_name_and_email_address_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config(['services.google_sheets.seekers_csv_url' => 'https://example.test/placement.csv']);
        Http::fake([
            'https://example.test/placement.csv' => Http::response(
                "First Name,Last Name,Email address,School,DM Status\nFirst1,Last1,user1@example.com,PLP,Successful\n",
                200
            ),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'First1 Last1',
            'email' => 'user1@example.com',
            'school' => 'PLP',
            'status' => 'Successful',
        ]);
    }

    public function test_google_sheet_sync_keeps_duplicate_email_rows_separate_by_sheet_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config(['services.google_sheets.seekers_csv_url' => 'https://example.test/placement.csv']);
        Http::fake([
            'https://example.test/placement.csv' => Http::response(
                "Name,Email,School,Status\nFirst10 Last10,user10@example.com,PLP,Successful\nPapa J,user10@example.com,PLP,Successful\n",
                200
            ),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertSame(2, Seeker::where('email', 'user10@example.com')->count());
        $this->assertDatabaseHas('seekers', [
            'source_key' => 'google-sheet-row-2',
            'name' => 'First10 Last10',
            'email' => 'user10@example.com',
        ]);
        $this->assertDatabaseHas('seekers', [
            'source_key' => 'google-sheet-row-3',
            'name' => 'Papa J',
            'email' => 'user10@example.com',
        ]);
    }

    public function test_google_sheet_sync_uses_column_before_last_name_when_first_name_header_is_corrupt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config(['services.google_sheets.seekers_csv_url' => 'https://example.test/placement.csv']);
        Http::fake([
            'https://example.test/placement.csv' => Http::response(
                "Miguel Santos Andrea Reyes Joshua Cruz,Last Name,Email address,School,Status\nFirst1,Last1,user1@example.com,PLP,Successful\n",
                200
            ),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'First1 Last1',
            'email' => 'user1@example.com',
        ]);
    }

    public function test_google_sheet_sync_reads_office_file_through_authenticated_csv_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config([
            'services.google_sheets.seekers_csv_url' => null,
            'services.google_sheets.seekers_sheet_id' => 'office-file-id',
            'services.google_sheets.seekers_gid' => '0',
            'services.google_sheets.service_account_json' => 'testing-service-account.json',
            'services.google_sheets.testing_access_token' => 'test-token',
        ]);

        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/office-file-id*' => Http::response([
                'error' => ['message' => 'This operation is not supported for this document. The document must not be an Office file.'],
            ], 400),
            'https://docs.google.com/spreadsheets/d/office-file-id/export*' => Http::response(
                "First Name,Last Name,Email address,School,DM Remarks for Closed Status,Landmark/Area\nUpdated,Name,user1@example.com,PLP,Successfully placed,Pasig\n",
                200
            ),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'Updated Name',
            'email' => 'user1@example.com',
            'school' => 'PLP',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Successful',
        ]);
    }

    public function test_google_sheet_sync_reads_uploaded_xlsx_office_file_without_conversion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config([
            'services.google_sheets.seekers_csv_url' => null,
            'services.google_sheets.seekers_sheet_id' => 'office-file-id',
            'services.google_sheets.seekers_gid' => '0',
            'services.google_sheets.service_account_json' => 'testing-service-account.json',
            'services.google_sheets.testing_access_token' => 'test-token',
        ]);

        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/office-file-id*' => Http::response([
                'error' => ['message' => 'This operation is not supported for this document. The document must not be an Office file.'],
            ], 400),
            'https://docs.google.com/spreadsheets/d/office-file-id/export*' => Http::response('<html>Export unavailable</html>', 500),
            'https://www.googleapis.com/drive/v3/files/office-file-id?alt=media*' => Http::response($this->xlsxFixture([
                ['First Name', 'Middle Name', 'Last Name', 'Email address', 'School', 'Final Placement Status', 'DM Remarks for Closed Status', 'Landmark/Area'],
                ['First2', null, 'Last2', 'user2@example.com', 'PLP', 'Pending', 'Successfully placed', 'Pasig'],
            ]), 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'First2 Last2',
            'email' => 'user2@example.com',
            'school' => 'PLP',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Successful',
        ]);
    }

    public function test_google_sheet_sync_converts_office_file_before_importing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config([
            'services.google_sheets.seekers_csv_url' => null,
            'services.google_sheets.seekers_sheet_id' => 'office-file-id',
            'services.google_sheets.seekers_gid' => '0',
            'services.google_sheets.service_account_json' => 'testing-service-account.json',
            'services.google_sheets.testing_access_token' => 'test-token',
        ]);

        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/office-file-id*' => Http::response([
                'error' => ['message' => 'This operation is not supported for this document. The document must not be an Office file.'],
            ], 400),
            'https://docs.google.com/spreadsheets/d/office-file-id/export*' => Http::response('<html>Export unavailable</html>', 500),
            'https://www.googleapis.com/drive/v3/files/office-file-id?alt=media*' => Http::response([
                'error' => ['message' => 'Download blocked'],
            ], 403),
            'https://www.googleapis.com/drive/v3/files/office-file-id/copy' => Http::response(['id' => 'converted-sheet-id'], 200),
            'https://sheets.googleapis.com/v4/spreadsheets/converted-sheet-id?fields=sheets.properties%28sheetId%2Ctitle%29' => Http::response([
                'sheets' => [
                    ['properties' => ['sheetId' => 0, 'title' => 'Sheet1']],
                ],
            ], 200),
            'https://sheets.googleapis.com/v4/spreadsheets/converted-sheet-id/values/%27Sheet1%27%21A%3AZZ*' => Http::response([
                'values' => [
                    ['First Name', 'Last Name', 'Email address', 'School', 'DM Status'],
                    ['First2', 'Last2', 'user2@example.com', 'PLP', 'Successful'],
                ],
            ], 200),
            'https://www.googleapis.com/drive/v3/files/converted-sheet-id' => Http::response(null, 204),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seekers.sync-google-sheet'))
            ->assertRedirect(route('admin.seekers'));

        $this->assertDatabaseHas('seekers', [
            'name' => 'First2 Last2',
            'email' => 'user2@example.com',
            'school' => 'PLP',
            'status' => 'Successful',
        ]);
    }

    public function test_admin_seekers_table_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        for ($index = 1; $index <= 11; $index++) {
            Seeker::create([
                'name' => sprintf('Person %02d', $index),
                'email' => "person{$index}@example.com",
                'status' => Seeker::STATUS_PENDING,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.seekers'))
            ->assertOk()
            ->assertSee('Showing 1-10 of 11')
            ->assertSee('Person 01')
            ->assertDontSee('Person 11');

        $this->actingAs($admin)
            ->get(route('admin.seekers', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11-11 of 11')
            ->assertSee('Person 11')
            ->assertDontSee('Person 01');
    }

    public function test_admin_can_assign_seeker_to_satellite_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $seeker = Seeker::create([
            'name' => 'Sam Torres',
            'email' => 'sam.torres@gmail.com',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.seekers.assign-district', $seeker), [
                'assigned_district' => 'Pasig',
            ])
            ->assertRedirect(route('admin.seekers'));

        $this->assertSame('Pasig', $seeker->refresh()->assigned_district);
        $this->assertSame('pasig', $seeker->assigned_satellite_slug);

        $this->actingAs($assignedUser)
            ->get(route('satellites.seekers', $satellite))
            ->assertOk()
            ->assertSee('Sam Torres')
            ->assertSee('sam.torres@gmail.com');
    }

    public function test_satellite_status_update_reflects_on_admin_seekers_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignedUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
        $seeker = Seeker::create([
            'name' => 'Ronnie Aquino',
            'email' => 'ronnie.a@gmail.com',
            'assigned_district' => 'Pasig',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Pending',
        ]);

        $this->actingAs($assignedUser)
            ->patch(route('satellites.seekers.status', [$satellite, $seeker]), [
                'status' => 'Successful',
            ])
            ->assertRedirect(route('satellites.seekers', $satellite));

        $this->assertSame('Successful', $seeker->refresh()->status);

        $this->actingAs($admin)
            ->get(route('admin.seekers'))
            ->assertOk()
            ->assertSee('Ronnie Aquino')
            ->assertSee('Successful');
    }

    public function test_admin_can_create_and_update_a_user_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'created_by_id' => $admin->id,
        ]);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@elevate.org',
                'role' => 'leader',
                'district' => 'Anonas',
            ])
            ->assertRedirect();

        $user = User::where('email', 'maria.santos@elevate.org')->firstOrFail();

        $this->assertSame('leader', $user->role);
        $this->assertSame('Anonas', $user->district);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'district' => 'Pasig',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Pasig', $user->refresh()->district);

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('User account')
            ->assertSee('Maria Santos')
            ->assertSee('Pasig');
    }

    public function test_admin_can_create_user_with_created_satellite_as_district(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'assigned_user_id' => null,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Warrend Franonas',
                'email' => 'warrenpagsuyuin810@gmail.com',
                'role' => 'staff',
                'district' => 'Anonas',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'warrenpagsuyuin810@gmail.com',
            'role' => 'staff',
            'district' => 'Anonas',
        ]);
        $user = User::where('email', 'warrenpagsuyuin810@gmail.com')->firstOrFail();

        $this->assertTrue($satellite->assignedUsers()->whereKey($user->id)->exists());
        $this->assertNull($satellite->refresh()->assigned_user_id);
    }

    public function test_updating_user_district_adds_them_to_the_shared_satellite_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warren = User::factory()->create(['name' => 'Warren Pagsuyuin', 'role' => 'member']);
        $maria = User::factory()->create(['name' => 'Maria Santos', 'role' => 'volunteer']);
        $anonas = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'assigned_user_id' => $warren->id,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $maria), [
                'district' => 'Anonas',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Anonas', $warren->refresh()->district);
        $this->assertSame('Anonas', $maria->refresh()->district);
        $this->assertSame($warren->id, $anonas->refresh()->assigned_user_id);
        $this->assertSame(2, $anonas->assignedUsers()->count());

        $this->actingAs($admin)
            ->get(route('admin.satellites'))
            ->assertOk()
            ->assertSee('Anonas')
            ->assertSee('2 assigned users')
            ->assertDontSee('Assigned to Maria Santos');
    }

    public function test_bulk_assign_users_to_same_satellite_district_does_not_overwrite_existing_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasig = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'created_by_id' => $admin->id,
        ]);
        $lea = User::factory()->create(['name' => 'Lea Ignacio', 'role' => 'member', 'district' => null]);
        $hannah = User::factory()->create(['name' => 'Hannah Arceo', 'role' => 'volunteer', 'district' => null]);

        $this->actingAs($admin)
            ->patch(route('admin.users.bulk'), [
                'bulk_action' => 'assign_district',
                'selection_scope' => 'ids',
                'ids' => [$lea->id, $hannah->id],
                'district' => 'Pasig',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Pasig', $lea->refresh()->district);
        $this->assertSame('Pasig', $hannah->refresh()->district);
        $this->assertNull($pasig->refresh()->assigned_user_id);
        $this->assertSame(2, $pasig->assignedUsers()->count());
    }

    public function test_same_district_users_share_satellite_dashboard_and_other_districts_are_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasig = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'leaders_count' => 4,
            'members_count' => 18,
            'seekers_count' => 7,
            'new_members_count' => 3,
            'created_by_id' => $admin->id,
        ]);
        $anonas = Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'created_by_id' => $admin->id,
        ]);
        $member = User::factory()->create(['name' => 'Pasig Member', 'role' => 'member', 'district' => 'Pasig']);
        $volunteer = User::factory()->create(['name' => 'Pasig Volunteer', 'role' => 'volunteer', 'district' => 'Pasig']);
        $leader = User::factory()->create(['name' => 'Pasig Leader', 'role' => 'leader', 'district' => 'Pasig']);
        $otherDistrictUser = User::factory()->create(['name' => 'Anonas Member', 'role' => 'member', 'district' => 'Anonas']);

        foreach ([$member, $volunteer, $leader] as $districtUser) {
            $this->actingAs($districtUser)
                ->get(route('satellites.dashboard', $pasig))
                ->assertOk()
                ->assertSee('Pasig Dashboard')
                ->assertSee('Pasig Member')
                ->assertSee('Pasig Volunteer')
                ->assertSee('Pasig Leader');
        }

        $this->actingAs($member)
            ->get(route('satellites.dashboard', $pasig))
            ->assertOk()
            ->assertSee('View Members')
            ->assertSee('View Follow-Ups')
            ->assertDontSee('Add Member')
            ->assertDontSee('Add Follow-Up');

        $this->actingAs($leader)
            ->get(route('satellites.dashboard', $pasig))
            ->assertOk()
            ->assertSee('Add Member')
            ->assertSee('Add Follow-Up');

        $this->actingAs($otherDistrictUser)
            ->get(route('satellites.dashboard', $pasig))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('satellites.dashboard', $anonas))
            ->assertForbidden();
    }

    public function test_admin_users_dashboard_can_search_and_filter_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create([
            'name' => 'Anonas',
            'slug' => 'anonas',
            'created_by_id' => $admin->id,
        ]);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'created_by_id' => $admin->id,
        ]);
        User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@elevate.org',
            'role' => 'leader',
            'district' => 'Anonas',
        ]);
        User::factory()->create([
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@elevate.org',
            'role' => 'member',
            'district' => 'Pasig',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users', [
                'search' => 'maria',
                'role' => 'leader',
                'district' => 'Anonas',
            ]))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertDontSee('Ana Reyes');
    }

    public function test_users_dashboard_shows_satellite_assignment_and_hides_admins(): void
    {
        $admin = User::factory()->create([
            'name' => 'Hidden Admin',
            'email' => 'hidden.admin@elevate.org',
            'role' => 'admin',
        ]);
        $assignedUser = User::factory()->create([
            'name' => 'Warren Pagsuyuin',
            'email' => 'warren@elevate.org',
            'role' => 'satellite',
            'district' => null,
        ]);
        Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Warren Pagsuyuin')
            ->assertSee('Pasig')
            ->assertDontSee('Hidden Admin')
            ->assertDontSee('hidden.admin@elevate.org');
    }

    /**
     * @param array<int, array<int, string|null>> $rows
     */
    private function xlsxFixture(array $rows): string
    {
        $sheetRows = '';

        foreach ($rows as $rowIndex => $columns) {
            $cells = '';
            $rowNumber = $rowIndex + 1;

            foreach ($columns as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $cellReference = $this->xlsxColumnName($columnIndex + 1).$rowNumber;
                $cells .= sprintf(
                    '<c r="%s" t="inlineStr"><is><t>%s</t></is></c>',
                    $cellReference,
                    htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                );
            }

            $sheetRows .= sprintf('<row r="%d">%s</row>', $rowNumber, $cells);
        }

        return $this->zipFixture([
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>',
        ]);
    }

    /**
     * @param array<int, array<int, string|null>> $rows
     */
    private function namespacedXlsxFixture(array $rows): string
    {
        $strings = [];
        $sheetRows = '';

        foreach ($rows as $rowIndex => $columns) {
            $cells = '';
            $rowNumber = $rowIndex + 1;

            foreach ($columns as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $strings[] = (string) $value;
                $cellReference = $this->xlsxColumnName($columnIndex + 1).$rowNumber;
                $cells .= sprintf(
                    '<x:c r="%s" t="s"><x:v>%d</x:v></x:c>',
                    $cellReference,
                    count($strings) - 1
                );
            }

            $sheetRows .= sprintf('<x:row r="%d">%s</x:row>', $rowNumber, $cells);
        }

        $sharedStrings = '';

        foreach ($strings as $string) {
            $sharedStrings .= sprintf(
                '<x:si><x:t>%s</x:t></x:si>',
                htmlspecialchars($string, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            );
        }

        return $this->zipFixture([
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><x:sheets><x:sheet name="Sheet1" sheetId="1" r:id="rId1"/></x:sheets></x:workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><r:Relationships xmlns:r="http://schemas.openxmlformats.org/package/2006/relationships"><r:Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></r:Relationships>',
            'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><x:sst xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.$sharedStrings.'</x:sst>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData>'.$sheetRows.'</x:sheetData></x:worksheet>',
        ]);
    }

    /**
     * @param array<string, string> $entries
     */
    private function zipFixture(array $entries): string
    {
        $localFiles = '';
        $centralDirectory = '';
        $offset = 0;

        foreach ($entries as $name => $contents) {
            $crc = hexdec(hash('crc32b', $contents));
            $size = strlen($contents);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0).$name;
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $localFiles .= $localHeader.$contents;
            $offset += strlen($localHeader) + $size;
        }

        $centralDirectoryOffset = strlen($localFiles);
        $centralDirectorySize = strlen($centralDirectory);

        return $localFiles.$centralDirectory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralDirectorySize, $centralDirectoryOffset, 0);
    }

    private function xlsxColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

}
