<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use App\Services\DashboardDirectoryService;
use App\Services\LeaderReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DashboardDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function satellite(string $name, string $hub = 'MAIN'): Satellite
    {
        return Satellite::create(['name' => $name, 'slug' => strtolower($name), 'hub' => $hub, 'members_count' => 999]);
    }

    private function person(Satellite $s, string $name, array $extra = []): Member
    {
        return Member::create(array_merge(['satellite_id' => $s->id, 'name' => $name, 'full_name' => $name, 'dgroup_role' => Member::ROLE_MEMBER, 'status' => Member::STATUS_ACTIVE], $extra));
    }

    public function test_live_definitions_and_every_card_scope_match_paginated_lists(): void
    {
        $main = $this->satellite('Main');
        $ncp = $this->satellite('North', 'NCP');
        $leader = $this->person($main, 'Leader', ['dgroup_role' => Member::ROLE_LEADER, 'campus_school' => ' Campus   One ']);
        $this->person($ncp, 'Other Leader', ['dgroup_role' => Member::ROLE_LEADER, 'campus_school' => 'CAMPUS ONE']);
        foreach (range(1, 12) as $i) {
            $this->person($main, 'Member '.sprintf('%02d', $i), ['leader_member_id' => $leader->id, 'status' => $i === 1 ? 'Inactive' : 'Active', 'campus_school' => $i === 2 ? 'Another campus' : 'n/A']);
        }
        $this->person($ncp, 'North member', ['campus_school' => '-']);
        $service = app(DashboardDirectoryService::class);
        $this->assertSame([2, 1, 12, 2], array_column($service->summary(), 'total'));
        $this->assertSame(2, $service->campuses(null, $main->id)->count());
        $this->assertSame(1, $service->campuses(null, $ncp->id)->count());
        $this->assertSame(['0' => 1, '1-2' => 0, '3-4' => 0, '5+' => 1], $service->distribution());
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ($service->summary() as $card) {
            foreach ([null, 'main', 'ncp', 'icp'] as $category) {
                $count = $category ? collect($card['segments'])->firstWhere('label', strtoupper($category))['value'] : $card['total'];
                $this->get(route('admin.dashboard.directory', ['directory' => $card['key'], 'category' => $category, 'per_page' => 10]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === $count && $rows->count() <= 10);
            }
        }
        $this->get('/admin/dashboard/dgroup-members?category=main&search=Member&sort=name&direction=desc&per_page=10&page=2')->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 11 && $rows->first()->display_name === 'Member 02');
        $this->get('/admin/dashboard/dgroup-members?category=icp')->assertSee('No ICP members yet');
        $this->get('/admin/dashboard')->assertSee('View all 12 DGroup Members', false);
        config(['dashboard_directory.DGROUP_MIN_MEMBERS' => 12]);
        $this->assertSame(0, $service->query('dgroups')->count());
    }

    public function test_directory_routes_require_admin_and_validate_filters(): void
    {
        $this->get('/admin/dashboard/dgroup-members')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'member']))->get('/admin/dashboard/dgroup-members')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/admin/dashboard/dgroup-members?category=invalid')->assertUnprocessable();
    }

    public function test_leader_links_validate_and_names_stay_synchronized(): void
    {
        $s = $this->satellite('Main');
        $leader = $this->person($s, 'Original', ['dgroup_role' => Member::ROLE_LEADER]);
        $member = $this->person($s, 'Member', ['leader_member_id' => $leader->id, 'dgroup_leader_name' => 'Forged']);
        $this->assertSame('Original', $member->dgroup_leader_name);
        $this->assertSame($leader->id, $member->formPayload()['leader_member_id']);
        $leader->update(['full_name' => 'Renamed', 'name' => 'Renamed']);
        $this->assertSame('Renamed', $member->refresh()->dgroup_leader_name);
        try {
            $leader->update(['dgroup_role' => Member::ROLE_MEMBER]);
            $this->fail('Role change should be blocked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leader_member_id', $e->errors());
        }
        $leader->refresh()->delete();
        $this->assertNull($member->refresh()->leader_member_id);
    }

    public function test_cross_satellite_and_non_leader_links_are_rejected(): void
    {
        $s = $this->satellite('Main');
        $other = $this->satellite('North', 'NCP');
        $wrong = $this->person($other, 'Leader', ['dgroup_role' => Member::ROLE_LEADER]);
        $nonLeader = $this->person($s, 'Not leader');
        foreach ([$wrong, $nonLeader] as $candidate) {
            try {
                $this->person($s, 'Invalid', ['leader_member_id' => $candidate->id]);
                $this->fail('Invalid leader accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('leader_member_id', $e->errors());
            }
        }
        $this->assertDatabaseMissing('members', ['name' => 'Invalid']);
    }

    public function test_reconciliation_is_exact_atomic_and_idempotent(): void
    {
        $s = $this->satellite('Main');
        $leader = $this->person($s, 'Leader', ['dgroup_role' => Member::ROLE_LEADER]);
        $member = $this->person($s, 'Member');
        DB::table('members')->where('id', $member->id)->update(['dgroup_leader_name' => 'Leader']);
        $pair = ['member_id' => $member->id, 'leader_id' => $leader->id, 'member_name' => 'Member', 'leader_name' => 'Leader', 'satellite_id' => $s->id];
        $service = app(LeaderReconciliationService::class);
        try {
            $service->apply([$pair, array_merge($pair, ['member_name' => 'Changed'])]);
            $this->fail('Changed preview accepted');
        } catch (\RuntimeException $e) {
            $this->assertNull($member->refresh()->leader_member_id);
        }
        $this->assertSame(1, $service->apply([$pair]));
        $this->assertSame(0, $service->apply([$pair]));
        $unresolved = $this->person($s, 'Unresolved', ['dgroup_leader_name' => 'Unknown']);
        $unresolved->update(['status' => 'Inactive']);
        $this->assertNull($unresolved->leader_member_id);
    }

    public function test_member_form_preserves_selected_link_and_rejects_other_district(): void
    {
        $s = $this->satellite('Main');
        $other = $this->satellite('North', 'NCP');
        $user = User::factory()->create(['role' => 'satellite', 'district' => $s->name]);
        $s->update(['assigned_user_id' => $user->id]);
        $leader = $this->person($s, 'Selected Leader', ['dgroup_role' => Member::ROLE_LEADER]);
        $wrong = $this->person($other, 'Other Leader', ['dgroup_role' => Member::ROLE_LEADER]);
        $payload = ['first_name' => 'Test', 'last_name' => 'Member', 'status' => 'Active', 'dgroup_role' => 'Member', 'district' => 'Main', 'campus_school' => 'School', 'leader_member_id' => $leader->id, 'dgroup_leader_name' => 'Forged'];
        $this->actingAs($user)->post(route('satellites.members.store', $s), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $member = Member::where('full_name', 'Test Member')->firstOrFail();
        $this->assertSame($leader->id, $member->leader_member_id);
        $this->assertSame('Selected Leader', $member->dgroup_leader_name);
        $this->get(route('satellites.members', $s))->assertOk()->assertSee('name="leader_member_id"', false)->assertViewHas('leaderOptions', fn ($options) => $options->pluck('id')->all() === [$leader->id]);
        $this->patch(route('satellites.members.update', [$s, $member]), $payload)->assertSessionHasNoErrors();
        $this->assertSame($leader->id, $member->refresh()->leader_member_id);
        $this->patch(route('satellites.members.update', [$s, $member]), array_merge($payload, ['leader_member_id' => $wrong->id]))->assertSessionHasErrors('leader_member_id', null, 'memberForm');
        $this->assertSame($leader->id, $member->refresh()->leader_member_id);
    }
}
