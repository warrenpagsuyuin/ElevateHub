<?php

namespace Tests\Feature;

use App\Models\Satellite;
use App\Models\User;
use App\Services\SatisfactionCommentEvaluation;
use App\Services\SatisfactionCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SatisfactionCommentReviewAndEvalTest extends TestCase
{
    use RefreshDatabase;

    private function createComment(string $text, string $predicted = 'Positive'): string
    {
        $satellite = Satellite::firstOrCreate(['name' => 'Pasig Review Hub'], ['slug' => 'pasig-hub-'.Str::random(4), 'hub' => 'East']);
        $respId = (string) Str::uuid();
        DB::table('satisfaction_responses')->insert([
            'id' => $respId,
            'service_date' => now()->toDateString(),
            'satellite_id' => $satellite->id,
            'hub' => 'East',
        ]);

        $commentId = (string) Str::uuid();
        DB::table('satisfaction_comments')->insert([
            'id' => $commentId,
            'response_id' => $respId,
            'comment' => $text,
            'research_consent' => true,
            'predicted_sentiment' => $predicted,
            'sentiment_score' => 1.2,
            'sentiment_confidence' => 0.85,
            'sentiment_model_version' => \App\Services\SentimentAnalysisService::MODEL_VERSION,
            'analyzed_at' => now(),
        ]);

        return $commentId;
    }

    public function test_reviewer_cannot_see_predicted_sentiment_on_review_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-rev@elevate.test']);
        $commentId = $this->createComment('Blessed gathering and clear preaching.', 'Positive');

        $response = $this->actingAs($admin)->get(route('admin.satisfaction-comments.review'));
        $response->assertOk();
        // The comment text should appear
        $response->assertSee('Blessed gathering and clear preaching.');
        // The word "Positive" must not appear as a system prediction label in the workbench
        $response->assertDontSee('System prediction: Positive');
        $response->assertDontSee('Pasig Review Hub');
        $response->assertDontSee(now()->toDateString());
        $response->assertDontSee('sentiment_confidence');
        $response->assertDontSee('predicted_sentiment');
        $response->assertDontSee('sentiment_model_version');
    }

    public function test_review_filters_skip_and_label_submission_preserve_the_blind_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->createComment('First blind fixture.');
        $second = $this->createComment('Second blind fixture.');
        [$first, $second] = collect([$first, $second])->sort()->values()->all();
        $url = route('admin.satisfaction-comments.review');
        foreach (['mine', 'awaiting', 'adjudication', 'final', 'all'] as $filter) {
            $this->actingAs($admin)->get($url.'?status='.$filter)->assertOk()->assertViewHas('filter', $filter);
        }
        $this->get($url.'?after='.$first)->assertOk()->assertViewHas('current', fn ($comment) => $comment->id === $second);
        $this->post(route('admin.satisfaction-comments.label', $first), ['label' => 'Neutral', 'status' => 'mine', 'page' => 1])->assertRedirect();
        $this->assertDatabaseHas('satisfaction_comment_reviews', ['comment_id' => $first, 'reviewer_id' => $admin->id, 'label' => 'Neutral']);
        $this->get($url)->assertOk()->assertViewHas('current', fn ($comment) => $comment->id === $second);
    }

    public function test_two_reviewers_agreeing_sets_final_label_automatically(): void
    {
        $admin1 = User::factory()->create(['role' => 'admin', 'email' => 'admin1@elevate.test']);
        $admin2 = User::factory()->create(['role' => 'admin', 'email' => 'admin2@elevate.test']);
        $commentId = $this->createComment('Great meeting and very helpful leaders.', 'Positive');

        // Reviewer 1
        $this->actingAs($admin1)->post(route('admin.satisfaction-comments.label', $commentId), [
            'label' => 'Positive'
        ])->assertRedirect();

        $comment = DB::table('satisfaction_comments')->where('id', $commentId)->first();
        $this->assertNull($comment->final_label);

        // Reviewer 2 agrees
        $this->actingAs($admin2)->post(route('admin.satisfaction-comments.label', $commentId), [
            'label' => 'Positive'
        ])->assertRedirect();

        $comment = DB::table('satisfaction_comments')->where('id', $commentId)->first();
        $this->assertSame('Positive', $comment->final_label);
        $this->assertSame('Agreement', $comment->final_label_source);
    }

    public function test_same_reviewer_cannot_label_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-double@elevate.test']);
        $commentId = $this->createComment('Average service.', 'Neutral');

        $this->actingAs($admin)->post(route('admin.satisfaction-comments.label', $commentId), [
            'label' => 'Neutral'
        ])->assertRedirect();

        // Second attempt
        $response = $this->actingAs($admin)->post(route('admin.satisfaction-comments.label', $commentId), [
            'label' => 'Neutral'
        ]);

        $response->assertSessionHasErrors('label');
        $this->assertDatabaseCount('satisfaction_comment_reviews', 1);
    }

    public function test_disagreement_requires_third_adjudicator(): void
    {
        $admin1 = User::factory()->create(['role' => 'admin', 'email' => 'admin-a@elevate.test']);
        $admin2 = User::factory()->create(['role' => 'admin', 'email' => 'admin-b@elevate.test']);
        $admin3 = User::factory()->create(['role' => 'admin', 'email' => 'admin-c@elevate.test']);
        $commentId = $this->createComment('Disorganized sound system.', 'Negative');

        $this->actingAs($admin1)->post(route('admin.satisfaction-comments.label', $commentId), ['label' => 'Negative']);
        $this->actingAs($admin2)->post(route('admin.satisfaction-comments.label', $commentId), ['label' => 'Neutral']);

        $comment = DB::table('satisfaction_comments')->where('id', $commentId)->first();
        $this->assertNull($comment->final_label);

        // Reviewer 1 cannot adjudicate their own case
        $this->actingAs($admin1)->post(route('admin.satisfaction-comments.adjudicate', $commentId), [
            'label' => 'Negative'
        ])->assertSessionHasErrors('label');

        // Third admin adjudicates
        $this->actingAs($admin3)->post(route('admin.satisfaction-comments.adjudicate', $commentId), [
            'label' => 'Negative'
        ])->assertRedirect();

        $comment = DB::table('satisfaction_comments')->where('id', $commentId)->first();
        $this->assertSame('Negative', $comment->final_label);
        $this->assertSame('Adjudication', $comment->final_label_source);
    }

    public function test_evaluation_csv_export_returns_stream_with_proper_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-export@elevate.test']);
        $commentId = $this->createComment('Clear sound and nice venue.', 'Positive');

        // Set as finalized
        DB::table('satisfaction_comments')->where('id', $commentId)->update([
            'final_label' => 'Positive',
            'final_label_source' => 'Agreement'
        ]);

        $response = $this->actingAs($admin)->get(route('admin.satisfaction-comments.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('comment_ref,predicted_sentiment,reference_label', $response->streamedContent());
    }

    public function test_non_admin_cannot_access_review_or_export(): void
    {
        $member = User::factory()->create(['role' => 'member', 'email' => 'member-blocked@elevate.test']);

        $this->actingAs($member)->get(route('admin.satisfaction-comments.review'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.satisfaction-comments.export'))->assertForbidden();
    }
}
