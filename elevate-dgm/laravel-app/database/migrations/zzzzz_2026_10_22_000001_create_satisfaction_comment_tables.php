<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Optional questionnaire comment (1:1 with an anonymous satisfaction response) and its
 * blind human reviews. The comment table has no link to users, members or feedback and
 * no created/updated timestamps, matching the anonymity rules of satisfaction_responses.
 * Reviewer and adjudicator identities live only in satisfaction_comment_reviews.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('satisfaction_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('response_id')->unique();
            $table->foreign('response_id')->references('id')->on('satisfaction_responses')->cascadeOnDelete();
            $table->text('comment');
            $table->boolean('research_consent')->default(false);
            $table->string('predicted_sentiment', 16)->nullable();
            $table->decimal('sentiment_score', 6, 2)->nullable();
            $table->decimal('sentiment_confidence', 5, 2)->nullable();
            $table->string('sentiment_model_version', 64)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->string('final_label', 16)->nullable();
            $table->string('final_label_source', 16)->nullable();
            $table->index(['research_consent', 'final_label', 'predicted_sentiment'], 'sat_comments_eval_idx');
            $table->index('predicted_sentiment');
            $table->index('final_label');
            $table->index('sentiment_model_version');
        });

        Schema::create('satisfaction_comment_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('comment_id');
            $table->foreign('comment_id')->references('id')->on('satisfaction_comments')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 16)->default('reviewer');
            $table->string('label', 16);
            $table->timestamp('reviewed_at');
            $table->unique(['comment_id', 'reviewer_id']);
            $table->index(['comment_id', 'role']);
            $table->index(['reviewer_id', 'role']);
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_comment_reviews');
        Schema::dropIfExists('satisfaction_comments');
    }
};
