<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use App\Services\SatisfactionCommentService;
use App\Services\SatisfactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SatisfactionCommentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $commentService = app(SatisfactionCommentService::class);

        // Ensure we have 3 admin reviewers
        $admin1 = User::firstOrCreate(['email' => 'reviewer1@elevate.test'], ['name' => 'Reviewer One', 'role' => 'admin', 'password' => bcrypt('secret123')]);
        $admin2 = User::firstOrCreate(['email' => 'reviewer2@elevate.test'], ['name' => 'Reviewer Two', 'role' => 'admin', 'password' => bcrypt('secret123')]);
        $admin3 = User::firstOrCreate(['email' => 'adjudicator@elevate.test'], ['name' => 'Adjudicator Admin', 'role' => 'admin', 'password' => bcrypt('secret123')]);

        // Satellite
        $satellite = Satellite::firstOrCreate(['slug' => 'pasig-demo'], ['name' => 'Pasig Demo', 'hub' => 'East']);

        $samples = [
            // Positive comments
            ['comment' => 'The message today was so inspiring and full of hope! God bless our leaders.', 'pred' => 'Positive', 'rev1' => 'Positive', 'rev2' => 'Positive', 'final' => 'Positive', 'src' => 'Agreement'],
            ['comment' => 'Very welcoming atmosphere. Felt safe and happy during worship.', 'pred' => 'Positive', 'rev1' => 'Positive', 'rev2' => 'Positive', 'final' => 'Positive', 'src' => 'Agreement'],
            ['comment' => 'Napakasaya ng fellowship namin, ang ganda ng teaching.', 'pred' => 'Positive', 'rev1' => 'Positive', 'rev2' => 'Positive', 'final' => 'Positive', 'src' => 'Agreement'],
            ['comment' => 'Great sound system and very accommodating youth volunteers.', 'pred' => 'Positive', 'rev1' => 'Positive', 'rev2' => 'Positive', 'final' => 'Positive', 'src' => 'Agreement'],
            ['comment' => 'Blessed by the fellowship and prayer support.', 'pred' => 'Positive', 'rev1' => 'Positive', 'rev2' => 'Positive', 'final' => 'Positive', 'src' => 'Agreement'],

            // Neutral comments
            ['comment' => 'Gathering started on time and ended as scheduled.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Neutral', 'final' => 'Neutral', 'src' => 'Agreement'],
            ['comment' => 'Regular service routine with normal attendance.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Neutral', 'final' => 'Neutral', 'src' => 'Agreement'],
            ['comment' => 'Attended the afternoon session at Pasig venue.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Neutral', 'final' => 'Neutral', 'src' => 'Agreement'],
            ['comment' => 'Meeting venue location was okay.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Neutral', 'final' => 'Neutral', 'src' => 'Agreement'],
            ['comment' => 'Schedule followed what was announced in the group chat.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Neutral', 'final' => 'Neutral', 'src' => 'Agreement'],

            // Negative comments
            ['comment' => 'Venue was too crowded and air conditioning was broken and uncomfortable.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Negative', 'final' => 'Negative', 'src' => 'Agreement'],
            ['comment' => 'I felt ignored and neglected by the volunteers at the front entrance.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Negative', 'final' => 'Negative', 'src' => 'Agreement'],
            ['comment' => 'Sobrang magulo ang distribution at maingay sa likod, hindi makarinig.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Negative', 'final' => 'Negative', 'src' => 'Agreement'],
            ['comment' => 'Disappointed with the lack of parking and poor organization.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Negative', 'final' => 'Negative', 'src' => 'Agreement'],
            ['comment' => 'The audio was painful and hard to understand the sermon.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Negative', 'final' => 'Negative', 'src' => 'Agreement'],

            // Adjudicated cases (disagreement between rev1 and rev2, resolved by admin3)
            ['comment' => 'The service was quite long today although the music was fine.', 'pred' => 'Neutral', 'rev1' => 'Neutral', 'rev2' => 'Negative', 'adj' => 'Neutral', 'final' => 'Neutral', 'src' => 'Adjudication'],
            ['comment' => 'I hope we can improve the room temperature next week.', 'pred' => 'Negative', 'rev1' => 'Negative', 'rev2' => 'Neutral', 'adj' => 'Negative', 'final' => 'Negative', 'src' => 'Adjudication'],
            ['comment' => 'Okay naman pero could have been better organized.', 'pred' => 'Neutral', 'rev1' => 'Positive', 'rev2' => 'Neutral', 'adj' => 'Neutral', 'final' => 'Neutral', 'src' => 'Adjudication'],

            // Awaiting second review
            ['comment' => 'Looking forward to the youth retreat next month!', 'pred' => 'Positive', 'rev1' => 'Positive'],
            ['comment' => 'Need clearer directions to the auxiliary hall.', 'pred' => 'Neutral', 'rev1' => 'Neutral'],

            // Needs adjudication
            ['comment' => 'Challenging message that made me think about my priorities.', 'pred' => 'Neutral', 'rev1' => 'Positive', 'rev2' => 'Neutral'],

            // Needs first review
            ['comment' => 'First time attending Elevate, thank you for welcoming me.', 'pred' => 'Positive'],
            ['comment' => 'Sound check took longer than expected.', 'pred' => 'Negative'],
        ];

        $today = now()->toDateString();
        $questions = DB::table('satisfaction_questions')->where('active', true)->pluck('id');

        foreach ($samples as $sample) {
            $respId = (string) Str::uuid();
            DB::table('satisfaction_responses')->insert([
                'id' => $respId,
                'service_date' => $today,
                'satellite_id' => $satellite->id,
                'hub' => $satellite->hub,
            ]);

            foreach ($questions as $qid) {
                DB::table('satisfaction_answers')->insert([
                    'response_id' => $respId,
                    'question_id' => $qid,
                    'yes' => true,
                ]);
            }

            $cid = (string) Str::uuid();
            DB::table('satisfaction_comments')->insert([
                'id' => $cid,
                'response_id' => $respId,
                'comment' => $sample['comment'],
                'research_consent' => true,
                'predicted_sentiment' => $sample['pred'] ?? null,
                'sentiment_score' => $sample['pred'] === 'Positive' ? 1.5 : ($sample['pred'] === 'Negative' ? -1.5 : 0.0),
                'sentiment_confidence' => 0.85,
                'sentiment_model_version' => \App\Services\SentimentAnalysisService::MODEL_VERSION,
                'analyzed_at' => now(),
                'final_label' => $sample['final'] ?? null,
                'final_label_source' => $sample['src'] ?? null,
            ]);

            if (isset($sample['rev1'])) {
                DB::table('satisfaction_comment_reviews')->insert([
                    'comment_id' => $cid,
                    'reviewer_id' => $admin1->id,
                    'role' => 'reviewer',
                    'label' => $sample['rev1'],
                    'reviewed_at' => now(),
                ]);
            }
            if (isset($sample['rev2'])) {
                DB::table('satisfaction_comment_reviews')->insert([
                    'comment_id' => $cid,
                    'reviewer_id' => $admin2->id,
                    'role' => 'reviewer',
                    'label' => $sample['rev2'],
                    'reviewed_at' => now(),
                ]);
            }
            if (isset($sample['adj'])) {
                DB::table('satisfaction_comment_reviews')->insert([
                    'comment_id' => $cid,
                    'reviewer_id' => $admin3->id,
                    'role' => 'adjudicator',
                    'label' => $sample['adj'],
                    'reviewed_at' => now(),
                ]);
            }
        }

        $commentService->bumpVersion();
    }
}
