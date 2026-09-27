<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('feedback')) return;
        Schema::table('feedback', function (Blueprint $table): void {
            if (!Schema::hasColumn('feedback','consent_corrected_by')) $table->unsignedBigInteger('consent_corrected_by')->nullable()->after('research_consent_recorded_by');
            if (!Schema::hasColumn('feedback','consent_corrected_at')) $table->timestamp('consent_corrected_at')->nullable()->after('consent_corrected_by');
        });
    }
    public function down(): void
    {
        if (!Schema::hasTable('feedback')) return;
        Schema::table('feedback', fn (Blueprint $table) => $table->dropColumn(['consent_corrected_by','consent_corrected_at']));
    }
};
