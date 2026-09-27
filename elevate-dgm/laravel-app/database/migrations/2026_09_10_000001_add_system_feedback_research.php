<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The repository's undated create_feedback_table migration runs later on fresh installs.
        if (Schema::hasTable('feedback')) Schema::table('feedback', function (Blueprint $table) {
            $table->string('research_consent_status')->default('Unknown');
            $table->timestamp('research_consented_at')->nullable();
            $table->unsignedBigInteger('research_consent_recorded_by')->nullable();
            $table->text('research_consent_evidence')->nullable();
        });
        Schema::create('sentiment_research_settings', function (Blueprint $table) {
            $table->id();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('feedback_analysis_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->restrictOnDelete();
            $table->unsignedBigInteger('parent_unit_id')->nullable();
            $table->string('source_key')->unique();
            $table->string('feedback_code')->nullable()->unique();
            $table->text('feedback_text');
            $table->string('text_hash', 64);
            $table->string('scope_status')->default('Pending');
            $table->text('scope_reason')->nullable();
            $table->string('language_group')->nullable();
            $table->string('automatic_language_signal')->nullable();
            $table->string('human_reference_sentiment')->nullable();
            $table->string('human_review_status')->default('Pending');
            $table->unsignedBigInteger('human_reviewed_by')->nullable();
            $table->timestamp('human_reviewed_at')->nullable();
            $table->text('human_label_reason')->nullable();
            $table->boolean('independent_attested')->default(false);
            $table->boolean('quote_approved')->default(false);
            $table->string('system_prediction')->nullable();
            $table->double('system_confidence')->nullable();
            $table->string('sentiment_model_version')->nullable();
            $table->timestamp('predicted_at')->nullable();
            $table->string('prediction_text_hash', 64)->nullable();
            $table->string('dataset_partition')->default('Excluded');
            $table->boolean('is_included')->default(false);
            $table->text('exclusion_reason')->nullable();
            $table->unsignedInteger('review_revision')->default(1);
            $table->timestamps();
        });
        Schema::create('sentiment_research_audits', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('sentiment_evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_signature', 64)->unique();
            $table->string('model_version');
            $table->json('report');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentiment_evaluation_runs');
        Schema::dropIfExists('sentiment_research_audits');
        Schema::dropIfExists('feedback_analysis_units');
        Schema::dropIfExists('sentiment_research_settings');
        if (Schema::hasTable('feedback')) Schema::table('feedback', fn (Blueprint $table) => $table->dropColumn(['research_consent_status','research_consented_at','research_consent_recorded_by','research_consent_evidence']));
    }
};
