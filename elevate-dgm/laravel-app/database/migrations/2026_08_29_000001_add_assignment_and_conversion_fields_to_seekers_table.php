<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->string('dgroup_leader_name')->nullable()->index();
            $table->foreignId('converted_member_id')->nullable()->constrained('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->dropForeign(['converted_member_id']);
            $table->dropColumn(['dgroup_leader_name', 'converted_member_id']);
        });
    }
};
