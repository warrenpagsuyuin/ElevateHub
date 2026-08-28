<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->string('source_key')->nullable()->unique()->after('id');
        });

        $this->dropEmailUniqueIndex();
    }

    public function down(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->dropUnique('seekers_source_key_unique');
            $table->dropColumn('source_key');
        });
    }

    private function dropEmailUniqueIndex(): void
    {
        try {
            Schema::table('seekers', function (Blueprint $table): void {
                $table->dropUnique('seekers_email_unique');
            });

            return;
        } catch (Throwable) {
            //
        }

        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('seekers')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === 'seekers_email_unique') {
                    DB::statement('DROP INDEX seekers_email_unique');
                }
            }
        }
    }
};
