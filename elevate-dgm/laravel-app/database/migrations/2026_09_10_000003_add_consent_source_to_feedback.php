<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('feedback')) return;
        Schema::table('feedback', function (Blueprint $table): void {
            $table->string('research_consent_source')->nullable()->after('research_consent_evidence');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('feedback')) return;
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropColumn('research_consent_source');
        });
    }
};