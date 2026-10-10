<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Days per leave year (and optionally the carry-forward cap and the longest request) for a grade and/or employment
 * type. Overrides the leave type's values;
 * the most specific match wins (grade + type → grade → type → leave type quota).
 */
class LeaveEntitlement extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['employment_type' => EmploymentType::class, 'days_per_year' => 'float', 'carry_forward_max' => 'float', 'max_consecutive_days' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    protected static function booted(): void
    {
        // A new or changed quota applies to this year's balances at once (not only at the next monthly accrual).
        static::saved(fn (self $e) => app(LeaveBalanceService::class)->refreshAccrued($e->leave_type_id));
        static::deleted(fn (self $e) => app(LeaveBalanceService::class)->refreshAccrued($e->leave_type_id));
    }
}
