<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $satellites = DB::table('satellites')->select('id', 'name')->get();

        foreach ($satellites as $satellite) {
            $assignedUserId = DB::table('users')
                ->where('role', '!=', 'admin')
                ->where('district', $satellite->name)
                ->orderBy('id')
                ->value('id');

            if ($assignedUserId) {
                DB::table('satellites')
                    ->where('id', $satellite->id)
                    ->update(['assigned_user_id' => $assignedUserId]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
