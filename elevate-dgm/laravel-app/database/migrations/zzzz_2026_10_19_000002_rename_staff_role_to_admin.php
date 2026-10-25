<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'access_profile')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('access_profile')->nullable();
            });
        }

        // One atomic update retains the old authorization identity, IDs and timestamps.
        DB::table('users')->whereRaw('LOWER(TRIM(role)) = ?', ['staff'])
            ->update(['role' => 'admin', 'access_profile' => 'satellite_admin']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'access_profile')) {
            DB::table('users')->where('role', 'admin')->where('access_profile', 'satellite_admin')
                ->update(['role' => 'staff']);
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('access_profile'));
        }
    }
};
