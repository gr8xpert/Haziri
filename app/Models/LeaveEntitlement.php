<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
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
}
