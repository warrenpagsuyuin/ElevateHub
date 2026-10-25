<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SatisfactionTest extends TestCase
{
    use RefreshDatabase;

    private function member(Satellite $satellite, string $email): User
    {
        $user = User::factory()->create(['role' => 'member', 'email' => $email, 'district' => $satellite->name]);
        Member::create(['name' => $email, 'email_address' => $email, 'satellite_id' => $satellite->id, 'status' => 'Active']);
        return $user;
    }

    private function satellite(string $name, string $hub): Satellite
    {
        return Satellite::create(['name' => $name, 'slug' => strtolower($name), 'hub' => $hub]);
    }

    private function answers(string $value = 'yes'): array
    {
        return DB::table('satisfaction_questions')->where('dgroup_only', false)->pluck('id')->mapWithKeys(fn ($id) => [$id => $value])->all();
    }

    public function test_member_can_submit_without_identifying_answer_columns_and_duplicate_is_rejected(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $member = $this->member($satellite, 'member1@example.test');
        $date = now(config('app.timezone'))->toDateString();
        $this->actingAs($member)->get(route('feedback.create'))->assertOk()
            ->assertSee('Satisfaction Questionnaire')->assertDontSee('I consent to use of my written system feedback')
            ->assertDontSee('Does your DGroup leader support you');
        $this->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers()])
            ->assertRedirect(route('feedback.create', ['satellite' => $satellite->slug]));
        $this->assertDatabaseCount('satisfaction_responses', 1);
        $this->assertDatabaseCount('satisfaction_answers', 9);
        $this->assertDatabaseCount('satisfaction_tokens', 1);
        $this->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers()])
            ->assertSessionHasErrors('service_date');
        $this->post(route('feedback.satisfaction.store'), ['service_date' => now(config('app.timezone'))->addDay()->toDateString(), 'answers' => $this->answers()])
            ->assertSessionHasErrors('service_date');
        $this->assertDatabaseCount('satisfaction_responses', 1);
        $forbidden = ['user_id', 'member_id', 'name', 'email', 'ip', 'ip_address', 'user_agent', 'created_at', 'updated_at', 'feedback_id'];
        foreach (['satisfaction_responses', 'satisfaction_answers', 'satisfaction_tokens'] as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($forbidden as $column) $this->assertNotContains($column, $columns, "$table has identifying column $column");
        }
        $this->assertNotContains('response_id', Schema::getColumnListing('satisfaction_tokens'));
        $this->assertNotContains('token', Schema::getColumnListing('satisfaction_responses'));
        foreach (['satisfaction_responses', 'satisfaction_answers', 'satisfaction_tokens'] as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertNotContains($key['foreign_table'] ?? null, ['users', 'members', 'feedback']);
            }
        }
    }

    public function test_missing_answer_and_unassigned_user_are_blocked(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $member = $this->member($satellite, 'member2@example.test');
        $answers = $this->answers();
        unset($answers[array_key_first($answers)]);
        $this->actingAs($member)->post(route('feedback.satisfaction.store'), ['service_date' => now(config('app.timezone'))->toDateString(), 'answers' => $answers])
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('satisfaction_responses', 0);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($seeker)->get(route('feedback.create'))->assertOk()->assertSee('confirm your satellite assignment');
        $this->actingAs($seeker)->post(route('feedback.satisfaction.store'), ['service_date' => now(config('app.timezone'))->toDateString(), 'answers' => $this->answers()])->assertSessionHasErrors('satisfaction');
    }

    public function test_dgroup_member_sees_dgroup_only_questions(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $user = User::factory()->create(['role' => 'member', 'email' => 'leader@example.test', 'district' => $satellite->name]);
        Member::create(['name' => 'Leader', 'email_address' => $user->email, 'satellite_id' => $satellite->id,
            'status' => 'Active', 'dgroup_role' => Member::ROLE_LEADER]);
        $this->actingAs($user)->get(route('feedback.create'))->assertOk()->assertSee('Does your DGroup leader support you');
        $allAnswers = DB::table('satisfaction_questions')->pluck('id')->mapWithKeys(fn ($id) => [$id => 'yes'])->all();
        $this->post(route('feedback.satisfaction.store'), ['service_date' => now(config('app.timezone'))->toDateString(), 'answers' => $allAnswers])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('satisfaction_answers', 12);
    }

    public function test_member_account_without_directory_row_can_answer_general_questions(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $user = User::factory()->create(['role' => 'member', 'district' => $satellite->name]);
        $this->actingAs($user)->get(route('feedback.create'))->assertOk()->assertSee('Satisfaction questionnaire')->assertDontSee('Does your DGroup leader support you');
        $this->post(route('feedback.satisfaction.store'), ['service_date' => now(config('app.timezone'))->toDateString(), 'answers' => $this->answers()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('satisfaction_answers', 9);
    }

    public function test_every_satellite_role_can_answer_and_cannot_switch_satellites(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $pasig = $this->satellite('Pasig', 'East');
        $other = $this->satellite('Other', 'West');
        $date = now(config('app.timezone'))->toDateString();
        foreach (['seeker', 'leader', 'volunteer', 'satellite', 'district'] as $role) {
            $user = User::factory()->create(['role' => $role, 'district' => $pasig->name]);
            $this->actingAs($user)->get(route('satellites.dashboard', $pasig))->assertOk()
                ->assertDontSee('Answer satisfaction questions')
                ->assertDontSee('View satisfaction results');
            $this->get(route('feedback.create', ['satellite' => $pasig->slug]))->assertOk()->assertSee('Submit answers');
            $this->post(route('feedback.satisfaction.store'), [
                'satellite' => $pasig->slug, 'service_date' => $date, 'answers' => $this->answers(),
            ])->assertSessionHasNoErrors();
            $this->post(route('feedback.satisfaction.store'), [
                'satellite' => $other->slug, 'service_date' => now(config('app.timezone'))->subDay()->toDateString(), 'answers' => $this->answers(),
            ])->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('feedback.create', ['satellite' => $pasig->slug]))->assertOk()->assertSee('Submit answers');
        $this->post(route('feedback.satisfaction.store'), [
            'satellite' => $pasig->slug, 'service_date' => $date, 'answers' => $this->answers(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('satisfaction_responses', 6);
    }

    public function test_one_recorded_answer_is_acknowledged_without_exposing_its_percentage(): void
    {
        $satellite = $this->satellite('Pasig', 'MAIN');
        $member = $this->member($satellite, 'single@example.test');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($member)->post(route('feedback.satisfaction.store'), [
            'service_date' => now(config('app.timezone'))->toDateString(), 'answers' => $this->answers(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.analytics.sentiment'))->assertOk()
            ->assertSee('Answers have been recorded')
            ->assertSee('Not enough responses yet')
            ->assertDontSee('No responses yet');
    }

    public function test_five_answers_on_separate_days_do_not_expose_small_daily_groups(): void
    {
        $satellite = $this->satellite('Pasig', 'MAIN');
        foreach (range(1, 5) as $number) {
            $member = $this->member($satellite, "daily{$number}@example.test");
            $this->actingAs($member)->post(route('feedback.satisfaction.store'), [
                'service_date' => now(config('app.timezone'))->subDays($number)->toDateString(),
                'answers' => $this->answers(),
            ])->assertSessionHasNoErrors();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.analytics.sentiment'))->assertOk()
            ->assertSee('The combined result is still hidden because at least one satellite/day group has fewer than 5 responses.')
            ->assertSee('Not enough responses yet');
    }

    public function test_admin_percentages_and_small_groups(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $admin = User::factory()->create(['role' => 'admin']);
        $date = now(config('app.timezone'))->toDateString();
        for ($i = 0; $i < 5; $i++) {
            $member = $this->member($satellite, "member{$i}@example.test");
            $this->actingAs($member)->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers($i === 4 ? 'no' : 'yes')])->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['month' => 'Live', 'year' => now()->year]))
            ->assertOk()->assertSee('80%')->assertSee('36 of 45')->assertSee('Satisfaction');
        $this->getJson(route('admin.analytics.sentiment', ['month' => 'Live', 'year' => now()->year]))
            ->assertOk()->assertJsonPath('selectedMonth', 'Live')->assertJsonStructure(['html', 'date', 'updatedAt'])->assertJsonMissingPath('controls');
        $this->get(route('admin.analytics.sentiment', ['day' => $date, 'satellite' => $satellite->id]))
            ->assertOk()->assertSee('80%');
        $church = DB::table('satisfaction_categories')->where('name', 'Church experience')->value('id');
        $this->get(route('admin.analytics.sentiment', ['day' => $date, 'category' => $church, 'hub' => 'East']))
            ->assertOk()->assertSee('80%')->assertSee('16 of 20');
        $other = $this->satellite('Other', 'West');
        $member = $this->member($other, 'other@example.test');
        $this->actingAs($member)->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers()])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['satellite' => $other->id]))
            ->assertOk()->assertSee('Not enough responses yet');
        $this->get(route('admin.analytics.sentiment'))
            ->assertOk()->assertSee('Not enough responses yet');
    }

    public function test_sentiment_overview_hides_management_and_period_controls_without_affecting_filters_or_shared_controls(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $admin = User::factory()->create(['role' => 'admin']);
        $date = now(config('app.timezone'))->toDateString();
        foreach (range(1, 5) as $number) {
            $member = $this->member($satellite, "overview{$number}@example.test");
            $this->actingAs($member)->post(route('feedback.satisfaction.store'), [
                'service_date' => $date, 'answers' => $this->answers($number === 5 ? 'no' : 'yes'),
            ])->assertSessionHasNoErrors();
        }
        $church = DB::table('satisfaction_categories')->where('name', 'Church experience')->value('id');
        $query = ['month' => 'Live', 'year' => now(config('app.timezone'))->year, 'category' => $church,
            'satellite' => $satellite->id, 'hub' => 'East', 'day' => $date];
        $this->actingAs($admin)->get(route('admin.analytics.sentiment', $query))->assertOk()
            ->assertSee('Category')->assertSee('Satellite')->assertSee('Hub')->assertSee('Selected day')
            ->assertSee('80%')->assertDontSee('Manage questions')
            ->assertDontSee('<div data-dashboard-controls>', false)->assertDontSee('<form class="dashboard-period-controls"', false)
            ->assertDontSee('<p class="sr-only" aria-live="polite" data-period-announce>', false)->assertDontSee('<i class="live-dot"', false);

        // Legacy period URLs remain valid and are honored server-side, but have no visible period selector.
        $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['month' => 'Live', 'year' => now(config('app.timezone'))->year]))
            ->assertOk()->assertDontSee('<form class="dashboard-period-controls"', false);

        // The shared selector still renders in its existing locations.
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('data-period-form', false);
        $satelliteViewer = User::factory()->create(['role' => 'satellite', 'district' => $satellite->name]);
        $this->actingAs($satelliteViewer)->get(route('satellites.dashboard', $satellite))->assertOk()->assertSee('data-period-form', false);
    }

    public function test_month_and_satellite_scope_and_question_lock(): void
    {
        $pasig = $this->satellite('Pasig', 'East');
        $other = $this->satellite('Other', 'West');
        $member = $this->member($pasig, 'month@example.test');
        $date = now(config('app.timezone'))->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->actingAs($member)->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers()])->assertSessionHasNoErrors();
        $viewer = User::factory()->create(['role' => 'satellite', 'district' => $pasig->name]);
        $this->actingAs($viewer)->get(route('satellites.satisfaction', $pasig))->assertOk()->assertSee('Not enough responses yet')->assertDontSee('Other');
        $this->get(route('satellites.satisfaction', $other))->assertForbidden();
        $this->get(route('admin.analytics.sentiment'))->assertForbidden();
        $question = DB::table('satisfaction_questions')->first();
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['category_id' => $question->category_id, 'text' => 'A different meaning?', 'position' => $question->position, 'active' => 1, 'dgroup_only' => 0];
        $this->actingAs($admin)->patch(route('admin.satisfaction.questions.update', $question->id), $payload)->assertSessionHasErrors('text');
        $this->assertSame($question->text, DB::table('satisfaction_questions')->where('id', $question->id)->value('text'));
        $period = now(config('app.timezone'))->subMonthNoOverflow();
        $this->get(route('admin.analytics.sentiment', ['month' => $period->format('F'), 'year' => $period->year]))->assertOk()->assertSee('Not enough responses yet');
    }

    public function test_live_and_monthly_percentages_use_stored_answers(): void
    {
        $satellite = $this->satellite('Pasig', 'East');
        $prior = now(config('app.timezone'))->subMonthNoOverflow()->startOfMonth();
        $today = now(config('app.timezone'))->toDateString();
        foreach (range(1, 10) as $number) {
            $member = $this->member($satellite, "period{$number}@example.test");
            $date = $number <= 5 ? $prior->toDateString() : $today;
            $value = in_array($number, [5, 7, 8, 9, 10], true) ? 'no' : 'yes';
            $this->actingAs($member)->post(route('feedback.satisfaction.store'), ['service_date' => $date, 'answers' => $this->answers($value)])->assertSessionHasNoErrors();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.analytics.sentiment', ['month' => $prior->format('F'), 'year' => $prior->year]))
            ->assertOk()->assertSee('80%')->assertSee('36 of 45');
        $this->get(route('admin.analytics.sentiment', ['month' => 'Live', 'year' => now()->year]))
            ->assertOk()->assertSee('50%')->assertSee('45 of 90');
    }

    public function test_admin_can_manage_catalogue_and_inactive_questions_keep_answers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.satisfaction.categories.store'), ['name' => 'New category', 'position' => 4, 'active' => 1])->assertSessionHasNoErrors();
        $category = DB::table('satisfaction_categories')->where('name', 'New category')->first();
        $this->post(route('admin.satisfaction.questions.store'), [
            'category_id' => $category->id, 'text' => 'Did this help?', 'position' => 1, 'active' => 1, 'dgroup_only' => 0,
        ])->assertSessionHasNoErrors();
        $question = DB::table('satisfaction_questions')->where('category_id', $category->id)->first();
        $this->patch(route('admin.satisfaction.questions.update', $question->id), [
            'category_id' => $category->id, 'text' => 'Did this help?', 'position' => 2, 'active' => 0, 'dgroup_only' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('satisfaction_questions')->where('id', $question->id)->value('position'));
        $this->assertSame(0, DB::table('satisfaction_questions')->where('id', $question->id)->value('active'));
        $this->get(route('admin.satisfaction.manage'))->assertOk()->assertSee('Did this help?');
    }
}

