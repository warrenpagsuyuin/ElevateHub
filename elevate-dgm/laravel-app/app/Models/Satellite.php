<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Satellite extends Model
{
    use HasFactory;

    protected $fillable = [
        'assigned_user_id',
        'created_by_id',
        'name',
        'slug',
        'hub',
        'leaders_count',
        'members_count',
        'seekers_count',
        'new_members_count',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'leaders_count' => 'integer',
            'members_count' => 'integer',
            'seekers_count' => 'integer',
            'new_members_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', fn ($query) => $query->active());
        static::deleting(function (Satellite $satellite) {
            $satellite->archive(auth()->user());
            return false;
        });
        static::created(function (Satellite $satellite): void {
            static::syncAssignedUserDistrict($satellite);
        });

        static::updated(function (Satellite $satellite): void {
            if ($satellite->wasChanged('assigned_user_id')) {
                static::syncAssignedUserDistrict($satellite);
            }
        });
    }

    public function newEloquentBuilder($query): \App\Models\Builders\SatelliteBuilder
    {
        return new \App\Models\Builders\SatelliteBuilder($query);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('satellites.archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->withoutGlobalScope('active')->whereNotNull('satellites.archived_at');
    }

    public static function dashboardCounts(): array
    {
        $satellites = static::active()->get();
        return [
            'totalSatellites' => $satellites->count(),
            'activeLeaders' => $satellites->sum('leaders_count'),
            'activeMembers' => $satellites->sum('members_count'),
            'newMembers' => $satellites->sum('new_members_count'),
            'openReports' => FollowUp::whereIn('satellite_id', static::active()->select('id'))->where('status', FollowUp::STATUS_PENDING)->count(),
        ];
    }

    public function archive(?User $actor = null): void
    {
        if ($this->archived_at === null) {
            $this->forceFill(['archived_at' => now(), 'archived_by' => $actor?->id])->save();
            app(\App\Services\MovementAnalyticsCache::class)->invalidate($this->getConnection());
        }
    }

    public function restore(): void
    {
        $this->forceFill(['archived_at' => null, 'archived_by' => null])->save();
        app(\App\Services\MovementAnalyticsCache::class)->invalidate($this->getConnection());
    }

    private static function syncAssignedUserDistrict(Satellite $satellite): void
    {
        if ($satellite->assigned_user_id === null) {
            return;
        }

        User::whereKey($satellite->assigned_user_id)
            ->update(['district' => $satellite->name]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getDisplayNameAttribute(): string
    {
        return Str::of($this->name)
            ->lower()
            ->title()
            ->toString();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'district', 'name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function seekers(): HasMany
    {
        return $this->hasMany(Seeker::class, 'assigned_satellite_slug', 'slug');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->active();
    }

    public function archivedMembers(): HasMany
    {
        return $this->hasMany(Member::class)->archived();
    }

    public function allMembers(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
