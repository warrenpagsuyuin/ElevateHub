<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('leader_member_id')->nullable()->index()->constrained('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropForeign(['leader_member_id']);
            $table->dropIndex(['leader_member_id']);
            $table->dropColumn('leader_member_id');
        });
    }
};
