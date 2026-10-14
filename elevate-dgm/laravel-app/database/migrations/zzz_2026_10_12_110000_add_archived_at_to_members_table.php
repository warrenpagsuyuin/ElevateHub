<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'archived_at')) {
            return;
        }

        Schema::table('members', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('members', 'archived_at')) {
            return;
        }

        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};