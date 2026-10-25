<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'estimated_age_at_entry')) {
                $table->unsignedSmallInteger('estimated_age_at_entry')->nullable()->after('age');
            }

            if (! Schema::hasColumn('members', 'age_estimated_at')) {
                $table->date('age_estimated_at')->nullable()->after('estimated_age_at_entry');
            }

            if (! Schema::hasColumn('members', 'age_is_estimated')) {
                $table->boolean('age_is_estimated')->default(false)->after('age_estimated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['estimated_age_at_entry', 'age_estimated_at', 'age_is_estimated'],
                fn (string $column): bool => Schema::hasColumn('members', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
