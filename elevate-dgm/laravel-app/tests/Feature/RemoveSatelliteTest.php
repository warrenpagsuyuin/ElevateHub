<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveSatelliteTest extends TestCase
{
    use RefreshDatabase;

    private function satellite(): Satellite
    {
        return Satellite::create(['name' => 'Test Satellite', 'slug' => 'test-satellite', 'hub' => 'MAIN']);
    }

    public function test_admin_can_archive_an_empty_satellite_from_the_menu(): void
    {
        $satellite = $this->satellite();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.satellites'))->assertOk()
            ->assertSee('Archive satellite')->assertSee('data-confirm-message', false)
            ->assertSee(route('admin.satellites.destroy', $satellite));

        $this->delete(route('admin.satellites.destroy', $satellite))
            ->assertRedirect(route('admin.satellites'))->assertSessionHas('status');
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id]);
        $this->assertNotNull($satellite->fresh()->archived_at);
        $this->delete(route('admin.satellites.destroy', $satellite))->assertNotFound();
    }

    public function test_non_admin_and_guest_cannot_remove_satellites(): void
    {
        $satellite = $this->satellite();
        $this->delete(route('admin.satellites.destroy', $satellite))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'satellite']))
            ->delete(route('admin.satellites.destroy', $satellite))->assertForbidden();
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id]);
    }

    public function test_assigned_users_members_and_seekers_are_preserved_on_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['user', 'member', 'seeker'] as $kind) {
            $satellite = Satellite::create(['name' => $kind, 'slug' => $kind, 'hub' => 'MAIN']);
            $record = match ($kind) {
                'user' => User::factory()->create(['district' => $satellite->name, 'role' => 'satellite']),
                'member' => Member::create(['name' => 'Member', 'satellite_id' => $satellite->id]),
                'seeker' => Seeker::create(['name' => 'Seeker', 'email' => 'seeker@example.com', 'assigned_satellite_slug' => $satellite->slug]),
            };

            $this->delete(route('admin.satellites.destroy', $satellite))
                ->assertRedirect(route('admin.satellites'))->assertSessionHas('status');
            $this->assertDatabaseHas('satellites', ['id' => $satellite->id]);
            $this->assertModelExists($record);
        }
    }

    public function test_analytics_mapping_survives_archive(): void
    {
        $satellite = $this->satellite();
        $unitId = DB::table('org_units')->insertGetId([
            'key' => 'test', 'name' => 'Test', 'type' => 'satellite', 'has_campus' => false, 'sort' => 1,
        ]);
        DB::table('org_unit_satellite_mappings')->insert(['unit_id' => $unitId, 'satellite_id' => $satellite->id]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.satellites.destroy', $satellite))
            ->assertRedirect(route('admin.satellites'))->assertSessionHas('status');
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id]);
        $this->assertDatabaseHas('org_unit_satellite_mappings', ['satellite_id' => $satellite->id]);
    }
}
