<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\EngagementResult;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\UnitSnapshot;
use App\Models\User;
use App\Services\MovementAnalyticsService;
use App\Services\MovementAnalyticsSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovementDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 2));
        Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        app(MovementAnalyticsSetup::class)->run();
    }

    private function report(string $period = '2026-06', ?string $group = null): array
    {
        return app(MovementAnalyticsService::class)->dashboard($period, group: $group, mode: 'reported');
    }

    private function snapshot(string $period, int $count): void
    {
        $states = array_fill_keys(config('movement_analytics.metrics'), 'not_reported');
        $states['dmembers'] = 'reported';
        UnitSnapshot::create(['unit_id' => OrgUnit::where('key', 'main.pasig')->value('id'), 'checkpoint_id' => Checkpoint::where('key', $period)->value('id'),
            'source' => 'legacy_import', 'dmembers' => $count, 'metric_states' => $states, 'quality_flags' => []]);
    }

    public function test_pipeline_uses_joined_members_not_assignment_or_success_labels_and_includes_archives(): void
    {
        $member = Member::create(['name' => 'Private member', 'satellite_id' => Satellite::first()->id]);
        Seeker::create(['name' => 'Private seeker', 'email' => 'joined@example.test', 'assigned_satellite_slug' => 'pasig', 'status' => 'Pending', 'converted_member_id' => $member->id]);
        Seeker::create(['name' => 'Assigned only', 'email' => 'assigned@example.test', 'assigned_satellite_slug' => 'pasig', 'status' => 'Successful']);
        Seeker::create(['name' => 'Archived', 'email' => 'archived@example.test', 'archived_at' => now()]);
        $report = $this->report();
        $p = $report['activity']['pipeline'];
        $this->assertSame(3, $p['total']);
        $this->assertSame(2, $p['active']);
        $this->assertSame(1, $p['archived']);
        $this->assertSame(1, $p['placed']);
        $this->assertSame(2, $p['non_placed']);
        $this->assertSame(33.33, $p['rate']);
        $this->assertNull($p['trend'][0]['value']);
        $this->assertStringNotContainsString('Private', json_encode($report));
        $this->assertSame(0, $this->report(group: 'ICP')['activity']['pipeline']['total']);
    }

    public function test_engagement_and_followup_sentiment_are_aggregated_without_names_or_notes(): void
    {
        $member = Member::create(['name' => 'Confidential name', 'satellite_id' => Satellite::first()->id]);
        EngagementResult::create(['member_id' => $member->id, 'classification' => 'High']);
        Member::create(['name' => 'Unclassified person', 'satellite_id' => Satellite::first()->id]);
        FollowUp::create(['member_id' => $member->id, 'satellite_id' => Satellite::first()->id, 'follow_up_date' => '2026-06-01', 'method' => 'Phone', 'status' => 'Completed', 'notes' => 'PRIVATE NOTE', 'sentiment_label' => 'Negative']);
        $report = $this->report();
        $this->assertSame(['Active' => 1, 'Not classified' => 1], $report['activity']['engagement']['classes']);
        $this->assertSame(1, $report['activity']['sentiment']['Negative']);
        $this->assertStringNotContainsString('PRIVATE NOTE', json_encode($report));
        $this->assertStringNotContainsString('Confidential name', json_encode($report));
        $this->assertSame(0, $this->report(group: 'ICP')['activity']['engagement']['total']);
    }

    public function test_forecast_requires_three_measured_points_and_reports_range_and_honest_backtest(): void
    {
        $this->snapshot('2025-12', 10);
        $this->snapshot('2026-03', 14);
        $this->assertFalse($this->report('2026-03')['forecasts']['units']['main.pasig']['dmembers']['available']);
        $this->snapshot('2026-06', 18);
        $forecast = $this->report()['forecasts']['units']['main.pasig']['dmembers'];
        $this->assertTrue($forecast['available']);
        $this->assertSame(18, $forecast['low']);
        $this->assertSame(26, $forecast['high']);
        $this->assertSame(0, $forecast['backtest']['absolute_error']);
        $this->assertSame(4, $forecast['backtest']['no_change_error']);
        $this->assertFalse($this->report('2026-09')['forecasts']['units']['main.pasig']['dmembers']['available']);
        $this->assertNull($this->report('2026-03')['forecasts']['units']['main.pasig']['dmembers']['backtest']);
        $this->assertFalse($this->report()['forecasts']['units']['icp.asia']['campus']['available']);
    }

    public function test_future_points_do_not_leak_into_forecast_and_missing_baseline_hides_backtest(): void
    {
        $this->snapshot('2026-03', 10);
        $this->snapshot('2026-06', 20);
        $this->snapshot('2026-09', 30);
        $this->snapshot('2026-12', 999999);
        $forecast = $this->report('2026-09')['forecasts']['units']['main.pasig']['dmembers'];
        $this->assertSame(30, $forecast['low']);
        $this->assertSame(50, $forecast['high']);
        $this->assertNull($forecast['backtest']);
    }

    public function test_csv_matches_every_pivot_cell_and_respects_group_filter_and_nulls(): void
    {
        $this->snapshot('2025-12', 10);
        $this->snapshot('2026-06', 8);
        $admin = User::factory()->create(['role' => 'admin']);
        $query = ['checkpoint' => '2026-06', 'mode' => 'reported', 'group' => 'MAIN'];
        $json = $this->actingAs($admin)->getJson(route('admin.analytics.movement-data', $query))->assertOk()->json();
        $csv = $this->get(route('admin.analytics.movement-data', $query + ['format' => 'csv']))->assertOk()->streamedContent();
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        foreach ([$json['pivot']['columns'], ...$json['pivot']['rows']] as $row) {
            $this->assertSame(array_map(fn ($v) => (string) ($v ?? ''), $row), fgetcsv($handle, 0, ',', '"', ''));
        }
        $this->assertFalse(fgetcsv($handle));
        fclose($handle);
        $this->assertStringNotContainsString('Visayas', $csv);
        $this->assertContains('not_reported', array_merge(...$json['pivot']['rows']));
    }

    public function test_csv_neutralizes_formula_strings_but_preserves_negative_numeric_variance(): void
    {
        OrgUnit::where('key', 'main.pasig')->update(['name' => '=1+1']);
        $this->snapshot('2025-12', 10);
        $this->snapshot('2026-06', 8);
        $admin = User::factory()->create(['role' => 'admin']);
        $csv = $this->actingAs($admin)->get(route('admin.analytics.movement-data', ['checkpoint' => '2026-06', 'mode' => 'reported', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('-2,-20', $csv);
    }

    public function test_dashboard_aggregation_is_read_only_and_page_has_no_external_chart_dependency(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->report();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $q) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $q['query']);
        }
        $this->assertLessThan(25, count($queries));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.analytics'))->assertOk()->assertSee('mv-drawer')->assertSee('Export CSV')->assertSee('How this works')->assertSee('Auto uses reports when available')->assertSee('How these numbers work')->assertDontSee('cdn.plot.ly');
    }

    public function test_empty_checkpoint_returns_a_stable_analytics_shape(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson(route('admin.analytics.movement-data', [
            'checkpoint' => '2026-06',
            'compare' => 'baseline',
            'mode' => 'reported',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'metrics',
                'completeness' => ['total', 'reported', 'live', 'missing', 'percent'],
                'meta' => ['request_id', 'display_source'],
                'units',
                'issues',
            ])
            ->assertJsonPath('completeness.reported', 0);
        $this->assertNotEmpty($response->json('metrics'));
        $this->assertSame('reported', $response->json('meta.display_source'));
    }

    public function test_auto_mode_uses_live_records_when_the_checkpoint_has_no_reports(): void
    {
        Member::create(['name' => 'Live count only', 'satellite_id' => Satellite::first()->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson(route('admin.analytics.movement-data', [
            'checkpoint' => '2026-06',
            'compare' => 'baseline',
            'mode' => 'auto',
        ]));

        $response->assertOk()
            ->assertJsonPath('completeness.reported', 0)
            ->assertJsonPath('completeness.live', 1)
            ->assertJsonPath('meta.display_source', 'live_fallback');
    }
}
