<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SeekerArchiveLog extends Model
{
    protected $table = 'seeker_archive_log';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Archive logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Archive logs are append-only.'));
    }
}
