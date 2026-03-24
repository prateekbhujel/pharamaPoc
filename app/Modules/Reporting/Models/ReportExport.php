<?php

namespace App\Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReportExport extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'public_id',
        'status',
        'format',
        'filter_hash',
        'filters',
        'requested_rows',
        'exported_rows',
        'progress',
        'file_path',
        'file_name',
        'error_message',
        'metrics',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'metrics' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::PENDING,
        'format' => 'csv',
        'progress' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            if (! $export->public_id) {
                $export->public_id = (string) Str::uuid();
            }
        });
    }
}
