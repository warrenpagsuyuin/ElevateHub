<?php

namespace Tests\Feature;

use App\Models\Satellite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SeedSatisfactionDemoTest extends TestCase
{
    use RefreshDatabase;

    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = storage_path('framework/testing/satisfaction-demo-'.bin2hex(random_bytes(6)).'.json');
        config(['satisfaction_demo.manifest_path' => $this->manifest, 'app.debug' => true]);
        foreach (['Anonas', 'UBELT', 'THE HUB', 'EASTWOOD', 'MAIN ORTIGAS'] as $name) {
            Satellite::create(['name' => $name, 'slug' => str($name)->slug(), 'hub' => 'MAIN']);
        }
    }

    protected function tearDown(): void
    {
        File::delete($this->manifest);
        parent::tearDown();
    }

    public function test_seed_is_idempotent_and_reset_keeps_all_seven_real_rows(): void
    {
        $satelliteId = Satellite::where('name', 'Anonas')->value('id');
        $realRows = collect(range(1, 7))->map(fn (int $number) => [
            'id' => sprintf('11111111-1111-4111-8111-%012d', $number),
            'service_date' => '2024-01-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'satellite_id' => $satelliteId,
            'hub' => 'MAIN',
        ])->all();
        DB::table('satisfaction_responses')->insert($realRows);

        $this->artisan('satisfaction:seed-demo --confirm-local')->assertSuccessful();
        $this->assertDatabaseCount('satisfaction_responses', 59);
        $firstIds = DB::table('satisfaction_responses')->whereNotIn('id', array_column($realRows, 'id'))->orderBy('id')->pluck('id')->all();
        $this->assertCount(52, $firstIds);
        $manifest = json_decode(File::get($this->manifest), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['rows'] as $row) {
            $this->assertLessThanOrEqual(now(config('app.timezone'))->toDateString(), $row['date']);
        }

        $this->artisan('satisfaction:seed-demo --confirm-local')->assertSuccessful();
        $this->assertDatabaseCount('satisfaction_responses', 59);
        $this->assertSame($firstIds, DB::table('satisfaction_responses')->whereNotIn('id', array_column($realRows, 'id'))->orderBy('id')->pluck('id')->all());
        $this->artisan('satisfaction:seed-demo --confirm-local --reset')->assertSuccessful();
        $this->assertDatabaseCount('satisfaction_responses', 7);
        foreach ($realRows as $real) $this->assertDatabaseHas('satisfaction_responses', $real);
        $this->assertFileDoesNotExist($this->manifest);
    }

    public function test_dry_run_writes_nothing_and_guards_refuse_unsafe_runs(): void
    {
        $this->artisan('satisfaction:seed-demo --confirm-local --dry-run')->assertSuccessful();
        $this->assertDatabaseCount('satisfaction_responses', 0);
        $this->assertFileDoesNotExist($this->manifest);
        $this->artisan('satisfaction:seed-demo')->assertFailed();
        config(['app.debug' => false]);
        $this->artisan('satisfaction:seed-demo --confirm-local')->assertFailed();
        config(['app.debug' => true, 'database.connections.sqlite.database' => 'file:unsafe.sqlite']);
        $this->artisan('satisfaction:seed-demo --confirm-local')->assertFailed();
    }

    public function test_dashboard_hides_seeded_individual_answers_and_proves_the_isolated_boundary(): void
    {
        $this->artisan('satisfaction:seed-demo --confirm-local')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);
        $anonas = Satellite::where('name', 'Anonas')->firstOrFail();
        $five = $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['day' => '2026-10-15', 'satellite' => $anonas->id]));
        $five->assertOk()->assertSee('71%')->assertSee('100%')->assertSee('40%')->assertSee('60%')
            ->assertDontSee('Not enough responses yet');
        $seededId = json_decode(File::get($this->manifest), true, 512, JSON_THROW_ON_ERROR)['rows'][0]['id'];
        $this->assertStringNotContainsString($seededId, $five->getContent());
        $json = $this->actingAs($admin)->getJson(route('admin.analytics.sentiment', ['day' => '2026-10-15', 'satellite' => $anonas->id]));
        $json->assertOk();
        $this->assertStringNotContainsString($seededId, $json->getContent());
        $eastwood = Satellite::where('name', 'EASTWOOD')->firstOrFail();
        $four = $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['day' => '2026-10-16', 'satellite' => $eastwood->id]));
        $four->assertOk()->assertSee('Not enough responses yet');
        $this->assertStringNotContainsString('seed:satisfaction-demo-v1', $five->getContent());
        $this->assertStringNotContainsString('seed:satisfaction-demo-v1', $four->getContent());

        $boundary = Satellite::create(['name' => 'Boundary', 'slug' => 'boundary', 'hub' => 'MAIN']);
        $questions = DB::table('satisfaction_questions')->where('active', true)->where('dgroup_only', false)->pluck('id');
        foreach (range(1, 4) as $number) $this->insertAnonymousResponse($boundary->id, '2026-10-11', $questions->all(), 'b'.$number);
        $request = \Illuminate\Http\Request::create('/admin/analytics/sentiment', 'GET', ['day' => '2026-10-11', 'satellite' => $boundary->id]);
        $service = app(\App\Services\SatisfactionService::class);
        $this->assertFalse($service->results($request, $service->period($request))['overall']['show']);
        $this->insertAnonymousResponse($boundary->id, '2026-10-11', $questions->all(), 'b5');
        $this->assertTrue($service->results($request, $service->period($request))['overall']['show']);
    }

    private function insertAnonymousResponse(int $satelliteId, string $date, array $questionIds, string $suffix): void
    {
        $id = sprintf('22222222-2222-4222-8222-%012d', (int) substr(hash('sha256', $suffix), 0, 6));
        DB::table('satisfaction_responses')->insert(['id' => $id, 'service_date' => $date, 'satellite_id' => $satelliteId, 'hub' => 'MAIN']);
        DB::table('satisfaction_answers')->insert(array_map(fn ($question) => ['response_id' => $id, 'question_id' => $question, 'yes' => true], $questionIds));
    }
}
