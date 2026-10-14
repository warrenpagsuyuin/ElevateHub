<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function satellite(User $admin, string $name = 'Pasig', string $slug = 'pasig', ?User $user = null): Satellite
    {
        return Satellite::create([
            'name' => $name,
            'slug' => $slug,
            'assigned_user_id' => $user?->id,
            'created_by_id' => $admin->id,
        ]);
    }

    private function member(Satellite $satellite, string $name, string $status = Member::STATUS_ACTIVE, string $role = Member::ROLE_MEMBER): Member
    {
        return Member::create([
            'satellite_id' => $satellite->id,
            'name' => $name,
            'full_name' => $name,
            'first_name' => $name,
            'last_name' => 'Test',
            'status' => $status,
            'dgroup_role' => $role,
            'campus_school' => $satellite->display_name.' School',
            'district' => $satellite->display_name,
        ]);
    }

    public function test_inactive_status_archives_and_removing_inactive_status_restores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $member = $this->member($satellite, 'Alyssa Lim');

        $member->update(['status' => Member::STATUS_INACTIVE]);

        $this->assertNotNull($member->refresh()->archived_at);
        $this->assertSame(0, $satellite->refresh()->members_count);
        $this->assertSame(1, $satellite->archivedMembers()->count());

        $member->update(['status' => Member::STATUS_ACTIVE]);

        $this->assertNull($member->refresh()->archived_at);
        $this->assertSame(1, $satellite->refresh()->members_count);
    }

    public function test_archive_inactive_command_backfills_legacy_rows_and_reports_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        DB::table('members')->insert([
            'name' => 'Legacy Inactive',
            'status' => 'Inactive',
            'satellite_id' => $satellite->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('members:archive-inactive')
            ->expectsOutput('Archived 1 inactive member.')
            ->assertExitCode(0);

        $legacy = Member::where('name', 'Legacy Inactive')->firstOrFail();
        $this->assertNotNull($legacy->archived_at);
        $this->assertSame(0, $satellite->refresh()->members_count);
    }

    public function test_updating_member_status_to_inactive_archives_and_returns_json_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $member = $this->member($satellite, 'Alden Reyes');

        $response = $this->actingAs($admin)
            ->patchJson(route('satellites.members.update', [$satellite, $member]), [
                'first_name' => 'Alden',
                'last_name' => 'Reyes',
                'status' => 'Inactive',
                'dgroup_role' => 'Member',
                'campus_school' => 'Pasig Campus',
                'district' => 'Pasig',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Member archived.',
                'archived' => true,
                'counts' => [
                    'active' => 0,
                    'archived' => 1,
                ],
            ]);

        $this->assertNotNull($member->refresh()->archived_at);
        $this->assertSame(0, $satellite->refresh()->members_count);
    }

    public function test_archived_members_are_excluded_from_active_list_search_and_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $activeMember = $this->member($satellite, 'Active Person');
        $inactiveMember = $this->member($satellite, 'Inactive Person');
        $inactiveMember->update(['status' => Member::STATUS_INACTIVE]);

        // Default list excludes archived member
        $response = $this->actingAs($admin)->get(route('satellites.members', $satellite));
        $response->assertOk()
            ->assertSee('Active Person')
            ->assertDontSee('Inactive Person');

        // Search for inactive member in default list returns 0 results
        $searchResponse = $this->actingAs($admin)->get(route('satellites.members', [$satellite, 'search' => 'Inactive']));
        $searchResponse->assertOk()
            ->assertDontSee('Inactive Person');

        // CSV export excludes archived member
        $exportResponse = $this->actingAs($admin)->get(route('admin.satellites.members.export', $satellite));
        $exportResponse->assertOk();
        $csvContent = $exportResponse->streamedContent();
        $this->assertStringContainsString('Active Person', $csvContent);
        $this->assertStringNotContainsString('Inactive Person', $csvContent);
    }

    public function test_archived_view_shows_archived_member_and_restore_action_restores_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $member = $this->member($satellite, 'Alden Reyes');
        $member->update(['status' => Member::STATUS_INACTIVE]);

        // Archived view displays them
        $archivedPage = $this->actingAs($admin)->get(route('satellites.members', [$satellite, 'view' => 'archived']));
        $archivedPage->assertOk()
            ->assertSee('Alden Reyes')
            ->assertSee('Restore');

        // Restore route
        $restoreResponse = $this->actingAs($admin)
            ->post(route('satellites.members.restore', [$satellite, $member]));

        $restoreResponse->assertRedirect(route('satellites.members', $satellite));
        $this->assertNull($member->refresh()->archived_at);
        $this->assertSame(Member::STATUS_ACTIVE, $member->status);
        $this->assertSame(1, $satellite->refresh()->members_count);
    }

    public function test_direct_url_to_archived_member_returns_404_for_normal_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $member = $this->member($satellite, 'Alden Reyes');
        $member->update(['status' => Member::STATUS_INACTIVE]);

        // Direct URL without view=archived returns 404
        $this->actingAs($admin)
            ->get(route('satellites.members.show', [$satellite, $member]))
            ->assertNotFound();

        // Direct URL with view=archived redirects to archived view
        $this->actingAs($admin)
            ->get(route('satellites.members.show', [$satellite, $member, 'view' => 'archived']))
            ->assertRedirect(route('satellites.members', ['satellite' => $satellite, 'view' => 'archived', 'search' => 'Alden Reyes']));
    }

    public function test_satellite_user_cannot_archive_or_restore_another_satellite_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasigUser = User::factory()->create(['role' => 'user']);
        $eastwoodUser = User::factory()->create(['role' => 'user']);

        $pasig = $this->satellite($admin, 'Pasig', 'pasig', $pasigUser);
        $eastwood = $this->satellite($admin, 'Eastwood', 'eastwood', $eastwoodUser);

        $eastwoodMember = $this->member($eastwood, 'Eastwood Member');
        $eastwoodMember->update(['status' => Member::STATUS_INACTIVE]);

        // Pasig user trying to restore Eastwood member gets 403
        $this->actingAs($pasigUser)
            ->post(route('satellites.members.restore', [$eastwood, $eastwoodMember]))
            ->assertForbidden();

        // Pasig user trying to update Eastwood member gets 403
        $this->actingAs($pasigUser)
            ->patch(route('satellites.members.update', [$eastwood, $eastwoodMember]), [
                'first_name' => 'Eastwood',
                'last_name' => 'Member',
                'status' => 'Active',
                'dgroup_role' => 'Member',
                'campus_school' => 'Eastwood Campus',
                'district' => 'Eastwood',
            ])
            ->assertForbidden();
    }

    public function test_admin_members_dashboard_counts_match_active_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $active1 = $this->member($satellite, 'Active One');
        $active2 = $this->member($satellite, 'Active Two');
        $inactive = $this->member($satellite, 'Inactive One');
        $inactive->update(['status' => Member::STATUS_INACTIVE]);

        $response = $this->actingAs($admin)->get(route('admin.members'));
        $response->assertOk();

        // Satellite members_count must be 2, not 3
        $this->assertSame(2, $satellite->refresh()->members_count);
        $response->assertSee('2');
    }
}