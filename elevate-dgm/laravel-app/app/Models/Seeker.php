<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Seeker extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_SUCCESSFUL = 'Successful';
    public const STATUS_UNSUCCESSFUL = 'Unsuccessful';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUCCESSFUL,
        self::STATUS_UNSUCCESSFUL,
    ];

    protected $fillable = [
        'source_key',
        'name',
        'last_name',
        'first_name',
        'email',
        'gender',
        'contact_number',
        'facebook_link',
        'birthdate',
        'age',
        'school',
        'area',
        'active_disciples',
        'year_joined',
        'ministry_involvement',
        'weekly_dgroups_led',
        'generation',
        'refresh_date',
        'year',
        'month',
        'assigned_district',
        'assigned_satellite_slug',
        'dgroup_leader_name',
        'converted_member_id',
        'archived_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'refresh_date' => 'date',
            'archived_at' => 'datetime',
            'age' => 'integer',
            'year' => 'integer',
        ];
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class, 'assigned_satellite_slug', 'slug');
    }

    public function getStatusClassAttribute(): string
    {
        return Str::slug($this->status ?: self::STATUS_PENDING);
    }

    public function getAssignedDistrictLabelAttribute(): string
    {
        return $this->assigned_district ?: 'Unassigned';
    }

    public static function normalizeStatus(?string $status): string
    {
        $statusText = Str::of((string) $status)
            ->trim()
            ->lower()
            ->toString();

        if (str_contains($statusText, 'unsuccess')) {
            return self::STATUS_UNSUCCESSFUL;
        }

        if (str_contains($statusText, 'success')) {
            return self::STATUS_SUCCESSFUL;
        }

        if (str_contains($statusText, 'pending') || str_contains($statusText, 'awaiting')) {
            return self::STATUS_PENDING;
        }

        $normalized = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        return in_array($normalized, self::STATUSES, true)
            ? $normalized
            : self::STATUS_PENDING;
    }
}
