<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
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
            'active_disciples' => 'integer',
            'weekly_dgroups_led' => 'integer',
            'year_joined' => 'integer',
            'year' => 'integer',
        ];
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class);
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
