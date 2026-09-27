<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemFeedbackSentimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_can_submit_system_feedback_and_store_sentiment(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)
            ->post(route('feedback.store'), $this->positiveFeedbackAnswers())
            ->assertRedirect(route('feedback.index'));

        $feedback = Feedback::firstOrFail();

        $this->assertSame($user->id, $feedback->evaluator_user_id);
        $this->assertSame(Feedback::SENTIMENT_POSITIVE, $feedback->sentiment);
        $this->assertSame(SentimentAnalysisService::MODEL_NAME, $feedback->sentiment_model);
        $this->assertSame(SentimentAnalysisService::MODEL_VERSION, $feedback->sentiment_model_version);
        $this->assertNotNull($feedback->sentiment_analyzed_at);
        $this->assertNotEmpty($feedback->sentiment_metrics['decision_path']);
        $this->assertSame('Very easy and clear to use.', $feedback->feedback_payload['answers']['question_1']);
        $this->assertStringContainsString('How easy was it to use', $feedback->message);
    }

    public function test_feedback_form_requires_all_five_questions(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)
            ->from(route('feedback.create'))
            ->post(route('feedback.store'), [
                'question_1' => 'Easy to use.',
            ])
            ->assertRedirect(route('feedback.create'))
            ->assertSessionHasErrors(['question_2', 'question_3', 'question_4', 'question_5']);
    }

    public function test_evaluator_can_view_own_feedback_with_explanation(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $feedback = Feedback::create([
            'evaluator_user_id' => $user->id,
            'message' => 'The system is helpful, clear, and useful.',
        ]);

        app(SentimentAnalysisService::class)->analyzeFeedback($feedback);

        $this->actingAs($user)
            ->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('My Feedback')
            ->assertSee('Positive')
            ->assertSee('Why This Sentiment?')
            ->assertSee('The system is helpful, clear, and useful');
    }

    public function test_admin_sentiment_analytics_shows_reference_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $positive = Feedback::create([
            'evaluator_user_id' => User::factory()->create()->id,
            'message' => 'The system is helpful, clear, accurate, and useful.',
            'human_sentiment_label' => Feedback::SENTIMENT_POSITIVE,
            'human_labeled_by_id' => $admin->id,
            'human_labeled_at' => now(),
        ]);
        $negative = Feedback::create([
            'evaluator_user_id' => User::factory()->create()->id,
            'message' => 'The system is confusing, slow, difficult, and not helpful.',
            'human_sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
            'human_labeled_by_id' => $admin->id,
            'human_labeled_at' => now(),
        ]);

        app(SentimentAnalysisService::class)->analyzeFeedback($positive);
        app(SentimentAnalysisService::class)->analyzeFeedback($negative);

        $this->actingAs($admin)
            ->get(route('admin.analytics.sentiment'))
            ->assertOk()
            ->assertSee('Sentiment Analytics')
            ->assertSee('Accuracy')
            ->assertSee('Precision')
            ->assertSee('Recall')
            ->assertSee('F1 Score')
            ->assertSee('Human Reference Label')
            ->assertSee('Human Feedback Review')
            ->assertSee('UNTESTED')
            ->assertDontSee('100.0%');
    }

    public function test_admin_can_update_human_reference_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $feedback = Feedback::create([
            'evaluator_user_id' => User::factory()->create()->id,
            'message' => 'It was okay overall.',
            'sentiment' => Feedback::SENTIMENT_NEUTRAL,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.analytics.sentiment.reference-label', $feedback), [
                'human_sentiment_label' => Feedback::SENTIMENT_NEUTRAL,
            ])
            ->assertRedirect();

        $feedback->refresh();

        $this->assertNull($feedback->human_sentiment_label);
        $this->assertNull($feedback->human_labeled_by_id);
        $this->assertNull($feedback->human_labeled_at);
    }

    public function test_admin_can_reanalyze_feedback_sentiment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Feedback::create([
            'evaluator_user_id' => User::factory()->create()->id,
            'message' => 'Maganda and malinaw ang system.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.analytics.sentiment.analyze'))
            ->assertSessionHasErrors('evaluation');

        $this->assertNull(Feedback::firstOrFail()->sentiment);
    }

    public function test_non_admin_cannot_access_admin_sentiment_analytics(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)
            ->get(route('admin.analytics.sentiment'))
            ->assertStatus(403);
    }

    /**
     * @return array<string, string>
     */
    private function positiveFeedbackAnswers(): array
    {
        return [
            'question_1' => 'Very easy and clear to use.',
            'question_2' => 'The workflows are helpful and organized.',
            'question_3' => 'The explanations are accurate and useful.',
            'question_4' => 'No missing features noticed.',
            'question_5' => 'Satisfied and willing to recommend it.',
            'message' => 'Overall, the developed system is smooth.',
        ];
    }
}
