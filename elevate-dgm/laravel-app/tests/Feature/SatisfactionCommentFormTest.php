<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use App\Services\SatisfactionCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SatisfactionCommentFormTest extends TestCase
{
    use RefreshDatabase;

    private function setupUserAndSatellite(): array
    {
        $satellite = Satellite::create(['name' => 'Pasig Central', 'slug' => 'pasig-central', 'hub' => 'East']);
        $user = User::factory()->create(['role' => 'member', 'email' => 'juan@example.test', 'district' => 'Pasig Central']);
        Member::create(['name' => 'Juan Dela Cruz', 'email_address' => 'juan@example.test', 'satellite_id' => $satellite->id, 'status' => 'Active']);
        return [$user, $satellite];
    }

    private function getAnswers(): array
    {
        return DB::table('satisfaction_questions')->where('active', true)->where('dgroup_only', false)->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => 'yes'])->all();
    }

    public function test_submit_without_comment_succeeds_and_does_not_create_comment_row(): void
    {
        [$user, $satellite] = $this->setupUserAndSatellite();
        $date = now()->toDateString();

        $response = $this->actingAs($user)->post(route('feedback.satisfaction.store'), [
            'satellite' => $satellite->slug,
            'service_date' => $date,
            'answers' => $this->getAnswers(),
            'comment' => null,
        ]);

        $response->assertRedirect(route('feedback.create', ['satellite' => $satellite->slug]));
        $this->assertDatabaseCount('satisfaction_responses', 1);
        $this->assertDatabaseCount('satisfaction_comments', 0);
    }

    public function test_submit_with_valid_comment_scores_sentiment_and_saves_row(): void
    {
        [$user, $satellite] = $this->setupUserAndSatellite();
        $date = now()->toDateString();

        $response = $this->actingAs($user)->post(route('feedback.satisfaction.store'), [
            'satellite' => $satellite->slug,
            'service_date' => $date,
            'answers' => $this->getAnswers(),
            'comment' => 'Very helpful, easy to understand, and I am very blessed and satisfied!',
            'comment_consent' => '1',
        ]);

        $response->assertRedirect(route('feedback.create', ['satellite' => $satellite->slug]));
        $this->assertDatabaseCount('satisfaction_comments', 1);

        $comment = DB::table('satisfaction_comments')->first();
        $this->assertTrue((bool) $comment->research_consent);
        $this->assertSame('Positive', $comment->predicted_sentiment);
        $this->assertNotNull($comment->sentiment_score);
        $this->assertNotNull($comment->analyzed_at);
    }

    public function test_submit_with_over_1000_characters_fails_validation(): void
    {
        [$user, $satellite] = $this->setupUserAndSatellite();

        $response = $this->actingAs($user)->post(route('feedback.satisfaction.store'), [
            'satellite' => $satellite->slug,
            'service_date' => now()->toDateString(),
            'answers' => $this->getAnswers(),
            'comment' => str_repeat('A', 1001),
        ]);

        $response->assertSessionHasErrors('comment');
        $this->assertDatabaseCount('satisfaction_comments', 0);
    }

    public function test_html_tags_in_comment_are_stripped_and_sanitized(): void
    {
        [$user, $satellite] = $this->setupUserAndSatellite();

        $response = $this->actingAs($user)->post(route('feedback.satisfaction.store'), [
            'satellite' => $satellite->slug,
            'service_date' => now()->toDateString(),
            'answers' => $this->getAnswers(),
            'comment' => '<script>alert("hack")</script><b>Very inspiring</b> service!',
            'comment_consent' => '1',
        ]);

        $response->assertRedirect();
        $comment = DB::table('satisfaction_comments')->first();
        $this->assertStringNotContainsString('<script>', $comment->comment);
        $this->assertStringNotContainsString('<b>', $comment->comment);
        $this->assertStringContainsString('Very inspiring service!', $comment->comment);
    }
}
