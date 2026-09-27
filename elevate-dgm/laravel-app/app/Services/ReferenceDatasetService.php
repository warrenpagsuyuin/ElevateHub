<?php

namespace App\Services;

use App\Models\EngagementReferenceDataset;
use App\Models\EngagementReferenceLabel;
use App\Models\EngagementResearchAudit;
use App\Models\EngagementModelRun;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReferenceDatasetService
{
    public const VERSION = 'researcher-provisional-206-v2';
    public const REASON = 'Researcher-entered provisional reference label used for dataset preparation and system evaluation workflow.';
    public const CLARIFICATION = 'Needs Clarification';

    public function current(): ?EngagementReferenceDataset
    {
        return Schema::hasTable('engagement_reference_datasets') ? EngagementReferenceDataset::latest('id')->first() : null;
    }

    public function initialize(?User $actor = null): EngagementReferenceDataset
    {
        if ($actor) {
            $this->authorize($actor);
        }

        return DB::transaction(function () use ($actor): EngagementReferenceDataset {
            $existing = EngagementReferenceDataset::where('version', self::VERSION)->first();
            if ($existing) {
                if ($existing->references()->count() !== $existing->member_count) {
                    throw ValidationException::withMessages(['dataset' => 'The existing cohort is incomplete. Restore its records instead of overwriting review history.']);
                }
                return $existing;
            }
            $members = Member::with('satellite')->orderBy('id')->get();
            if ($members->count() !== 206 || $members->pluck('id')->unique()->count() !== 206) {
                throw ValidationException::withMessages(['dataset' => 'Initialization requires exactly 206 distinct existing members. No labels were changed.']);
            }
            $previous = $this->current();
            if (EngagementReferenceLabel::exists() && (! $previous || $previous->version !== 'researcher-provisional-206-v1'
                || $previous->references()->count() !== 206
                || EngagementReferenceLabel::where('verification_status', '!=', 'Provisional')->exists()
                || EngagementReferenceLabel::where('reference_source', '!=', 'researcher_entered')->exists()
                || EngagementReferenceLabel::whereNotNull('reviewed_at')->exists()
                || EngagementReferenceLabel::whereNotNull('reviewed_by')->exists())) {
                throw ValidationException::withMessages(['dataset' => 'Existing reference labels must be reconciled before initialization; they will not be overwritten.']);
            }
            $assignments = $this->provisionalAssignments($members->map(fn ($member) => ['member_id' => (int) $member->id, 'satellite_id' => $member->satellite_id])->all());
            $previousRows = $previous ? $previous->references()->get()->keyBy('member_id') : collect();
            $end = $previous ? CarbonImmutable::parse($previous->observation_end) : CarbonImmutable::today(config('app.timezone'));
            $start = $previous ? CarbonImmutable::parse($previous->observation_start) : $end->subMonthsNoOverflow(3);
            $dataset = EngagementReferenceDataset::create([
                'version' => self::VERSION, 'revision' => 1, 'seed' => 42, 'member_count' => 206,
                'evaluation_status' => 'Provisional', 'observation_start' => $start, 'observation_end' => $end,
                'created_by' => $actor?->id,
                'assignment_method' => 'Proportional satellite allocation with exact global margins (112/63/25/6), largest remainders, then SHA-256(seed=42:provisional-assignment:member_id) shuffle within each real satellite. IDs are shuffle keys only, never ordinal engagement evidence. Development assignments are unrelated to measured engagement and remain Provisional. Prior v1 references and evidence snapshots retained.',
            ]);
            foreach ($members as $member) {
                $label = $assignments[$member->id];
                EngagementReferenceLabel::create([
                    'member_id' => $member->id, 'satellite_id' => $member->satellite_id,
                    'label' => $label ?? self::CLARIFICATION, 'source' => 'researcher_entered', 'notes' => self::REASON,
                    'reference_label' => $label, 'review_status' => $label === null ? self::CLARIFICATION : 'Final',
                    'reference_source' => 'researcher_entered', 'verification_status' => 'Provisional', 'reason' => self::REASON,
                    'reviewed_by' => null, 'reviewed_at' => null, 'labeled_by_id' => null,
                    'observation_start' => $start, 'observation_end' => $end, 'dataset_version' => $dataset->version,
                    'feature_snapshot' => $previousRows->get($member->id)?->feature_snapshot ?? $this->features($member, $start, $end), 'review_revision' => 1,
                ]);
            }
            if ($previous) {
                EngagementModelRun::whereKey($previous->active_model_run_id)->update(['is_active' => false]);
                $this->audit('reference_dataset.superseded', $previous, $actor, null, $previous->toArray(), ['replacement_version' => $dataset->version, 'history_retained' => true]);
            }
            $this->audit('reference_dataset.created', $dataset, $actor, null, null, [
                'version' => $dataset->version, 'assignment_method' => $dataset->assignment_method,
                'member_ids' => $members->pluck('id')->all(), 'source' => 'researcher_entered', 'verification_status' => 'Provisional',
            ]);
            $this->refreshSplit($dataset, $actor);
            return $dataset->fresh();
        });
    }

    public function records(EngagementReferenceDataset $dataset): Builder
    {
        return EngagementReferenceLabel::with(['member.satellite', 'reviewer'])->where('dataset_version', $dataset->version);
    }

    /** Exact global and satellite margins; seed changes membership, never counts. */
    public function provisionalAssignments(array $members, int $seed = 42): array
    {
        if (count($members) !== 206 || count(array_unique(array_column($members, 'member_id'))) !== 206) {
            throw new InvalidArgumentException('Exactly 206 distinct members are required.');
        }
        $groups = [];
        foreach ($members as $member) {
            if (! $member['satellite_id']) {
                throw ValidationException::withMessages(['dataset' => 'Every member must belong to an existing real satellite.']);
            }
            $groups[$member['satellite_id']][] = $member['member_id'];
        }
        // Largest satellites first; the last receives remaining class margins.
        uksort($groups, fn ($a, $b) => (count($groups[$b]) <=> count($groups[$a])) ?: ($a <=> $b));
        $remaining = [112, 63, 25, 6];
        $population = 206;
        $assigned = [];
        foreach ($groups as $ids) {
            $size = count($ids);
            $quotas = array_map(fn ($count) => $size * $count / $population, $remaining);
            $counts = array_map(fn ($quota) => (int) floor($quota), $quotas);
            $order = [0, 1, 2, 3];
            usort($order, fn ($a, $b) => (($quotas[$b] - $counts[$b]) <=> ($quotas[$a] - $counts[$a])) ?: ($a <=> $b));
            $slots = $size - array_sum($counts);
            foreach (array_slice($order, 0, $slots) as $class) {
                $counts[$class]++;
            }
            usort($ids, fn ($a, $b) => strcmp(hash('sha256', $seed.':provisional-assignment:'.$a), hash('sha256', $seed.':provisional-assignment:'.$b)) ?: ($a <=> $b));
            $offset = 0;
            foreach ($counts as $class => $count) {
                foreach (array_slice($ids, $offset, $count) as $id) {
                    $assigned[$id] = ResearchCartService::LABELS[$class] ?? null;
                }
                $offset += $count;
                $remaining[$class] -= $count;
            }
            $population -= $size;
        }
        ksort($assigned);
        return $assigned;
    }

    public function samples(EngagementReferenceDataset $dataset): array
    {
        return $this->records($dataset)->orderBy('member_id')->get()->map(fn ($row) => [
            'member_id' => (int) $row->member_id,
            'label' => $row->review_status === 'Final' ? $row->reference_label : null,
            'features' => $row->feature_snapshot ?? [],
        ])->all();
    }

    public function refreshSplit(EngagementReferenceDataset $dataset, ?User $actor = null): void
    {
        $query = $this->records($dataset);
        $query->update(['partition' => 'Excluded']);
        try {
            $split = app(ResearchCartService::class)->split($this->samples($dataset), $dataset->seed);
            $summary = $split['summary'];
            $this->records($dataset)->whereIn('member_id', $summary['train_member_ids'])->update(['partition' => 'Training']);
            $this->records($dataset)->whereIn('member_id', $summary['test_member_ids'])->update(['partition' => 'Held-Out Test']);
        } catch (InvalidArgumentException $error) {
            $summary = ['train_count' => 0, 'test_count' => 0, 'classes' => [], 'seed' => $dataset->seed, 'method' => 'Split unavailable', 'error' => $error->getMessage()];
            $this->records($dataset)->where('review_status', 'Final')->whereIn('reference_label', ResearchCartService::LABELS)->update(['partition' => 'Unassigned']);
        }
        $dataset->update(['split_summary' => $summary]);
        $this->audit('dataset.split', $dataset, $actor, null, null, $summary);
    }

    public function verify(EngagementReferenceLabel $reference, User $reviewer, ?string $label, string $reason, ?int $expectedRevision = null): EngagementReferenceLabel
    {
        $this->authorize($reviewer);
        if (($label !== null && ! in_array($label, ResearchCartService::LABELS, true)) || trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Choose an allowed outcome and provide actual review evidence.']);
        }
        return DB::transaction(function () use ($reference, $reviewer, $label, $reason, $expectedRevision) {
            $row = EngagementReferenceLabel::lockForUpdate()->findOrFail($reference->id);
            if ($expectedRevision !== null && $row->review_revision !== $expectedRevision) {
                throw ValidationException::withMessages(['reference' => 'Another reviewer changed this record. Reload and review the current label.']);
            }
            $dataset = EngagementReferenceDataset::where('version', $row->dataset_version)->lockForUpdate()->firstOrFail();
            $before = $row->toArray();
            $row->update([
                'reference_label' => $label, 'label' => $label ?? self::CLARIFICATION,
                'review_status' => $label === null ? self::CLARIFICATION : 'Final',
                'reference_source' => 'human_verified', 'source' => 'human_verified', 'verification_status' => 'Verified',
                'reviewed_by' => $reviewer->id, 'labeled_by_id' => $reviewer->id, 'reviewed_at' => now(),
                'reason' => trim($reason), 'notes' => trim($reason), 'review_revision' => $row->review_revision + 1,
            ]);
            $this->audit('reference_label.updated', $dataset, $reviewer, $row, $before, $row->toArray());
            $this->audit('reference_label.verified', $dataset, $reviewer, $row, $before, $row->toArray());
            if ($dataset->active_model_run_id) {
                EngagementModelRun::whereKey($dataset->active_model_run_id)->update(['is_active' => false]);
            }
            $dataset->update(['revision' => $dataset->revision + 1, 'active_model_run_id' => null, 'evaluation_status' => 'Provisional', 'finalized_at' => null, 'finalized_by' => null]);
            $this->refreshSplit($dataset, $reviewer);
            return $row->fresh();
        });
    }

    public function finalize(EngagementReferenceDataset $dataset, User $reviewer): EngagementReferenceDataset
    {
        $this->authorize($reviewer);
        return DB::transaction(function () use ($dataset, $reviewer) {
            $dataset = EngagementReferenceDataset::lockForUpdate()->findOrFail($dataset->id);
            $verified = $this->records($dataset)->where('verification_status', 'Verified')->where('reference_source', 'human_verified')->whereNotNull('reviewed_by')->whereNotNull('reviewed_at')->whereNotNull('reason')->where('reason', '!=', '')->count();
            if ($verified !== (int) $dataset->member_count || $dataset->references()->count() !== $verified) {
                throw ValidationException::withMessages(['dataset' => 'Human Verified Final requires actual authorized verification of every cohort record.']);
            }
            $before = $dataset->toArray();
            $dataset->update(['evaluation_status' => 'Human Verified Final', 'finalized_by' => $reviewer->id, 'finalized_at' => now()]);
            $this->audit('reference_dataset.finalized', $dataset, $reviewer, null, $before, $dataset->toArray());
            return $dataset;
        });
    }

    public function summary(?EngagementReferenceDataset $dataset = null): array
    {
        $dataset ??= $this->current();
        $rows = $dataset ? $this->records($dataset)->get() : collect();
        $counts = array_fill_keys([...ResearchCartService::LABELS, self::CLARIFICATION], 0);
        foreach ($rows as $row) {
            $label = $row->review_status === self::CLARIFICATION ? self::CLARIFICATION : $row->reference_label;
            if (array_key_exists((string) $label, $counts)) {
                $counts[$label]++;
            }
        }
        $total = $rows->count();
        $distribution = collect($counts)->map(fn ($count, $label) => ['label' => $label, 'frequency' => $count, 'percentage' => $total ? round($count / $total * 100, 2) : 0.0])->values()->all();
        $satellites = $rows->groupBy(fn ($row) => $row->member?->satellite?->name ?? 'Unassigned')->map(function ($group, $satellite) {
            return ['satellite' => $satellite, 'active' => $group->where('reference_label', ResearchCartService::LABELS[0])->where('review_status', 'Final')->count(),
                'moderately_active' => $group->where('reference_label', ResearchCartService::LABELS[1])->where('review_status', 'Final')->count(),
                'follow_up' => $group->where('reference_label', ResearchCartService::LABELS[2])->where('review_status', 'Final')->count(),
                'clarification' => $group->where('review_status', self::CLARIFICATION)->count(), 'total' => $group->count()];
        })->values()->all();
        $verified = $rows->where('verification_status', 'Verified')->where('reference_source', 'human_verified')->count();
        $pct = fn ($label) => number_format($total ? $counts[$label] / $total * 100 : 0, 2, '.', '');
        $kind = $verified === 0 ? 'provisional reference engagement labels entered for dataset preparation' : 'reference engagement labels, including the recorded human verification updates';
        $chapter = 'Table 9 presents the distribution of the '.$total.' member records according to the '.$kind.'. Of the total records, '.$counts['Active'].' or '.$pct('Active').' percent were categorized as Active, '.$counts['Moderately Active'].' or '.$pct('Moderately Active').' percent as Moderately Active, and '.$counts['Possible Need for Follow-Up'].' or '.$pct('Possible Need for Follow-Up').' percent as Possible Need for Follow-Up. '.($counts[self::CLARIFICATION] === 6 ? 'Six' : $counts[self::CLARIFICATION]).' records or '.$pct(self::CLARIFICATION).' percent were marked as Needs Clarification and were not included as a CART target class.';

        return ['available' => Member::count(), 'reference_count' => $total, 'provisional' => $rows->where('verification_status', 'Provisional')->count(), 'verified' => $verified,
            'clarification' => $counts[self::CLARIFICATION], 'eligible' => array_sum($counts) - $counts[self::CLARIFICATION], 'unlabeled' => max(0, Member::count() - $total),
            'distribution' => $distribution, 'satellites' => $satellites, 'chapter_text' => $chapter,
            'evaluation_status' => $dataset?->evaluation_status ?? 'Provisional', 'dataset_version' => $dataset?->revision_version, 'revision' => $dataset?->revision,
            'split' => $dataset?->split_summary ?? ['train_count' => 0, 'test_count' => 0, 'classes' => [], 'seed' => 42, 'method' => 'Not prepared'],
            'source' => $verified === 0 ? 'Researcher Entered' : ($verified === $total ? 'Human Verified' : 'Researcher Entered / Human Verified')];
    }

    public function features(Member $member, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $refresh = $member->refresh_date ? CarbonImmutable::parse($member->refresh_date)->startOfDay() : null;
        $role = strtolower(trim((string) $member->dgroup_role));
        $ministry = $member->ministry_involvement === null ? null : trim((string) $member->ministry_involvement);
        $year = is_numeric($member->year_joined) ? (int) $member->year_joined : null;
        return [
            'follow_up_count_3m' => $member->followUps()->where('status', 'Completed')->whereDate('follow_up_date', '>=', $start->toDateString())->whereDate('follow_up_date', '<=', $end->toDateString())->count(),
            'months_since_refresh' => $refresh && $refresh <= $end ? (float) $refresh->diffInMonths($end) : null,
            'active_disciples' => $member->active_disciples === null ? null : (int) $member->active_disciples,
            'weekly_dgroups_led' => $member->weekly_dgroups_led === null ? null : (int) $member->weekly_dgroups_led,
            'is_dgroup_leader' => $role === '' ? null : (int) ($role === 'dgroup leader'),
            'years_since_joined' => $year && $year > 1900 && $year <= $end->year ? $end->year - $year : null,
            'has_ministry_involvement' => $ministry === null || $ministry === '' ? null : (int) ! in_array(strtolower($ministry), ['none', 'no', 'n/a', 'not involved'], true),
        ];
    }

    public function audit(string $event, ?EngagementReferenceDataset $dataset, ?User $actor, ?EngagementReferenceLabel $reference, ?array $before, ?array $after): void
    {
        EngagementResearchAudit::create(['event' => $event, 'dataset_id' => $dataset?->id, 'actor_id' => $actor?->id,
            'reference_label_id' => $reference?->id, 'before_state' => $before, 'after_state' => $after, 'created_at' => now()]);
    }

    public function authorize(User $user): void
    {
        abort_unless($user->normalizedRole() === 'admin', 403);
    }
}
