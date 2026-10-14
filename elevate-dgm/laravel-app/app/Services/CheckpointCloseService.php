<?php

namespace App\Services;

use App\Models\Checkpoint;
use App\Models\OrgUnit;
use App\Models\UnitSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckpointCloseService
{
    public function review(User $actor, Checkpoint $checkpoint, string $basis = 'system'): array
    {
        $this->authorize($actor);
        if (! in_array($basis, ['system', 'legacy_import'], true)) {
            $this->fail('basis', 'Choose system records or imported evidence.');
        }
        $report = app(MovementAnalyticsService::class)->report($checkpoint->key, mode: $basis === 'system' ? 'auto' : 'reported');
        $all = OrgUnit::orderBy('id')->get()->keyBy('id');
        if ($checkpoint->status === 'closed') {
            $frozenIds = UnitSnapshot::where('checkpoint_id', $checkpoint->id)->where('source', 'system')->whereNotNull('closed_at')->pluck('unit_id')->all();
            if ($frozenIds) {
                $all = $all->only($frozenIds);
            }
        }
        $parentIds = $all->pluck('parent_id')->filter()->all();
        $leaves = $all->reject(fn ($u) => in_array($u->id, $parentIds, true));
        $mayFinalize = $actor->normalizedRole() === 'admin';
        $allowed = $mayFinalize ? $leaves : $leaves->filter(fn ($u) => $this->canAttest($actor, $u));
        $attestations = DB::table('checkpoint_attestations')->where('checkpoint_id', $checkpoint->id)->where('basis', $basis)->orderByDesc('id')->get();
        $blockers = [];
        $units = [];
        if ($checkpoint->status === 'closed') {
            $blockers[] = ['code' => 'closed', 'reason' => 'This checkpoint is already closed. Use an audited correction.'];
        }
        if ($checkpoint->as_of_date->toDateString() >= now('Asia/Manila')->toDateString()) {
            $blockers[] = ['code' => 'not_due', 'reason' => 'Close is available only after the checkpoint day has ended in Asia/Manila.'];
        }
        if ($leaves->isEmpty()) {
            $blockers[] = ['code' => 'no_units', 'reason' => 'Configure the organizational hierarchy first.'];
        }
        if ($checkpoint->status === 'open' && UnitSnapshot::where('checkpoint_id', $checkpoint->id)->where('source', 'system')->whereNotNull('closed_at')->exists()) {
            $blockers[] = ['code' => 'locked_snapshot', 'reason' => 'A separately locked system snapshot must be reviewed before finalization.'];
        }
        foreach ($allowed as $unit) {
            $row = $report['units'][$unit->key];
            $cells = array_map(fn ($metric) => array_intersect_key($metric, array_flip(['value', 'state', 'source'])), $row['metrics']);
            $fingerprint = $this->hash([$checkpoint->key, $checkpoint->as_of_date->toDateString(), $basis, $unit->id, $cells, config('movement_analytics.definitions')]);
            $attestation = $attestations->first(fn ($a) => $a->unit_id === $unit->id && $a->fingerprint === $fingerprint);
            $missing = [];
            foreach (config('movement_analytics.close.required_metrics') as $metric) {
                if ($metric === 'campus' && ! $unit->has_campus && $cells[$metric]['state'] === 'not_applicable') {
                    continue;
                }
                if ($cells[$metric]['state'] !== 'reported' || $cells[$metric]['value'] === null) {
                    $missing[] = $metric;
                }
            }
            $units[$unit->key] = ['id' => $unit->id, 'name' => $unit->name, 'metrics' => $cells, 'fingerprint' => $fingerprint, 'confirmed' => $attestation !== null, 'missing' => $missing];
            if ($missing) {
                $blockers[] = ['code' => 'missing_counts', 'unit' => $unit->key, 'reason' => $unit->name.': verified counts are missing for '.implode(', ', $missing).'.'];
            } elseif (! $attestation) {
                $blockers[] = ['code' => 'unconfirmed_unit', 'unit' => $unit->key, 'reason' => $unit->name.': confirm the as-of evidence before close.'];
            }
        }
        $issues = array_values(array_filter($report['issues'], function ($issue) use ($checkpoint, $allowed, $mayFinalize) {
            return ($issue['checkpoint'] ?? null) === $checkpoint->key && ($mayFinalize || $allowed->contains('key', $issue['unit'] ?? null));
        }));
        foreach ($issues as $issue) {
            if (in_array($issue['code'], config('movement_analytics.close.blocking_issue_codes'), true)) {
                $blockers[] = $issue;
            }
        }
        $revision = DB::table('checkpoint_revisions')->where('checkpoint_id', $checkpoint->id)->max('version') ?? 0;
        $closure = DB::table('checkpoint_closures')->where('checkpoint_id', $checkpoint->id)->first();
        $review = ['checkpoint' => ['key' => $checkpoint->key, 'label' => $checkpoint->label, 'status' => $checkpoint->status], 'basis' => $basis,
            'may_finalize' => (bool) $mayFinalize, 'units' => $units, 'blockers' => $blockers, 'issues' => $issues,
            'ready' => $checkpoint->status === 'open' && ! $blockers && $units !== [], 'revision' => (int) $revision,
            'closed_by' => $closure?->actor_id, 'closed_at' => $closure?->created_at];
        $review['token'] = $this->hash([$review, $this->sourceDigest($checkpoint, $basis), config('movement_analytics.close')]);

        return $review;
    }

    public function attest(User $actor, Checkpoint $checkpoint, array $input): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $checkpoint, $input) {
            $checkpoint = Checkpoint::whereKey($checkpoint->id)->lockForUpdate()->firstOrFail();
            if ($checkpoint->status !== 'open') {
                $this->fail('checkpoint', 'Closed evidence cannot be re-attested.');
            }
            $review = $this->review($actor, $checkpoint, $input['basis']);
            $unit = $review['units'][$input['unit']] ?? null;
            abort_unless($unit && $this->canAttest($actor, OrgUnit::findOrFail($unit['id'])), 403);
            if (! hash_equals($unit['fingerprint'], $input['fingerprint'])) {
                $this->fail('fingerprint', 'Unit counts changed. Review the latest evidence.');
            }
            if ($unit['missing']) {
                $this->fail('unit', 'Import or complete verified dated records before attesting this unit.');
            }
            if ($checkpoint->as_of_date->toDateString() >= now('Asia/Manila')->toDateString()) {
                $this->fail('checkpoint', 'The checkpoint day has not ended.');
            }
            $this->evidence($input);
            DB::table('checkpoint_attestations')->insert(['checkpoint_id' => $checkpoint->id, 'unit_id' => $unit['id'], 'basis' => $input['basis'], 'fingerprint' => $unit['fingerprint'],
                'actor_id' => $actor->id, 'reason' => trim($input['reason']), 'evidence_reference' => trim($input['evidence_reference']), 'created_at' => now()]);

            return $this->review($actor, $checkpoint, $input['basis']);
        });
    }

    public function close(User $actor, Checkpoint $checkpoint, array $input): array
    {
        $this->authorize($actor);
        abort_unless($actor->normalizedRole() === 'admin', 403);

        return DB::transaction(function () use ($actor, $checkpoint, $input) {
            $checkpoint = Checkpoint::whereKey($checkpoint->id)->lockForUpdate()->firstOrFail();
            $review = $this->review($actor, $checkpoint, $input['basis']);
            if ($checkpoint->status === 'closed') {
                $this->fail('checkpoint', 'Already closed; no duplicate close was written.');
            }
            if (! hash_equals($review['token'], $input['token'])) {
                $this->fail('token', 'The evidence changed after review. Refresh and review again.');
            }
            if (! $review['ready']) {
                $this->fail('checkpoint', 'Resolve all close blockers first.');
            }
            if (($input['confirmation'] ?? '') !== $checkpoint->key || ! ($input['acknowledge_issues'] ?? false)) {
                $this->fail('confirmation', 'Type the checkpoint key and acknowledge the quality report.');
            }
            $this->evidence($input);
            $report = app(MovementAnalyticsService::class)->report($checkpoint->key, mode: $input['basis'] === 'system' ? 'auto' : 'reported');
            $at = now();
            foreach ($report['units'] as $unit) {
                $snapshot = UnitSnapshot::firstOrNew(['checkpoint_id' => $checkpoint->id, 'unit_id' => $unit['id'], 'source' => 'system']);
                if ($snapshot->exists && $snapshot->closed_at) {
                    $this->fail('checkpoint', 'A separately locked system snapshot needs review before the checkpoint can close.');
                }
                $states = [];
                foreach ($unit['metrics'] as $metric => $cell) {
                    $snapshot->$metric = $cell['value'];
                    $states[$metric] = $cell['state'];
                }
                $snapshot->metric_states = $states;
                $snapshot->conflict = false;
                $snapshot->quality_flags = array_values(array_filter($review['issues'], fn ($issue) => ($issue['unit'] ?? null) === $unit['key']));
                $snapshot->quality_flags = [...$snapshot->quality_flags, ['code' => 'close_basis', 'basis' => $input['basis']]];
                $snapshot->closed_by = $actor->id;
                $snapshot->closed_at = $at;
                $snapshot->save();
            }
            // Immutable originals remain separate from any later correction version.
            DB::table('checkpoint_closures')->insert(['checkpoint_id' => $checkpoint->id, 'basis' => $input['basis'], 'fingerprint' => $review['token'], 'actor_id' => $actor->id,
                'reason' => trim($input['reason']), 'evidence_reference' => trim($input['evidence_reference']), 'review' => json_encode($review, JSON_THROW_ON_ERROR), 'created_at' => $at]);
            $checkpoint->update(['status' => 'closed']);

            return ['checkpoint' => $checkpoint->key, 'status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => $at->toIso8601String(), 'revision' => 0];
        });
    }

    public function correct(User $actor, Checkpoint $checkpoint, array $input): array
    {
        $this->authorize($actor);
        // Organization-wide reconciled revisions are finalized by the same authorized closer.
        abort_unless($actor->normalizedRole() === 'admin', 403);

        return DB::transaction(function () use ($actor, $checkpoint, $input) {
            $checkpoint = Checkpoint::whereKey($checkpoint->id)->lockForUpdate()->firstOrFail();
            if ($checkpoint->status !== 'closed') {
                $this->fail('checkpoint', 'Corrections apply only after close.');
            }
            $version = (int) (DB::table('checkpoint_revisions')->where('checkpoint_id', $checkpoint->id)->max('version') ?? 0);
            if ((int) $input['expected_version'] !== $version) {
                $this->fail('expected_version', 'A correction was recorded. Review the latest version first.');
            }
            $unit = OrgUnit::where('key', $input['unit'])->firstOrFail();
            $closedUnitIds = UnitSnapshot::where('checkpoint_id', $checkpoint->id)->where('source', 'system')->whereNotNull('closed_at')->pluck('unit_id')->all();
            if (! in_array($unit->id, $closedUnitIds, true)) {
                $this->fail('unit', 'This unit was not part of the original checkpoint close.');
            }
            if (OrgUnit::whereIn('id', $closedUnitIds)->where('parent_id', $unit->id)->exists()) {
                $this->fail('unit', 'Correct a leaf unit; parent totals are recalculated automatically.');
            }
            $this->evidence($input);
            $report = app(MovementAnalyticsService::class)->report($checkpoint->key);
            $units = [];
            foreach ($report['units'] as $row) {
                foreach ($row['metrics'] as $metric => $cell) {
                    $units[$row['id']][$metric] = array_intersect_key($cell, array_flip(['value', 'state', 'source']));
                }
            }
            $before = $units;
            foreach ($input['values'] as $metric => $value) {
                if (! in_array($metric, config('movement_analytics.metrics'), true)) {
                    $this->fail('values', 'Unknown metric.');
                }
                if ($metric === 'campus' && ! $unit->has_campus) {
                    if ($value !== null) {
                        $this->fail('values', 'Campus is not applicable for this unit.');
                    }
                    $state = 'not_applicable';
                } else {
                    if ($value === null && $metric !== 'new_leaders') {
                        $this->fail('values', 'Required corrected counts cannot be blank.');
                    }
                    if ($value !== null && (! is_int($value) || $value < 0 || $value > 2147483647)) {
                        $this->fail('values', 'Counts must be nonnegative whole numbers.');
                    }
                    $state = $value === null ? 'not_reported' : 'reported';
                }
                $units[$unit->id][$metric] = ['value' => $value, 'state' => $state, 'source' => 'correction'];
            }
            // Reconcile the original frozen scope, excluding units added after close.
            $all = OrgUnit::whereIn('id', $closedUnitIds)->get()->keyBy('id');
            $parentId = $unit->parent_id;
            while ($parentId !== null) {
                foreach (config('movement_analytics.metrics') as $metric) {
                    $units[$parentId][$metric] = app(MovementAnalyticsService::class)->sum($all->where('parent_id', $parentId)->map(fn ($child) => $units[$child->id][$metric])->values()->all());
                }
                $parentId = $all[$parentId]->parent_id;
            }
            $changes = [];
            foreach ($units as $id => $metrics) {
                foreach ($metrics as $metric => $cell) {
                    if ([$cell['value'], $cell['state']] !== [$before[$id][$metric]['value'], $before[$id][$metric]['state']]) {
                        $changes[] = ['unit' => $all[$id]->key, 'metric' => $metric, 'before' => $before[$id][$metric], 'after' => $cell];
                    }
                }
            }
            if (! $changes) {
                $this->fail('values', 'No count or data-state change was supplied.');
            }
            DB::table('checkpoint_revisions')->insert(['checkpoint_id' => $checkpoint->id, 'version' => $version + 1, 'actor_id' => $actor->id, 'reason' => trim($input['reason']),
                'evidence_reference' => trim($input['evidence_reference']), 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'units' => json_encode($units, JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return ['checkpoint' => $checkpoint->key, 'status' => 'closed', 'revision' => $version + 1, 'changes' => $changes];
        });
    }

    private function canAttest(User $actor, OrgUnit $unit): bool
    {
        if ($actor->normalizedRole() !== 'leader') {
            return false;
        }
        $satellites = $unit->satellites;

        return $satellites->isNotEmpty() && $satellites->every(fn ($s) => $actor->canAccessSatellite($s));
    }

    private function authorize(User $actor): void
    {
        abort_unless(in_array($actor->normalizedRole(), ['leader', 'admin'], true), 403);
    }

    private function evidence(array $input): void
    {
        foreach (['reason', 'evidence_reference'] as $field) {
            if (mb_strlen(trim($input[$field] ?? '')) < 10) {
                $this->fail($field, 'Provide a meaningful reason and a verifiable as-of evidence reference (at least 10 characters each).');
            }
        }
    }

    private function hash(array $data): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function sourceDigest(Checkpoint $cp, string $basis): string
    {
        $tables = ['movement_conflict_resolutions', 'org_units', 'org_unit_satellite_mappings'];
        if ($basis === 'system') {
            $tables = [...$tables, 'analytics_member_assignments', 'member_status_history', 'analytics_dgroups', 'analytics_dgroup_memberships', 'analytics_leadership_events'];
        }
        $source = [];
        foreach ($tables as $table) {
            $source[$table] = DB::table($table)->orderBy('id')->get()->all();
        }
        $source['snapshots'] = DB::table('unit_snapshots')->where('checkpoint_id',$cp->id)->orderBy('id')->get()->all();

        return $this->hash($source);
    }

    private function fail(string $key,string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
