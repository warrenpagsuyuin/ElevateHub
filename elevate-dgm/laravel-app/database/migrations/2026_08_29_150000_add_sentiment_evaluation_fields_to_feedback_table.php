<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback')) {
            return;
        }

        Schema::table('feedback', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback', 'evaluator_user_id')) {
                $table->unsignedBigInteger('evaluator_user_id')->nullable()->index();
            }

            foreach (range(1, 5) as $number) {
                $column = 'question_'.$number;

                if (! Schema::hasColumn('feedback', $column)) {
                    $table->text($column)->nullable();
                }
            }

            if (! Schema::hasColumn('feedback', 'feedback_payload')) {
                $table->json('feedback_payload')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_score')) {
                $table->decimal('sentiment_score', 6, 2)->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_confidence')) {
                $table->decimal('sentiment_confidence', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_model')) {
                $table->string('sentiment_model')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_model_version')) {
                $table->string('sentiment_model_version')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_metrics')) {
                $table->json('sentiment_metrics')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'sentiment_analyzed_at')) {
                $table->timestamp('sentiment_analyzed_at')->nullable()->index();
            }

            if (! Schema::hasColumn('feedback', 'human_sentiment_label')) {
                $table->string('human_sentiment_label')->nullable()->index();
            }

            if (! Schema::hasColumn('feedback', 'human_labeled_by_id')) {
                $table->unsignedBigInteger('human_labeled_by_id')->nullable()->index();
            }

            if (! Schema::hasColumn('feedback', 'human_labeled_at')) {
                $table->timestamp('human_labeled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedback')) {
            return;
        }

        Schema::table('feedback', function (Blueprint $table) {
            $columns = [
                'evaluator_user_id',
                'question_1',
                'question_2',
                'question_3',
                'question_4',
                'question_5',
                'feedback_payload',
                'sentiment_score',
                'sentiment_confidence',
                'sentiment_model',
                'sentiment_model_version',
                'sentiment_metrics',
                'sentiment_analyzed_at',
                'human_sentiment_label',
                'human_labeled_by_id',
                'human_labeled_at',
            ];

            $existingColumns = array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('feedback', $column)
            );

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
