<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger row produced by the status engine. Written only by App\Services\Attendance\AttendanceRecomputer (spec §15).
 */
class AttendanceDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => AttendanceStatus::class,
            'first_in' => 'immutable_datetime',
            'last_out' => 'immutable_datetime',
            'flags' => 'array',
            'source_summary' => 'array',
            'computed_at' => 'immutable_datetime',
            'is_locked' => 'boolean',
            'manual_override' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
