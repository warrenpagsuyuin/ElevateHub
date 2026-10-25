<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpSentimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sentiment_service_explains_positive_taglish_notes(): void
    {
        $result = app(SentimentAnalysisService::class)->analyze('Salamat, interested siya and willing to join next week.');
        $positiveCues = collect($result['metrics']['matched_positive_cues'])->pluck('cue')->all();

        $this->assertSame(SentimentAnalysisService::LABEL_POSITIVE, $result['label']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertContains('interested', $positiveCues);
        $this->assertSame('Filipino/Taglish', $result['metrics']['language_signal']);
        $this->assertNotEmpty($result['metrics']['decision_path']);
    }

    public function test_creating_follow_up_stores_sentiment_analysis(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();

        $this->actingAs($satelliteUser)
            ->post(route('satellites.follow-ups.store', $satellite), [
                'seeker_id' => $seeker->id,
                'follow_up_date' => now()->format('Y-m-d'),
                'method' => FollowUp::METHOD_PHONE,
                'notes' => 'The seeker was encouraged and interested to attend.',
                'status' => FollowUp::STATUS_COMPLETED,
            ])
            ->assertRedirect(route('satellites.follow-ups.index', $satellite));

        $followUp = FollowUp::firstOrFail();

        $this->assertSame(FollowUp::SENTIMENT_POSITIVE, $followUp->sentiment_label);
        $this->assertSame(SentimentAnalysisService::MODEL_NAME, $followUp->sentiment_model);
        $this->assertSame(SentimentAnalysisService::MODEL_VERSION, $followUp->sentiment_model_version);
        $this->assertNotNull($followUp->sentiment_analyzed_at);
        $this->assertNotEmpty($followUp->sentiment_metrics['decision_path']);
    }

    public function test_updating_follow_up_reanalyzes_sentiment(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();
        $followUp = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now(),
            'method' => FollowUp::METHOD_EMAIL,
            'notes' => 'No reply after two attempts.',
            'status' => FollowUp::STATUS_PENDING,
        ]);

        app(SentimentAnalysisService::class)->analyzeFollowUp($followUp);
        $this->assertSame(FollowUp::SENTIMENT_NEGATIVE, $followUp->refresh()->sentiment_label);

        $this->actingAs($satelliteUser)
            ->patch(route('satellites.follow-ups.update', [$satellite, $followUp]), [
                'follow_up_date' => now()->format('Y-m-d'),
                'method' => FollowUp::METHOD_PHONE,
                'notes' => 'He confirmed and is willing to join.',
                'status' => FollowUp::STATUS_COMPLETED,
            ])
            ->assertRedirect();

        $this->assertSame(FollowUp::SENTIMENT_POSITIVE, $followUp->refresh()->sentiment_label);
    }

    public function test_empty_notes_remain_unanalyzed_and_later_notes_are_analyzed(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();
        $followUp = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'follow_up_date' => now()->toDateString(),
            'method' => null,
            'notes' => null,
            'status' => FollowUp::STATUS_PENDING,
        ]);
        $service = app(SentimentAnalysisService::class);

        $analyzed = $service->analyzeFollowUp($followUp);
        $this->assertNull($analyzed->sentiment_label);
        $this->assertNull($analyzed->sentiment_confidence);
        $this->assertNull($analyzed->sentiment_analyzed_at);
        $this->assertSame(0, $service->analyzeQuery(FollowUp::whereKey($followUp->id))['count']);

        $followUp->update(['notes' => 'The seeker is interested and thankful.']);
        $analyzed = $service->analyzeFollowUp($followUp->fresh());
        $this->assertNotNull($analyzed->sentiment_label);
        $this->assertNotNull($analyzed->sentiment_confidence);
        $this->assertNotNull($analyzed->sentiment_analyzed_at);
    }

    public function test_satellite_user_can_filter_follow_ups_by_sentiment(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();
        $positive = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now(),
            'method' => FollowUp::METHOD_PHONE,
            'notes' => 'Encouraged and interested.',
            'status' => FollowUp::STATUS_COMPLETED,
        ]);
        $negative = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now()->addDay(),
            'method' => FollowUp::METHOD_EMAIL,
            'notes' => 'No response and not interested.',
            'status' => FollowUp::STATUS_COMPLETED,
        ]);

        app(SentimentAnalysisService::class)->analyzeFollowUp($positive);
        app(SentimentAnalysisService::class)->analyzeFollowUp($negative);

        $this->actingAs($satelliteUser)
            ->get(route('satellites.follow-ups.index', [$satellite, 'sentiment' => FollowUp::SENTIMENT_NEGATIVE]))
            ->assertOk()
            ->assertDontSee('Sentiment Trends')
            ->assertDontSee('Why this sentiment?')
            ->assertSee('<details class="sentiment-result"', false)
            ->assertViewHas('sentimentSummary', fn ($summary) => ! array_key_exists('trend', $summary) && $summary['counts']['Positive'] === 1 && $summary['counts']['Negative'] === 1)
            ->assertSee('No response and not interested')
            ->assertDontSee('Encouraged and interested');
    }

    public function test_satellite_user_can_reanalyze_existing_follow_up_notes(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();
        FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now(),
            'method' => FollowUp::METHOD_PHONE,
            'notes' => 'Ayaw muna and no reply after reminder.',
            'status' => FollowUp::STATUS_PENDING,
        ]);

        $this->actingAs($satelliteUser)
            ->post(route('satellites.follow-ups.analyze-sentiment', $satellite))
            ->assertRedirect();

        $this->assertSame(FollowUp::SENTIMENT_NEGATIVE, FollowUp::firstOrFail()->sentiment_label);
    }

    public function test_satellite_manager_can_reanalyze_follow_up_sentiment(): void
    {
        [$satellite, $satelliteUser, $seeker] = $this->followUpFixture();
        FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => $seeker->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now(),
            'method' => FollowUp::METHOD_EMAIL,
            'notes' => 'He confirmed and was thankful.',
            'status' => FollowUp::STATUS_COMPLETED,
        ]);

        $this->actingAs($satelliteUser)
            ->post(route('satellites.follow-ups.analyze-sentiment', $satellite))
            ->assertRedirect();

        $this->assertSame(FollowUp::SENTIMENT_POSITIVE, FollowUp::firstOrFail()->sentiment_label);
    }

    public function test_non_admin_cannot_view_global_sentiment_analytics(): void
    {
        [, $satelliteUser] = $this->followUpFixture();

        $this->actingAs($satelliteUser)
            ->get(route('admin.analytics.sentiment'))
            ->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_reanalyze_sentiment(): void
    {
        [$satellite] = $this->followUpFixture();
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->post(route('satellites.follow-ups.analyze-sentiment', $satellite))
            ->assertStatus(403);
    }

    /**
     * @return array{0: Satellite, 1: User, 2: Seeker, 3: User}
     */
    private function followUpFixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
            'created_by_id' => $admin->id,
        ]);
        $seeker = Seeker::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Pending',
        ]);

        return [$satellite, $satelliteUser, $seeker, $admin];
    }
}
