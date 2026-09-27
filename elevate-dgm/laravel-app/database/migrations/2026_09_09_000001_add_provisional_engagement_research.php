<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_reference_datasets', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('seed')->default(42);
            $table->unsignedInteger('member_count');
            $table->string('evaluation_status')->default('Provisional');
            $table->text('assignment_method');
            $table->date('observation_start');
            $table->date('observation_end');
            $table->json('split_summary')->nullable();
            $table->unsignedBigInteger('active_model_run_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
        Schema::table('engagement_reference_labels', function (Blueprint $table): void {
            $table->foreignId('satellite_id')->nullable()->constrained()->nullOnDelete();
            $table->date('observation_start')->nullable();
            $table->date('observation_end')->nullable();
            $table->string('reference_label')->nullable()->index();
            $table->string('review_status')->nullable()->index();
            $table->string('reference_source')->default('researcher_entered');
            $table->string('verification_status')->default('Provisional')->index();
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('dataset_version')->nullable()->index();
            $table->string('partition')->nullable();
            $table->json('feature_snapshot')->nullable();
            $table->unsignedInteger('review_revision')->default(1);
            $table->unique(['dataset_version', 'member_id'], 'reference_dataset_member_unique');
        });
        Schema::table('engagement_model_runs', function (Blueprint $table): void {
            $table->string('dataset_version')->nullable()->index();
            $table->string('evaluation_status')->default('Provisional');
        });
        Schema::create('engagement_research_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event')->index();
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->unsignedBigInteger('reference_label_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_research_audits');
        Schema::table('engagement_model_runs', fn (Blueprint $table) => $table->dropColumn(['dataset_version', 'evaluation_status']));
        Schema::table('engagement_reference_labels', function (Blueprint $table): void {
            $table->dropUnique('reference_dataset_member_unique');
            $table->dropForeign(['satellite_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['satellite_id', 'observation_start', 'observation_end', 'reference_label', 'review_status', 'reference_source', 'verification_status', 'reason', 'reviewed_by', 'reviewed_at', 'dataset_version', 'partition', 'feature_snapshot', 'review_revision']);
        });
        Schema::dropIfExists('engagement_reference_datasets');
    }
};
