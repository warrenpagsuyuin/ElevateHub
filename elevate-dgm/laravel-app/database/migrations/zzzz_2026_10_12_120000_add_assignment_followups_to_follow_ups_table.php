<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->string('method')->nullable()->default(null)->change();
            $table->string('origin')->nullable()->index();
            $table->foreignId('leader_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->index(['seeker_id', 'origin', 'status'], 'followups_seeker_origin_status_index');
        });
    }

    public function down(): void
    {
        DB::table('follow_ups')->whereNull('method')->update(['method' => 'Phone']);

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->dropIndex('followups_seeker_origin_status_index');
            $table->dropConstrainedForeignId('leader_member_id');
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
            $table->string('method')->default('Phone')->nullable(false)->change();
        });
    }
};
