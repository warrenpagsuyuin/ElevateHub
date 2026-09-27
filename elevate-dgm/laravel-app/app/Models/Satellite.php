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
            'leaders_count' => 'integer',
            'members_count' => 'integer',
            'seekers_count' => 'integer',
            'new_members_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Satellite $satellite): void {
            if ($satellite->assigned_user_id === null) {
                return;
            }

            User::whereKey($satellite->assigned_user_id)
                ->where(function ($query): void {
                    $query->whereNull('district')
                        ->orWhere('district', '');
                })
                ->update(['district' => $satellite->name]);
        });
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
        return $this->hasMany(Member::class);
    }
}
