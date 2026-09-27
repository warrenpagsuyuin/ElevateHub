<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback_analysis_units', function (Blueprint $table): void {
            $table->boolean('is_test_record')->nullable()->after('scope_status');
            $table->unsignedBigInteger('scope_reviewed_by')->nullable()->after('scope_reason');
            $table->timestamp('scope_reviewed_at')->nullable()->after('scope_reviewed_by');
            $table->string('language_review_status')->default('Pending')->after('language_group');
            $table->unsignedBigInteger('language_reviewed_by')->nullable()->after('language_review_status');
            $table->timestamp('language_reviewed_at')->nullable()->after('language_reviewed_by');
            $table->string('review_independence')->nullable()->after('human_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_analysis_units', function (Blueprint $table): void {
            $table->dropColumn([
                'is_test_record',
                'scope_reviewed_by',
                'scope_reviewed_at',
                'language_review_status',
                'language_reviewed_by',
                'language_reviewed_at',
                'review_independence',
            ]);
        });
    }
};