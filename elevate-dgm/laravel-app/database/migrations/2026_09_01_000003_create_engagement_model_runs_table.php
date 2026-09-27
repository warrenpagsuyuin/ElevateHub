<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engagement_model_runs')) {
            Schema::create('engagement_model_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('satellite_id')->nullable()->constrained()->nullOnDelete();
                $table->string('model_name');
                $table->string('model_version')->index();
                $table->string('training_signature', 64)->index();
                $table->json('training_config')->nullable();
                $table->json('split_summary')->nullable();
                $table->json('label_distribution')->nullable();
                $table->json('validation_metrics')->nullable();
                $table->json('holdout_metrics')->nullable();
                $table->json('tree')->nullable();
                $table->timestamp('trained_at')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['satellite_id', 'model_name', 'model_version', 'training_signature'], 'engagement_model_run_unique');
            });
        }

        if (Schema::hasTable('engagement_results') && ! Schema::hasColumn('engagement_results', 'engagement_model_run_id')) {
            Schema::table('engagement_results', function (Blueprint $table): void {
                $table->unsignedBigInteger('engagement_model_run_id')->nullable()->index()->after('model_metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('engagement_results') && Schema::hasColumn('engagement_results', 'engagement_model_run_id')) {
            Schema::table('engagement_results', function (Blueprint $table): void {
                $table->dropColumn('engagement_model_run_id');
            });
        }

        Schema::dropIfExists('engagement_model_runs');
    }
};
