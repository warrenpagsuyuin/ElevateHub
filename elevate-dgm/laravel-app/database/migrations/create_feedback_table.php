<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('evaluator_user_id')->nullable()->index();
            $table->text('question_1')->nullable();
            $table->text('question_2')->nullable();
            $table->text('question_3')->nullable();
            $table->text('question_4')->nullable();
            $table->text('question_5')->nullable();
            $table->text('message');
            $table->string('sentiment')->nullable();
            $table->decimal('sentiment_score', 6, 2)->nullable();
            $table->decimal('sentiment_confidence', 5, 2)->nullable();
            $table->string('sentiment_model')->nullable();
            $table->string('sentiment_model_version')->nullable();
            $table->json('sentiment_metrics')->nullable();
            $table->timestamp('sentiment_analyzed_at')->nullable()->index();
            $table->string('human_sentiment_label')->nullable()->index();
            $table->unsignedBigInteger('human_labeled_by_id')->nullable()->index();
            $table->timestamp('human_labeled_at')->nullable();
            $table->json('feedback_payload')->nullable();
            $table->string('research_consent_status')->default('Unknown');
            $table->timestamp('research_consented_at')->nullable();
            $table->unsignedBigInteger('research_consent_recorded_by')->nullable();
            $table->unsignedBigInteger('consent_corrected_by')->nullable();
            $table->timestamp('consent_corrected_at')->nullable();
            $table->text('research_consent_evidence')->nullable();
            $table->string('research_consent_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
