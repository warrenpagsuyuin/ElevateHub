<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->foreignId('parent_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $t->string('type'); $t->string('name'); $t->string('group')->nullable()->index();
            $t->boolean('has_campus'); $t->unsignedInteger('sort'); $t->timestamps();
        });
        Schema::create('org_unit_satellite_mappings', function (Blueprint $t) {
            $t->id(); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->foreignId('satellite_id')->unique()->constrained('satellites')->restrictOnDelete(); $t->timestamps();
        });
        Schema::create('checkpoints', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->string('label'); $t->date('as_of_date')->unique();
            $t->enum('kind',['baseline','quarter']); $t->enum('status',['open','closed'])->default('open'); $t->timestamps();
        });
        Schema::create('analytics_imports', function (Blueprint $t) {
            $t->id(); $t->string('fingerprint',64)->unique(); $t->json('files'); $t->json('issues');
            $t->unsignedBigInteger('actor_id'); $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('unit_snapshots', function (Blueprint $t) {
            $t->id(); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->foreignId('checkpoint_id')->constrained('checkpoints')->restrictOnDelete();
            foreach (['dleaders','dgroups','dmembers','campus','new_leaders'] as $metric) $t->unsignedInteger($metric)->nullable();
            $t->json('metric_states'); $t->enum('source',['legacy_import','system']);
            $t->timestamp('reported_at')->nullable(); $t->text('remarks')->nullable();
            $t->boolean('conflict')->default(false); $t->json('quality_flags');
            $t->unsignedBigInteger('closed_by')->nullable(); $t->timestamp('closed_at')->nullable();
            $t->timestamps(); $t->unique(['unit_id','checkpoint_id','source'],'unit_checkpoint_source_unique');
        });
        Schema::create('analytics_import_rows', function (Blueprint $t) {
            $t->id(); $t->foreignId('import_id')->constrained('analytics_imports')->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->foreignId('checkpoint_id')->constrained('checkpoints')->restrictOnDelete();
            $t->string('source_tab'); $t->unsignedInteger('row_number'); $t->json('payload');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('analytics_data_issues', function (Blueprint $t) {
            $t->id(); $t->string('fingerprint',64)->unique(); $t->string('code');
            $t->foreignId('unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $t->foreignId('checkpoint_id')->nullable()->constrained('checkpoints')->restrictOnDelete();
            $t->text('reason'); $t->json('details'); $t->string('status')->default('open'); $t->timestamps();
        });
        Schema::create('member_status_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('member_id')->index(); $t->unsignedInteger('version');
            $t->string('old_status')->nullable(); $t->string('new_status'); $t->unsignedBigInteger('actor_id');
            $t->timestamp('effective_at'); $t->timestamp('recorded_at'); $t->text('reason');
            $t->unique(['member_id','version']); $t->index(['member_id','effective_at']);
        });
        // New registries only: no backfill or inferred membership/leadership from name strings.
        Schema::create('analytics_dgroups', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->string('name'); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->unsignedBigInteger('leader_member_id'); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps();
        });
        Schema::create('analytics_dgroup_memberships', function (Blueprint $t) {
            $t->id(); $t->foreignId('dgroup_id')->constrained('analytics_dgroups')->restrictOnDelete();
            $t->unsignedBigInteger('member_id')->index(); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps();
            $t->unique(['dgroup_id','member_id','effective_from'],'analytics_group_member_start_unique');
        });
        Schema::create('analytics_member_assignments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('member_id')->index(); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->unsignedBigInteger('actor_id'); $t->timestamps();
        });
        Schema::create('analytics_leadership_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('member_id')->index(); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->enum('event',['appointed','reactivated','ended']); $t->timestamp('effective_at');
            $t->unsignedBigInteger('actor_id'); $t->timestamp('recorded_at'); $t->text('reason');
        });
        Schema::create('analytics_seeker_placements', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seeker_id')->index(); $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->foreignId('dgroup_id')->nullable()->constrained('analytics_dgroups')->restrictOnDelete();
            $t->enum('event',['assigned','converted','unplaced']); $t->timestamp('effective_at');
            $t->unsignedBigInteger('actor_id'); $t->timestamp('recorded_at'); $t->text('reason');
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['member_status_history','analytics_imports','analytics_import_rows','analytics_leadership_events','analytics_seeker_placements'] as $table) {
                foreach (['UPDATE','DELETE'] as $operation) DB::unprepared("CREATE TRIGGER {$table}_immutable_{$operation} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Append-only analytics history'); END");
            }
            foreach (['UPDATE','DELETE'] as $operation) DB::unprepared("CREATE TRIGGER snapshots_closed_{$operation} BEFORE {$operation} ON unit_snapshots WHEN OLD.closed_at IS NOT NULL OR (SELECT status FROM checkpoints WHERE id=OLD.checkpoint_id)='closed' BEGIN SELECT RAISE(ABORT, 'Closed snapshots are immutable'); END");
            DB::unprepared("CREATE TRIGGER snapshots_closed_INSERT BEFORE INSERT ON unit_snapshots WHEN (SELECT status FROM checkpoints WHERE id=NEW.checkpoint_id)='closed' BEGIN SELECT RAISE(ABORT, 'Checkpoint is closed'); END");
            DB::unprepared("CREATE TRIGGER snapshots_closed_target_UPDATE BEFORE UPDATE ON unit_snapshots WHEN (SELECT status FROM checkpoints WHERE id=NEW.checkpoint_id)='closed' BEGIN SELECT RAISE(ABORT, 'Checkpoint is closed'); END");
            DB::unprepared("CREATE TRIGGER checkpoints_closed_UPDATE BEFORE UPDATE ON checkpoints WHEN OLD.status='closed' BEGIN SELECT RAISE(ABORT, 'Closed checkpoints are immutable'); END");
            DB::unprepared("CREATE TRIGGER checkpoints_closed_DELETE BEFORE DELETE ON checkpoints WHEN OLD.status='closed' BEGIN SELECT RAISE(ABORT, 'Closed checkpoints are immutable'); END");
        }
    }

    public function down(): void
    {
        foreach (['analytics_seeker_placements','analytics_leadership_events','analytics_member_assignments','analytics_dgroup_memberships','analytics_dgroups','member_status_history','analytics_data_issues','analytics_import_rows','unit_snapshots','analytics_imports','checkpoints','org_unit_satellite_mappings','org_units'] as $table) Schema::dropIfExists($table);
    }
};
