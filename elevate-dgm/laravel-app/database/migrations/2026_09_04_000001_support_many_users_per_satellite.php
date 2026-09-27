<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'district')) {
            return;
        }

        $this->dropUniqueDistrictIndexes();
        $this->addDistrictIndex();
        $this->backfillUserDistrictsFromLegacySatelliteAssignment();
    }

    public function down(): void
    {
        try {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_district_index');
            });
        } catch (Throwable) {
            //
        }
    }

    private function addDistrictIndex(): void
    {
        try {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('district', 'users_district_index');
            });
        } catch (Throwable) {
            //
        }
    }

    private function backfillUserDistrictsFromLegacySatelliteAssignment(): void
    {
        if (! Schema::hasColumn('satellites', 'assigned_user_id')) {
            return;
        }

        DB::table('satellites')
            ->select('name', 'assigned_user_id')
            ->whereNotNull('assigned_user_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $satellite): void {
                DB::table('users')
                    ->where('id', $satellite->assigned_user_id)
                    ->where(function ($query): void {
                        $query->whereNull('district')
                            ->orWhere('district', '');
                    })
                    ->update(['district' => $satellite->name]);
            });
    }

    private function dropUniqueDistrictIndexes(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->dropSqliteUniqueDistrictIndexes(),
            'mysql', 'mariadb' => $this->dropMysqlUniqueDistrictIndexes(),
            'pgsql' => $this->dropPostgresUniqueDistrictIndexes(),
            default => null,
        };
    }

    private function dropSqliteUniqueDistrictIndexes(): void
    {
        foreach (DB::select("PRAGMA index_list('users')") as $index) {
            $name = (string) ($index->name ?? '');

            if ((int) ($index->unique ?? 0) !== 1 || $name === '') {
                continue;
            }

            $columns = collect(DB::select('PRAGMA index_info("'.$name.'")'))
                ->pluck('name')
                ->values()
                ->all();

            if ($columns === ['district']) {
                DB::statement('DROP INDEX "'.$name.'"');
            }
        }
    }

    private function dropMysqlUniqueDistrictIndexes(): void
    {
        foreach (DB::select("SHOW INDEX FROM users WHERE Column_name = 'district' AND Non_unique = 0") as $index) {
            $name = (string) ($index->Key_name ?? '');

            if ($name !== '' && $name !== 'PRIMARY') {
                DB::statement('ALTER TABLE users DROP INDEX `'.$name.'`');
            }
        }
    }

    private function dropPostgresUniqueDistrictIndexes(): void
    {
        $indexes = DB::select(<<<'SQL'
            SELECT schemaname, indexname
            FROM pg_indexes
            WHERE tablename = 'users'
              AND indexdef ILIKE 'CREATE UNIQUE INDEX%'
              AND indexdef ILIKE '%(district)%'
        SQL);

        foreach ($indexes as $index) {
            $schema = str_replace('"', '""', (string) $index->schemaname);
            $name = str_replace('"', '""', (string) $index->indexname);

            DB::statement('DROP INDEX IF EXISTS "'.$schema.'"."'.$name.'"');
        }
    }
};
