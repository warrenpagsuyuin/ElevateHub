<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback_analysis_units', function (Blueprint $table): void {
            $table->string('original_human_reference_sentiment')->nullable()->after('human_reference_sentiment');
            $table->unsignedBigInteger('original_human_reviewed_by')->nullable()->after('human_reviewed_by');
            $table->timestamp('original_human_reviewed_at')->nullable()->after('human_reviewed_at');
        });
    }
    public function down(): void
    {
        Schema::table('feedback_analysis_units', fn (Blueprint $table) => $table->dropColumn(['original_human_reference_sentiment','original_human_reviewed_by','original_human_reviewed_at']));
    }
};
