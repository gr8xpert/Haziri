<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** remaining = opening + accrued + adjusted − used − pending (spec §5.6). */
class LeaveBalance extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening' => 'float', 'accrued' => 'float', 'used' => 'float', 'pending' => 'float', 'adjusted' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['opening', 'accrued', 'used', 'pending', 'adjusted'])->logOnlyDirty();
    }

    public function remaining(): float
    {
        return round($this->opening + $this->accrued + $this->adjusted - $this->used - $this->pending, 1);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
