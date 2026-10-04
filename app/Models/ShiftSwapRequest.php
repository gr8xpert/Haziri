<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ShiftSwapRequest extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'decided_at' => 'immutable_datetime', 'status' => ShiftSwapStatus::class];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'partner_employee_id');
    }
}
