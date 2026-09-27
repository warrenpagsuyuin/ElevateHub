<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private const SATELLITE_MANAGEMENT_ROLES = [
        'district',
        'district_user',
        'leader',
        'satellite',
        'satellite_user',
        'staff',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'district',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class, 'district', 'name');
    }

    public function assignedSatellites(): HasMany
    {
        return $this->hasMany(Satellite::class, 'name', 'district');
    }

    public function satelliteForDistrict(): ?Satellite
    {
        $district = trim((string) $this->district);

        if ($district === '') {
            return null;
        }

        return Satellite::query()
            ->where('slug', Str::slug($district))
            ->orWhere('name', $district)
            ->first();
    }

    public function canAccessSatellite(Satellite $satellite): bool
    {
        return trim((string) $this->district) !== ''
            && Str::slug((string) $this->district) === $satellite->slug;
    }

    public function canManageSatellite(Satellite $satellite): bool
    {
        return $this->canAccessSatellite($satellite)
            && in_array($this->normalizedRole(), self::SATELLITE_MANAGEMENT_ROLES, true);
    }

    public function normalizedRole(): string
    {
        return str_replace([' ', '-'], '_', strtolower((string) ($this->role ?: 'member')));
    }

    public function assignedRecommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'assigned_to');
    }

    public function createdRecommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'assigned_by');
    }

    public function reviewedRecommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'reviewed_by');
    }
}
