<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('follow_ups', 'sentiment_label')) {
                $table->string('sentiment_label')->nullable()->index();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_score')) {
                $table->decimal('sentiment_score', 6, 2)->nullable();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_confidence')) {
                $table->decimal('sentiment_confidence', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_model')) {
                $table->string('sentiment_model')->nullable();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_model_version')) {
                $table->string('sentiment_model_version')->nullable();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_metrics')) {
                $table->json('sentiment_metrics')->nullable();
            }

            if (! Schema::hasColumn('follow_ups', 'sentiment_analyzed_at')) {
                $table->timestamp('sentiment_analyzed_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn([
                'sentiment_label',
                'sentiment_score',
                'sentiment_confidence',
                'sentiment_model',
                'sentiment_model_version',
                'sentiment_metrics',
                'sentiment_analyzed_at',
            ]);
        });
    }
};
