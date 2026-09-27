<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engagement_results')) {
            return;
        }

        Schema::table('engagement_results', function (Blueprint $table): void {
            if (! Schema::hasColumn('engagement_results', 'model_name')) {
                $table->string('model_name')->nullable()->after('predicted_attendance');
            }

            if (! Schema::hasColumn('engagement_results', 'model_version')) {
                $table->string('model_version')->nullable()->index()->after('model_name');
            }

            if (! Schema::hasColumn('engagement_results', 'decision_path')) {
                $table->json('decision_path')->nullable()->after('metrics');
            }

            if (! Schema::hasColumn('engagement_results', 'used_features')) {
                $table->json('used_features')->nullable()->after('decision_path');
            }

            if (! Schema::hasColumn('engagement_results', 'explanation')) {
                $table->text('explanation')->nullable()->after('used_features');
            }

            if (! Schema::hasColumn('engagement_results', 'data_sufficiency')) {
                $table->string('data_sufficiency')->nullable()->index()->after('explanation');
            }

            if (! Schema::hasColumn('engagement_results', 'generated_by_id')) {
                $table->unsignedBigInteger('generated_by_id')->nullable()->index()->after('data_sufficiency');
            }

            if (! Schema::hasColumn('engagement_results', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->index()->after('generated_by_id');
            }

            if (! Schema::hasColumn('engagement_results', 'human_review_required')) {
                $table->boolean('human_review_required')->default(true)->after('generated_at');
            }

            if (! Schema::hasColumn('engagement_results', 'model_metadata')) {
                $table->json('model_metadata')->nullable()->after('human_review_required');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('engagement_results')) {
            return;
        }

        Schema::table('engagement_results', function (Blueprint $table): void {
            $columns = [
                'model_name',
                'model_version',
                'decision_path',
                'used_features',
                'explanation',
                'data_sufficiency',
                'generated_by_id',
                'generated_at',
                'human_review_required',
                'model_metadata',
            ];

            $existing = array_filter($columns, fn (string $column): bool => Schema::hasColumn('engagement_results', $column));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
