<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_satellite_tabs_have_counts_and_follow_the_view_query(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $archived = Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        $archived->archive($admin);

        $active = $this->actingAs($admin)->get(route('admin.satellites'));
        $active->assertOk()
            ->assertSee('Active (1)')
            ->assertSee('Archived (1)')
            ->assertSee(route('admin.satellites'), false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($admin)->get(route('admin.satellites', ['view' => 'archived']))
            ->assertOk()
            ->assertSee(route('admin.satellites', ['view' => 'archived']), false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_seeker_and_member_tabs_mark_the_selected_url_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        Seeker::create(['name' => 'Active seeker', 'email' => 'active@example.test']);
        Seeker::create(['name' => 'Archived seeker', 'email' => 'archived@example.test', 'archived_at' => now()]);
        Member::create(['satellite_id' => $satellite->id, 'name' => 'Active member', 'full_name' => 'Active member', 'status' => Member::STATUS_ACTIVE]);
        Member::create(['satellite_id' => $satellite->id, 'name' => 'Archived member', 'full_name' => 'Archived member', 'status' => Member::STATUS_INACTIVE]);

        $this->actingAs($admin)->get(route('admin.seekers', ['view' => 'archive']))
            ->assertOk()
            ->assertSee('Active (1)')
            ->assertSee('Archived (1)')
            ->assertSee(route('admin.seekers', ['view' => 'archive']), false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($admin)->get(route('satellites.members', [$satellite, 'view' => 'archived']))
            ->assertOk()
            ->assertSee('Active (1)')
            ->assertSee('Archived (1)')
            ->assertSee(route('satellites.members', [$satellite, 'view' => 'archived']), false)
            ->assertSee('aria-current="page"', false);
    }
}
