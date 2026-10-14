<?php

namespace App\Models;

use App\Services\LeaderAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Member extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const STATUS_NEW = 'New';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_NEW,
    ];

    public const ROLE_LEADER = 'Dgroup Leader';

    public const ROLE_MEMBER = 'Member';

    public const DGROUP_ROLES = [
        self::ROLE_LEADER,
        self::ROLE_MEMBER,
    ];

    public const GENDERS = ['Male', 'Female'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'refresh_date' => 'date',
            'age' => 'integer',
            'leader_member_id' => 'integer',
            'active_disciples' => 'integer',
            'weekly_dgroups_led' => 'integer',
            'year_joined' => 'integer',
            'year' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->exists) {
                static::whereKey($this->getKey())->lockForUpdate()->first();
            }

            return parent::save($options);
        }, 3);
    }

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            if (strtolower(trim((string) $member->status)) === strtolower(self::STATUS_INACTIVE)) {
                $member->status = self::STATUS_INACTIVE;
                $member->archived_at ??= now();
            } elseif ($member->exists && $member->isDirty('status') && $member->archived_at !== null) {
                $member->archived_at = null;
            }

            app(LeaderAssignmentService::class)->prepare($member);
        });

        static::saved(function (Member $member): void {
            app(LeaderAssignmentService::class)->synchronizeName($member);

            if ($member->wasRecentlyCreated || $member->wasChanged(['status', 'archived_at', 'satellite_id', 'dgroup_role'])) {
                static::refreshSatelliteCountsFor([
                    $member->satellite_id,
                    $member->getOriginal('satellite_id'),
                ]);
            }
        });

        static::deleted(function (Member $member): void {
            static::refreshSatelliteCountsFor([$member->satellite_id]);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('members.archived_at')
            ->where(function (Builder $query): void {
                $query->whereNull('members.status')
                    ->orWhere('members.status', '!=', self::STATUS_INACTIVE);
            });
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('members.archived_at');
    }

    public static function refreshSatelliteCountsFor(array $satelliteIds): void
    {
        foreach (array_unique(array_filter(array_map('intval', $satelliteIds))) as $satelliteId) {
            $counts = static::active()->where('satellite_id', $satelliteId)
                ->selectRaw('COUNT(*) as members_count')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as new_members_count', [self::STATUS_NEW])
                ->selectRaw('SUM(CASE WHEN dgroup_role = ? THEN 1 ELSE 0 END) as leaders_count', [self::ROLE_LEADER])
                ->first();

            Satellite::withoutGlobalScope('active')->whereKey($satelliteId)->update([
                'members_count' => (int) ($counts->members_count ?? 0),
                'new_members_count' => (int) ($counts->new_members_count ?? 0),
                'leaders_count' => (int) ($counts->leaders_count ?? 0),
            ]);
        }
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(self::class, 'leader_member_id');
    }

    public function assignedMembers()
    {
        return $this->hasMany(self::class, 'leader_member_id')->active()->where('dgroup_role', self::ROLE_MEMBER);
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class)->withoutGlobalScope('active');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function engagementResult()
    {
        return $this->hasOne(EngagementResult::class);
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->name;
    }

    public function getDisplayEmailAttribute(): ?string
    {
        return $this->email_address ?: $this->email;
    }

    public function getDisplayContactAttribute(): ?string
    {
        return $this->contact_number ?: $this->phone;
    }

    public function getDisplayRoleAttribute(): string
    {
        return $this->dgroup_role ?: Str::of($this->role ?: self::ROLE_MEMBER)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    public function getStatusClassAttribute(): string
    {
        return Str::slug($this->status ?: self::STATUS_ACTIVE);
    }

    public function setBirthdateAttribute(mixed $value): void
    {
        $this->attributes['birthdate'] = $this->dateOnlyValue($value);
    }

    public function setRefreshDateAttribute(mixed $value): void
    {
        $this->attributes['refresh_date'] = $this->dateOnlyValue($value);
    }

    private function dateOnlyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $value instanceof \DateTimeInterface
                ? Carbon::instance($value)->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function detailPayload(): array
    {
        return [
            'id' => $this->id,
            'leader_member_id' => $this->leader_member_id,
            'status' => $this->status ?: self::STATUS_ACTIVE,
            'role' => $this->display_role,
            'lastName' => $this->last_name,
            'firstName' => $this->first_name,
            'fullName' => $this->display_name,
            'gender' => $this->gender,
            'email' => $this->display_email,
            'contact' => $this->display_contact,
            'facebook' => $this->facebook_link,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'age' => $this->age,
            'district' => $this->district,
            'area' => $this->area,
            'school' => $this->campus_school,
            'leader' => $this->dgroup_leader_name,
            'activeDisciples' => $this->active_disciples,
            'yearJoined' => $this->year_joined,
            'ministryInvolvement' => $this->ministry_involvement,
            'weeklyDgroupsLed' => $this->weekly_dgroups_led,
            'generation' => $this->generation,
            'refreshDate' => $this->refresh_date?->format('Y-m-d'),
            'year' => $this->year,
            'month' => $this->month,
        ];
    }

    public function formPayload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status ?: self::STATUS_ACTIVE,
            'dgroup_role' => $this->display_role,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'full_name' => $this->display_name,
            'gender' => $this->gender,
            'email_address' => $this->display_email,
            'contact_number' => $this->display_contact,
            'facebook_link' => $this->facebook_link,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'age' => $this->age,
            'district' => $this->district,
            'area' => $this->area,
            'campus_school' => $this->campus_school,
            'leader_member_id' => $this->leader_member_id,
            'dgroup_leader_name' => $this->dgroup_leader_name,
            'active_disciples' => $this->active_disciples,
            'year_joined' => $this->year_joined,
            'ministry_involvement' => $this->ministry_involvement,
            'weekly_dgroups_led' => $this->weekly_dgroups_led,
            'generation' => $this->generation,
            'refresh_date' => $this->refresh_date?->format('Y-m-d'),
            'year' => $this->year,
            'month' => $this->month,
        ];
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        return in_array($normalized, self::STATUSES, true)
            ? $normalized
            : self::STATUS_ACTIVE;
    }

    public static function normalizeDgroupRole(?string $role): string
    {
        $normalized = Str::of((string) $role)
            ->trim()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->title()
            ->replace('Dgroup', 'Dgroup')
            ->toString();

        if (Str::of($normalized)->lower()->contains('leader')) {
            return self::ROLE_LEADER;
        }

        return self::ROLE_MEMBER;
    }
}
